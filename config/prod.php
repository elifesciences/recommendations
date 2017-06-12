<?php

use Monolog\Logger;

return [
    'api_url' => 'http://prod--gateway.elife.internal',
    'aws' => [
        'credential_file' => true,
        'queue_name' => 'recommendations--prod',
        'region' => 'us-east-1',
    ],
    'logging_level' => Logger::INFO,
];
