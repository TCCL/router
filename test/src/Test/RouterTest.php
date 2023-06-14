<?php

namespace TCCL\Test\Router\Test;

use TCCL\Router\Router;
use TCCL\Test\Router\RouterTestCase;
use TCCL\Test\Router\Sample\Handler;
use TCCL\Test\Router\Sample\Subrouter_ExceptionHandling;
use TCCL\Test\Router\Sample\Subrouter_MethodHandling;
use TCCL\Test\Router\Sample\Subrouter_RESTHandling;

final class RouterTest extends RouterTestCase {
    public function testBasicRouting() {
        $router = new Router('TCCL\Router\Router::nop');

        $getPage = function($router) {
            $this->assertInstanceOf(Router::class,$router);
            echo '<p>Hello, World!</p>';
        };
        $router->addRoute(Router::HTTP_GET,'/page',$getPage);

        $output = $this->routeAndCapture($router,'GET','/page');
        $this->assertEquals($output,'<p>Hello, World!</p>');
    }

    public function testNotFound() {
        $counter = 0;
        $notFound = function($router) use(&$counter) {
            $counter += 1;
            $this->assertInstanceOf(Router::class,$router);
        };

        $router = new Router($notFound);
        $router->route('GET','/page');

        $this->assertEquals($counter,1);
    }

    public function testBasePath() {
        $router = new Router('TCCL\Router\Router::nop','/app');

        $getPage = function($router) {
            $this->assertInstanceOf(Router::class,$router);
            echo '<p>Hello, World!</p>';
        };
        $router->addRoute(Router::HTTP_GET,'/page',$getPage);

        $output = $this->routeAndCapture($router,'GET','/app/page');
        $this->assertEquals($output,'<p>Hello, World!</p>');

        $this->assertEquals($router->getURI('/page'),'/app/page');
    }

    public function testRegexRouting() {
        $router = new Router('TCCL\Router\Router::nop');

        $getPage = function($router) {
            $this->assertInstanceOf(Router::class,$router);

            $number = (int)$router->matches[1];
            echo "<p>Page $number</p>";
        };
        $router->addRoute(Router::HTTP_GET,'/^\/page\/([0-9]+)$/',$getPage);

        $output = $this->routeAndCapture($router,'GET','/page/1');
        $this->assertEquals($output,'<p>Page 1</p>');

        $output = $this->routeAndCapture($router,'GET','/page/33');
        $this->assertEquals($output,'<p>Page 33</p>');
    }

    public function testRequestHandler() {
        $router = new Router('TCCL\Router\Router::nop');
        $router->addRoutesFromTable([
            Router::HTTP_GET => [
                '/one' => (new Handler),
            ],

            Router::HTTP_POST => [
                '/two' => 'TCCL\Test\Router\Sample\Handler',
            ],
        ]);

        $output = $this->routeAndCapture($router,'GET','/one');
        $this->assertEquals($output,'GET URI=/one');

        $output = $this->routeAndCapture($router,'POST','/two');
        $this->assertEquals($output,'POST URI=/two');
    }

    public function testSubrouter() {
        $router = new Router('TCCL\Router\Router::nop');
        $router->addRoute(
            Router::HTTP_ALL,
            '/^\/path\//',
            'TCCL\Test\Router\Sample\Subrouter'
        );

        $output = $this->routeAndCapture($router,'GET','/path/one');
        $this->assertEquals($output,'GET URI=/one');
    }

    public function testExceptionHandling() {
        $router = new Subrouter_ExceptionHandling;

        $output = $this->routeAndCapture($router,'GET','/one');
        $this->assertEquals($output,'GET URI=/one');

        $output = $this->routeAndCapture($router,'GET','/two');
        $this->assertEquals($output,'Router Error: 404');

        $output = $this->routeAndCapture($router,'GET','/error');
        $this->assertEquals($output,'Server Error: Whoops!');
    }

    public function testMethodHandling() {
        $router = new Subrouter_MethodHandling;

        $output = $this->routeAndCapture($router,'GET','/one');
        $this->assertEquals($output,'GET URI=/one');

        $output = $this->routeAndCapture($router,'GET','/other');
        $this->assertEquals($output,'OTHER GET URI=/other');
    }

    public function testRESTHandling() {
        $router = new Subrouter_RESTHandling;

        $output = $this->routeAndCapture($router,'GET','/one');
        $this->assertEquals($output,'GET URI=/one');

        $output = $this->routeAndCapture($router,'GET','/rest');
        $this->assertEquals($output,'{"a":"b"}');
    }
}
