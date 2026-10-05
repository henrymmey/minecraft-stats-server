<?php

use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    'default' => env('LOG_CHANNEL', 'stderr'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'stderr')),
            'ignore_exceptions' => false,
        ],
        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'with' => ['stream' => 'php://stderr'],
            'processors' => [PsrLogMessageProcessor::class],
            'level' => env('LOG_LEVEL', 'info'),
        ],
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'replace_placeholders' => true,
        ],
        'null' => [
            'driver' => 'monolog',
            'handler' => \Monolog\Handler\NullHandler::class,
        ],
    ],
];
