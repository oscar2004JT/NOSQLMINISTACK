<?php

return [
    'table' => env('DYNAMODB_TABLE', 'MiMercadoLocal'),
    'endpoint' => env('DYNAMODB_ENDPOINT', 'http://localhost:4566'),
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'key' => env('AWS_ACCESS_KEY_ID', 'test'),
    'secret' => env('AWS_SECRET_ACCESS_KEY', 'test'),
];
