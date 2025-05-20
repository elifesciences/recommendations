<?php
// public/index.php

// Prevent worker script termination when a client connection is interrupted
ignore_user_abort(true);

ini_set('display_error', 'Off');

require_once __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../src/bootstrap.php';

// Handler outside the loop for better performance (doing less work)
$handler = static function () use ($app) {
    // Called when a request is received,
    // superglobals, php://input and the like are reset
    var_dump($_SERVER);
    // $app->run();
    // echo "boo";
};

$maxRequests = (int)($_SERVER['MAX_REQUESTS'] ?? 0);
for ($nbRequests = 0; !$maxRequests || $nbRequests < $maxRequests; ++$nbRequests) {
    $keepRunning = \frankenphp_handle_request($handler);

    // Call the garbage collector to reduce the chances of it being triggered in the middle of a page generation
    gc_collect_cycles();

    if (!$keepRunning) break;
}
