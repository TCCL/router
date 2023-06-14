<?php

namespace TCCL\Test\Router\Sample;

use TCCL\Router\Router;
use TCCL\Router\RouterRESTHandling;

class Subrouter_RESTHandling extends Subrouter_MethodHandling {
    use RouterRESTHandling;

    public function __construct() {
        parent::__construct();

        $this->addRoutesFromTable([
            Router::HTTP_GET => [
                '/rest' => 'TCCL\Test\Router\Sample\Handler::restEndpoint',
            ],
        ]);
    }
}
