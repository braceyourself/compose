<?php

return [
    'domain' => env('COMPOSE_DOMAIN'),
    'php' => env('COMPOSE_PHP_VERSION'),
    'user_id' => 1000,
    'group_id' => 1000,
    'traefik_network' => 'traefik_default',
    'traefik_router' => env('COMPOSE_ROUTER'),
    /***
     * Override these settings to modify your project
     */
    'services' => [
        'php' => [
            'image' => env('COMPOSE_PHP_IMAGE'),
        ],
        'scheduler' => [],
        'horizon' => [],
        'nginx' => [],
        'npm' => [],
        'mysql' => [],
        'redis' => [],
        'mailhog' => [],
    ],
];