<?php
use Psr\Log\LogLevel;

require_once __DIR__.'/../vendor/autoload.php';

if (getenv('APP_ENV') == 'dev') {
    $config = [
        'api.timeout' => 5,
        'debug' => true,
        'logger.level' => LogLevel::DEBUG,
    ];
}

$app = require __DIR__.'/../src/bootstrap.php';

$app->run();
