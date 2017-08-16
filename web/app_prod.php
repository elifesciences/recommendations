<?php

$config = [
    'api.uri' => 'http://prod--gateway.elife.internal/',
];

$app = require __DIR__.'/../src/bootstrap.php';

$app->run();
