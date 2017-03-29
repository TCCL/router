<?php

/**
 * RequestHandler.php
 *
 * This file is a part of tccl/router.
 *
 * @package tccl/router
 */

namespace TCCL\Router;

/**
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
    function run(Router $router);
}
