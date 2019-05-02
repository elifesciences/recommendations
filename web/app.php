<?php

use Psr\Log\LogLevel;

require_once __DIR__.'/../vendor/autoload.php';

if ('dev' == getenv('APP_ENV')) {
    $config = [
        'api.timeout' => 5,
        'debug' => true,
        'logger.level' => LogLevel::DEBUG,
    ];
}

$app = require __DIR__.'/../src/bootstrap.php';

$app->run();
