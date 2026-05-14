<?php

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => 'horizon',
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', 'horizon:'),
    'middleware' => ['web'],
    'waits' => [ 'redis:default' => 60 ],
    'trim' => [
        'recent' => 60, 'pending' => 60, 'completed' => 60,
        'recent_failed' => 10080, 'failed' => 10080, 'monitored' => 10080,
    ],
    'silenced'  => [],
    'metrics'   => [ 'trim_snapshots' => [ 'job' => 24, 'queue' => 24 ] ],
    'fast_termination' => false,
    'memory_limit' => 512,
    'defaults' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['high','default','low','webhooks','broadcasts'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 10,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 90,
            'nice' => 0,
        ],
    ],
    'environments' => [
        'production' => [ 'supervisor-default' => [ 'maxProcesses' => 20, 'balanceMaxShift' => 1, 'balanceCooldown' => 3 ] ],
        'local'      => [ 'supervisor-default' => [ 'maxProcesses' => 3 ] ],
    ],
];
