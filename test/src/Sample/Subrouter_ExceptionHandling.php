<?php

namespace TCCL\Test\Router\Sample;

use TCCL\Router\Router;
use TCCL\Router\RouterException;
use TCCL\Router\RouterExceptionHandling;

class Subrouter_ExceptionHandling extends Subrouter {
    use RouterExceptionHandling;

    public static function not_found(Router $router) {
        throw new RouterException(404);
    }

    public function handleServerError(\Exception $ex) : void {
        echo 'Server Error: ' . $ex->getMessage();
    }

    public function handleRouterError(RouterException $ex) : void {
        echo 'Router Error: ' . $ex->getStatusCode();
    }
}
