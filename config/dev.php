<?php

return [
    'debug' => true,
    'validate' => true,
    'ttl' => 0,
    'aws' => [
        'queue_name' => 'recommendations--dev',
        'credential_file' => true,
        'region' => 'us-east-1',
        'endpoint' => 'http://localhost:4100',
    ],
];
