<?php

return [
    'domain'           => env('COMPOSE_DOMAIN'),
    'user_id'          => env('COMPOSE_USER_ID', 1000),
    'group_id'         => env('COMPOSE_GROUP_ID', 1000),

    /***
     * Override these settings to modify your project
     */
    'services'         => [
        'php'       => [
            'image' => env('COMPOSE_PHP_IMAGE'),
            'version' => env('COMPOSE_PHP_VERSION'),
            'memory_limit' => env('COMPOSE_PHP_MEMORY_LIMIT', '512M'),
            'extensions' => [
                'gd',
                'bcmath',
                'mbstring',
                'opcache',
                'xsl',
                'zip',
                'ssh2',
                'yaml',
                'pcntl',
                'intl',
                'exif',
                'redis',
                'pdo_mysql',
//                'imap',
//                'sockets',
//                'pdo_pgsql',
//                'sqlsrv',
//                'pdo_sqlsrv',
//                'soap',
            ],
            'packages' => [
                'git',
                'ffmpeg',
                'jq',
                'iputils-ping',
                'poppler-utils',
                'wget',
            ]
        ],
        'scheduler' => app()->environment('production'),
        'horizon'   => app()->environment('production'),
        'nginx'     => [],
        'npm'       => [],
        'mysql'     => [],
        'redis'     => app()->environment('production'),
        'mailhog'   => app()->environment('local'),
    ],
];