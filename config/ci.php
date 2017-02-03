<?php

return [
    'debug' => false,
    'validate' => true,
    'ttl' => 0,
    'api_url' => 'http://localhost:8080',
    'aws' => [
        'queue_name' => 'recommendations--ci',
        'credential_file' => true,
        'region' => 'us-east-1',
        'endpoint' => 'http://localhost:4100',
    ],
];
