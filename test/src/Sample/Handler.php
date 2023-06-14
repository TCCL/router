<?php

namespace TCCL\Test\Router\Sample;

use TCCL\Router\RequestHandler;
use TCCL\Router\Router;

class Handler implements RequestHandler {
    public function run(Router $router) {
        echo $router->method . ' URI=' . $router->uri;
    }

    public function other(Router $router) {
        echo 'OTHER ' . $router->method . ' URI=' . $router->uri;
        return false;
    }

    public function restEndpoint(Router $router) {
        return [
            'a' => 'b',
        ];
    }
}
