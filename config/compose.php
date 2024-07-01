<?php

return [
    'domain' => env('COMPOSE_DOMAIN'),
    'php' => env('COMPOSE_PHP_VERSION'),
    'user_id' => env('COMPOSE_USER_ID', 1000),
    'group_id' => env('COMPOSE_GROUP_ID', 1000),
    'traefik_router' => env('COMPOSE_ROUTER'),
    'traefik_network' => 'traefik_default',

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