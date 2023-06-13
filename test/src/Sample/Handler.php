<?php

namespace TCCL\Test\Router\Sample;

use TCCL\Router\RequestHandler;
use TCCL\Router\Router;

class Handler implements RequestHandler {
    public function run(Router $router) {
        echo $router->method . ' URI=' . $router->uri;
    }
}
