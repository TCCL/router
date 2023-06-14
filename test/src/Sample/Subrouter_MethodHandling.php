<?php

namespace TCCL\Test\Router\Sample;

use TCCL\Router\Router;
use TCCL\Router\RouterMethodHandling;

class Subrouter_MethodHandling extends Subrouter_ExceptionHandling {
    use RouterMethodHandling;

    public function __construct() {
        parent::__construct();

        $this->addRoutesFromTable([
            Router::HTTP_GET => [
                '/other' => 'TCCL\Test\Router\Sample\Handler::other',
            ],
        ]);
    }
}
