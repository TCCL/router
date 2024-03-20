<?php

/**
 * RouterClosure.php
 *
 * @package tccl\router
 */

namespace TCCL\Router;

use Closure;

final class RouterClosure {
    private $callable;

    public function __construct(Closure $closure) {
        $this->callable = $closure;
    }

    public function __invoke() {
        ($this->callable)();
    }
}
