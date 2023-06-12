<?php

/**
 * RouterExceptionHandling.php
 *
 * @package tccl\router
 */

namespace TCCL\Router;

/**
 * Encapsulates router exception handling functionality.
 *
 * This trait is designed to be used in a class derived from TCCL\Router\Router.
 */
trait RouterExceptionHandling {
    /**
     * This method is designed to override Router::router() in a derived class.
     */
    public function route(string $method,string $uri,string $basedir = null) {
        try {
            parent::route($method,$uri,$basedir);
        } catch (RouterException $ex) {
            Router::getExecutingRouter()->handleRouterError($ex);
        } catch (\Exception $ex) {
            Router::getExecutingRouter()->handleServerError($ex);
        }
    }

    /**
     * User-defined method for handling unknown server errors.
     *
     * @param Exception $ex
     *  The unknown exception that was caught by the router.
     */
    abstract public function handleServerError(\Exception $ex) : void;

    /**
     * User-defined method for handling router exceptions. These are known
     * exceptions thrown by an application's request handlers.
     */
    abstract public function handleRouterError(RouterException $ex) : void;
}
