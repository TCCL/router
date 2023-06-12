<?php

/**
 * PayloadVerify.php
 *
 * @package tccl\router
 */

namespace TCCL\Router;

/**
 * Provides functionality for verifying a request payload.
 *
 * You may use this class directly; however, the functionality is primarily
 * exposed through TCCL\Router\Router::getPayloadVerify().
 *
 * Payload verification looks through a request parameter payload and determines
 * if the required parameters exist and are properly constrained. Constraints
 * include data type, range, ETC. A format argument is used to indicate the
 * parameters and constraints.
 *
 * The payload verification processes produces a result payload that contains
 * the verified parameters. The format argument may indicate promotions for each
 * payload parameter that convert the value to the expected data type.
 *
 * Example format array:
 *  [
 *    "name" => "s",
 *    "favorite_number" => "siI",
 *    "age?" => "i",
 *  ]
 *
 * Example payload that passes:
 *  [
 *    "name" => "Roger",
 *    "favorite_number" => "33",
 *  ]
 *
 * Example payload that fails:
 *  [
 *    "name" => "Roger",
 *    "favorite_number" => "33",
 *    "age" => "29", // fail: age, if specified, must be integer
 *  ]
 */
class PayloadVerify {
    private static $currentOptions;

    private static $defaultOptions = [
        'checkExtraneous' => true,
    ];

    private static $typeFns = [
        'b' => 'is_bool',
        's' => 'is_string',
        'i' => 'is_int',
        'f' => 'is_float',
        'd' => 'is_double',
    ];

    private static $promoteFns = [
        'B' => 'boolval',
        'S' => 'strval',
        'I' => 'intval',
        'F' => 'floatval',
        'D' => 'doubleval',
        '^' => 'trim',
    ];

    private static $checkFns = [
        '!' => 'strlen',
        '+' => '\TCCL\Router\PayloadVerify::is_positive',
        '-' => '\TCCL\Router\PayloadVerify::is_negative',
        '*' => '\TCCL\Router\PayloadVerify::is_nonnegative',
        '%' => '\TCCL\Router\PayloadVerify::is_nonzero',
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
    public static function registerType(string $typechar,callable $typeFn) : void {
        $chr = strtolower($typechar[0]);
        if ($chr < 'a' || $chr > 'z') {
            throw new \Exception(
                "Cannot register type: specifier character '$chr' is invalid"
            );
        }

        self::$typeFns[$chr] = $typeFn;
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
    public static function registerPromotion(string $promotechar,callable $promoteFn) : void {
        $chr = strtoupper($promotechar[0]);
        if (isset(self::$checkFns[$chr])) {
            throw new \Exception(
                "Cannot register promotion: character '$chr' is "
               ."already used for check function"
            );
        }

        self::$promoteFns[$chr] = $promoteFn;
    }

    /**
     * Registers a custom check. Note: this can override core checks.
     *
     * @param string $checkchar
     *  The character that identifies the check.
     * @param callable $checkFn
     *  The predicate function called to perform the check.
     */
    public static function registerCheck(string $checkchar,callable $checkFn) : void {
        $chr = strtolower($checkchar[0]);
        if ($chr >= 'a' && $chr <= 'z') {
            throw new \Exception(
                "Cannot register check: specifier character '$chr' is invalid"
            );
        }
        if (isset($promoteFns[$chr])) {
            throw new \Exception(
                "Cannot register check: character '$chr' is "
               ."already used for promotion function"
            );
        }

        self::$checkFns[$chr] = $checkFn;
    }

    /**
     * Verifies a payload structure.
     *
     * @param mixed $vars
     * @param mixed $format
     * @param array $options
     */
    public static function verify(&$vars,$format,array $options = []) : void {
        self::$currentOptions = $options + self::$defaultOptions;
        self::verifyDecide($vars,$format);
        self::$currentOptions = null;
    }

    private static function verifyDecide(&$vars,$format) : void {
        if (is_scalar($format)) {
            self::verifyScalar($vars,$format);
        }
        else {
            self::verifyArray($vars,$format);
        }
    }

    private static function verifyScalar(&$vars,$format) {
        // NOTE: We treat NULL like a scalar value.
        if (!is_scalar($vars) && !is_null($vars)) {
            throw new PayloadVerifyException($vars,$format);
        }

        if (!is_string($format)) {
            $print = var_export($format,true);
            throw new \Exception("Invalid scalar format: $print");
        }

        $typeInfo = self::parseTypeFormat($format);

        $good = false;
        foreach ($typeInfo['verify'] as $fn) {
            if ($fn($vars)) {
                $good = true;
                break;
            }
        }

        if (!$good && is_null($vars) && $typeInfo['allowNull']) {
            $good = true;
        }

        if (!$good) {
            throw new PayloadVerifyException($vars,$format);
        }

        if (isset($typeInfo['promote'])) {
            $vars = $typeInfo['promote']($vars);
        }

        if (isset($typeInfo['check'])) {
            foreach ($typeInfo['check'] as $fn) {
                if (!$fn($vars)) {
                    throw new PayloadVerifyException($vars,$format);
                }
            }
        }
    }

    private static function verifyArray(&$vars,$format) {
        if (!is_array($vars)) {
            throw new PayloadVerifyException($vars,$format);
        }

        // If the format is an indexed array, then check each element.
        if (array_keys($format) === range(0,count($format)-1)) {
            if (!empty($vars) && array_keys($vars) !== range(0,count($vars)-1)) {
                throw new PayloadVerifyException($vars,$format);
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
                throw new PayloadVerifyException($vars,$format);
            }

            $nkeys = count($format);
            foreach ($format as $key => $newFormat) {
                $result = self::parseKey($key);
                if (array_key_exists($result['name'],$vars)
                    && (!is_null($vars[$result['name']])
                        || !$result['optional']))
                {
                    self::verifyDecide($vars[$result['name']],$newFormat);
                }
                else if (!$result['optional']) {
                    throw new PayloadVerifyException($vars,$format);
                }
                else {
                    // Don't count optional keys.
                    unset($vars[$result['name']]);
                    $nkeys -= 1;
                }
            }

            // If we check extraneous, then we make sure the structure matches
            // exactly such that there are no extraneous keys.
            if (self::$currentOptions['checkExtraneous'] && $nkeys != count($vars)) {
                throw new PayloadVerifyException($vars,$format);
            }
        }
    }

    private static function parseKey(string $key) : array {
        $regex = '/^([a-zA-Z_][A-Za-z_0-9]*)([?]?)$/';

        if (preg_match($regex,$key,$match)) {
            return [
                'name' => $match[1],
                'optional' => (strpos($match[2],'?') !== false),
            ];
        }

        throw new \Exception("Invalid payload key '$key' in format");
    }

    private static function parseTypeFormat(string $format) : array {
        $types = implode('',array_keys(self::$typeFns));
        $promotions = implode('',array_keys(self::$promoteFns));
        $checks = implode('',array_keys(self::$checkFns));
        $types = preg_quote($types);
        $promotions = preg_quote($promotions);
        $checks = preg_quote($checks);
        $regex = "/^([$types]+)([$promotions]*)([$checks]*)([?]?)$/";

        if (preg_match($regex,$format,$match)) {
            $results = [];

            foreach (array_unique(str_split($match[1])) as $char) {
                if (!isset(self::$typeFns[$char])) {
                    throw new \Exception("Invalid type specifier '$char'");
                }

                $results['verify'][] = self::$typeFns[$char];
            }

            if (!empty($match[2])) {
                if (!isset(self::$promoteFns[$match[2]])) {
                    throw new \Exception("Invalid type promotion '{$match[2]}'");
                }
                $results['promote'] = self::$promoteFns[$match[2]];
            }
            else {
                $results['promote'] = null;
            }

            if (!empty($match[3])) {
                foreach (array_unique(str_split($match[3])) as $char) {
                    if (!isset(self::$checkFns[$char])) {
                        throw new \Exception("Invalid check specifier '$char'");
                    }

                    $results['check'][] = self::$checkFns[$char];
                }
            }
            else {
                $results['check'] = [];
            }

            $results['allowNull'] = ( $match[4] == '?' );

            return $results;
        }

        throw new \Exception("Invalid scalar format: '$format'");
    }

    private static function is_positive($val) : bool {
        return $val > 0;
    }

    private static function is_negative($val) : bool {
        return $val < 0;
    }

    private static function is_nonnegative($val) : bool {
        return $val >= 0;
    }

    private static function is_nonzero($val) : bool {
        return $val != 0;
    }
}
