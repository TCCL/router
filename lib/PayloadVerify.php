<?php

/**
 * PayloadVerify.php
 *
 * tccl/router
 */

namespace TCCL\Router;

use Exception;

class PayloadVerify {
    private static $currentOptions;

    private static $defaultOptions = [
        'checkExtraneous' => true,
    ];

    private static $typeFns = [
        's' => 'is_string',
        'i' => 'is_int',
        'f' => 'is_float',
        'd' => 'is_double',
    ];

    private static $promoteFns = [
        'S' => 'strval',
        'I' => 'intval',
        'F' => 'floatval',
        'D' => 'doubleval',
    ];

    /**
     * Registers a custom type. Note: this can override core types.
     *
     * @param string $typechar
     *  The character that identifies the type in a scalar format. This is a
     *  lowercase character.
     * @param callable $typeFn
     *  The predicate function called to verify the scalar.
     */
    public static function registerType($typechar,callable $typeFn) {
        self::$typeFns[strtolower($typechar[0])] = $typeFn;
    }

    /**
     * Registers a custom type promotion. Note: this can override core
     * promotions.
     *
     * @param string $promotechar
     *  The character that identifies the promotion in a scalar format. This is
     *  an uppercase character.
     * @param callable $promoteFn
     *  The function called to perform the type promotion. Such a function takes
     *  a single scalar argument and returns the promoted value.
     */
    public static function registerPromotion($promotechar,callable $promoteFn) {
        self::$promoteFns[strtoupper($promotechar[0])] = $promoteFn;
    }

    /**
     * Verifies a payload structure.
     *
     * @param mixed $vars
     * @param mixed $format
     * @param array $options
     */
    public static function verify(&$vars,$format,$options = []) {
        self::$currentOptions = $options + self::$defaultOptions;
        $result = self::verifyDecide($vars,$format);
        self::$currentOptions = null;
        return $result;
    }

    private static function verifyDecide(&$vars,$format) {
        if (is_scalar($format)) {
            return self::verifyScalar($vars,$format);
        }

        return self::verifyArray($vars,$format);
    }

    private static function verifyScalar(&$vars,$format) {
        if (!is_scalar($vars)) {
            throw new RouterException(400);
        }

        if (!is_string($format)) {
            throw new Exception('Invalid scalar format');
        }

        $typeInfo = self::parseTypeFormat($format);

        $good = false;
        foreach ($typeInfo['verify'] as $fn) {
            if ($fn($vars)) {
                $good = true;
            }
        }

        if (isset($typeInfo['promote'])) {
            $vars = $typeInfo['promote']($vars);
        }

        if (!$good) {
            throw new RouterException(400);
        }
    }

    private static function verifyArray(&$vars,$format) {
        if (!is_array($vars)) {
            throw new RouterException(400);
        }

        // If the format is an indexed array, then check each element.
        if (array_keys($format) === range(0,count($format)-1)) {
            if (array_keys($vars) !== range(0,count($vars)-1)) {
                throw new RouterException(400);
            }

            $subformat = $format[0];
            foreach ($vars as &$elem) {
                self::verifyDecide($elem,$subformat);
            }
            unset($elem);
        }
        else {
            // Otherwise it is an associative array that denotes a precise
            // payload structure.

            if (!empty($vars) && array_keys($vars) === range(0,count($vars)-1)) {
                throw new RouterException(400);
            }

            $nkeys = count($format);
            foreach ($format as $key => $newFormat) {
                $result = self::parseKey($key);
                if (isset($vars[$result['name']])) {
                    self::verifyDecide($vars[$result['name']],$newFormat);
                }
                else if (!$result['optional']) {
                    throw new RouterException(400);
                }
                else {
                    // Don't count optional keys.
                    $nkeys -= 1;
                }
            }

            // If we check extraneous, then we make sure the structure matches
            // exactly such that there are no extraneous keys.
            if (self::$currentOptions['checkExtraneous'] && $nkeys != count($vars)) {
                throw new RouterException(400);
            }
        }
    }

    private static function parseKey($key) {
        $regex = '/^([a-zA-Z_][A-Za-z_0-9]*)(\??)$/';

        if (preg_match($regex,$key,$match)) {
            return [
                'name' => $match[1],
                'optional' => (strpos($match[2],'?') !== false),
            ];
        }

        throw new Exception('Invalid payload key in format');
    }

    private static function parseTypeFormat($format) {
        $types = implode('',array_keys(self::$typeFns));
        $promotions = implode('',array_keys(self::$promoteFns));
        $regex = "/^([$types]+)([$promotions])?$/";

        if (preg_match($regex,$format,$match)) {
            $results = [];
            foreach (array_unique(str_split($match[1])) as $char) {
                if (!isset(self::$typeFns[$char])) {
                    throw new Exception("Invalid type specifier '$char'");
                }

                $results['verify'][] = self::$typeFns[$char];
            }

            if (!empty($match[2])) {
                if (!isset(self::$promoteFns[$match[2]])) {
                    throw new Exception("Invalid type promotion '{$match[2]}'");
                }
                $results['promote'] = self::$promoteFns[$match[2]];
            }
            else {
                $results['promote'] = null;
            }

            return $results;
        }

        throw new Exception('Invalid scalar format');
    }
}
