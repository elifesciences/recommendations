<?php

use eLife\Recommendations\AppKernel;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__.'/../vendor/autoload.php';

$app = new AppKernel('dev');

$request = Request::createFromGlobals();

$response = $app->handle($request);
$response->send();

$this->terminate($request, $response);
