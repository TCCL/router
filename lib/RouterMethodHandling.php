<?php

/**
 * RouterMethodHandling.php
 *
 * @package tccl\router
 */

namespace TCCL\Router;

/**
 * Provides routing to a particular method within a request handler
 * implementation.
 *
 * When using this trait, route request handler descriptions can now have the
 * form:
 *
 *   ClassName::MethodName
 *
 * To indicate a static method, use the form:
 *
 *   @ClassName::MethodName
 */
trait RouterMethodHandling {
    private $handlerMethod;

    /**
     * Gets the method identified by the current route handler description.
     *
     * @return string
     *  NOTE: null is returned if no handler method was identified.
     */
    public function getHandlerMethod() : ?string {
        return $this->handlerMethod;
    }

    /**
     * Overrides Router::createHandler().
     */
    protected function createHandler($handler) : callable {
        if (is_string($handler)) {
            $parts = @explode('::',$handler,2);

            if (count($parts) == 2) {
                list($class,$method) = $parts;
                $this->handlerMethod = $method;

                if ($class[0] != '@') {
                    $class = new $class;
                }
                else {
                    $class = substr($class,1);
                }

                return [$class,$method];
            }
        }

        return parent::createHandler($handler);
    }
}
