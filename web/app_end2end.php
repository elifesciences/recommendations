<?php

$config = [
    'api.uri' => 'http://end2end--gateway.elife.internal/',
];

$app = require __DIR__ . '/../src/bootstrap.php';

$app->run();
