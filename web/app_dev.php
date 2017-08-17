<?php

use Psr\Log\LogLevel;

require_once __DIR__.'/../vendor/autoload.php';

$config = [
    'debug' => true,
    'logger.level' => LogLevel::DEBUG,
];

$app = require __DIR__.'/../src/bootstrap.php';

$app->run();
