<?php

$config = [
    'api.uri' => 'http://localhost:8080/',
];

$app = require __DIR__.'/../src/bootstrap.php';

$app->run();
