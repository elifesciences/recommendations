<?php

$config = [
    'api.uri' => 'http://continuumtest--gateway.elife.internal/',
];

$app = require __DIR__ . '/../src/bootstrap.php';

$app->run();
