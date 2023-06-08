<?php

/**
 * RequestHandler.php
 *
 * @package tccl\router
 */

namespace TCCL\Router;

/**
 * Interface for request handler implementations.
 *
 * This interface represents a handler object. The default, class-based router
 * handling expects instances of this interface.
 */
interface RequestHandler {
    /**
     * This function should run the request handler.
     *
     * @param Router $router
     *  The router instance invoking the request handler.
     */
    public function run(Router $router);
}
