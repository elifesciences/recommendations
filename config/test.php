<?php

$config = require __DIR__.'/dev.php';

return [
        'api.uri' => 'http://api.elifesciences.org/',
        'mock' => true,
    ] + $config;
