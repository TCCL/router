<?php

/**
 * RequestHandler.php
 *
 * @package tccl/router
 */

namespace TCCL\Router;

/**
 * RequestHandler
 *
 * This interface represents a handler object. Class-based request handlers must
 * implement this interface.
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
