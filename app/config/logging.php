<?php

use Monolog\Handler\StreamHandler;

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'deprecations' => [ 'channel' => 'null' ],
    'channels' => [
        'stack' => [ 'driver' => 'stack', 'channels' => ['daily','stderr'], 'ignore_exceptions' => false ],
        'daily' => [ 'driver' => 'daily', 'path' => storage_path('logs/laravel.log'), 'level' => 'debug', 'days' => 14 ],
        'stderr'=> [ 'driver' => 'monolog', 'handler' => StreamHandler::class, 'with' => ['stream' => 'php://stderr'] ],
        'evolution'=> [ 'driver' => 'daily', 'path' => storage_path('logs/evolution.log'), 'level' => 'debug', 'days' => 7 ],
        'webhooks' => [ 'driver' => 'daily', 'path' => storage_path('logs/webhooks.log'), 'level' => 'debug', 'days' => 7 ],
        'null'  => [ 'driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class ],
    ],
];
