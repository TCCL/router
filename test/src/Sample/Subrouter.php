<?php

namespace TCCL\Test\Router\Sample;

use TCCL\Router\Router;

class Subrouter extends Router {
    public static function not_found(Router $router) {
        echo 'Not Found';
    }

    public function __construct() {
        parent::__construct([get_class(),'not_found']);

        $this->addRoutesFromTable([
            Router::HTTP_GET => [
                '/one' => 'TCCL\Test\Router\Sample\Handler',
            ],
        ]);
    }
}
