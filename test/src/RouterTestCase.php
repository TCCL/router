<?php

namespace TCCL\Test\Router;

use PHPUnit\Framework\TestCase;
use TCCL\Router\Router;

abstract class RouterTestCase extends TestCase {
    public function routeAndCapture(Router $router,string $method,string $uri,string $basePath = '') : string {
        ob_start();
        $router->route($method,$uri,$basePath);
        $output = ob_get_clean();
        return $output;
    }
}
