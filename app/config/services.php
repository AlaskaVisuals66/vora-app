<?php

return [
    'evolution' => [
        'url'     => env('EVOLUTION_API_URL', 'http://evolution:8080'),
        'api_key' => env('EVOLUTION_API_KEY'),
        'instance'=> env('EVOLUTION_DEFAULT_INSTANCE', 'helpdesk-main'),
        'timeout' => 15,
    ],
    'n8n' => [
        'base_url'  => env('N8N_BASE_URL', 'http://n8n:5678'),
        'api_token' => env('N8N_API_TOKEN'),
        'webhook_url' => env('N8N_WEBHOOK_URL', 'http://localhost:5678/'),
    ],
];
