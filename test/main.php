<?php

/**
 * main.php
 *
 * tccl/router/test
 */

use PHPUnit\TextUI\Command as PHPUnitCommand;

require_once 'vendor/autoload.php';

function main() : void {
    global $argv;

    $args = [
        'phpunit',
    ];

    $userArgs = array_slice($argv,1);
    if (empty($userArgs)) {
        $userArgs = ['test/src/Test'];
    }
    $args = array_merge($args,$userArgs);

    $app = new PHPUnitCommand;
    $result = $app->run($args,false);
}

if (php_sapi_name() !== 'cli') {
    error_log(__FILE__ . ' must execute using PHP CLI');
    exit(1);
}

main();
