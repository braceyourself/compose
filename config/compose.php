<?php

return [
    'domain'   => env('COMPOSE_DOMAIN'),
    'user_id'  => env('COMPOSE_USER_ID', 1000),
    'group_id' => env('COMPOSE_GROUP_ID', 1000),
    'profiles' => env('COMPOSE_PROFILES', 'local'),

    /***
     * wait for the database to be ready before starting the application
     */
    'wait_for_database' => env('COMPOSE_WAIT_FOR_DATABASE', true),

    /***
     * A shell script that will run when building the docker image
     */
    'php_install_script' => null,

    /***
     * run these commands when a php container starts
     * The optimize command is always run
     */
    'startup_commands' => [
        'php' => [
            'storage:link',
            'migrate' => ['--force' => env('app_env') === 'production'],
            'config:clear',
            'clear',
            'clear-compiled',
            'chown www-data: public/storage',
        ],
        'horizon' => [
            'horizon',
        ],
        'websockets' => [
            'websockets:serve',
        ],
        'scheduler' => [
            //
        ],
    ],

    /***
     * Override these settings to modify your project
     */
    'services' => [
        'php'       => [
            'image'        => env('COMPOSE_PHP_IMAGE'),
            'version'      => env('COMPOSE_PHP_VERSION'),
            'memory_limit' => env('COMPOSE_PHP_MEMORY_LIMIT', '512M'),
            'extensions'   => [
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
            'packages'     => [
                'git',
                'ffmpeg',
                'jq',
                'iputils-ping',
                'poppler-utils',
                'wget',
            ]
        ],
        'scheduler' => [
            'profiles' => ['production']
        ],
        'horizon'   => [
            'profiles' => ['production']
        ],
        'nginx'     => [],
        'npm'       => [],
        'database'     => [
            'expose_on_port' => env('COMPOSE_DB_PORT'),
        ],
        'redis'     => [
            'profiles' => ['production']
        ],
        'mailhog'   => [
            'profiles' => ['local']
        ],
    ],

    'deploy' => [
        'docker_hub_username' => env('DOCKER_HUB_USERNAME'),
        'docker_hub_password' => env('DOCKER_HUB_PASSWORD'),

        'host' => env('COMPOSE_DEPLOY_HOST'),
        'user' => env('COMPOSE_DEPLOY_USER'),
        'path' => env('COMPOSE_DEPLOY_PATH'),
        'password' => env('COMPOSE_DEPLOY_PASSWORD'),
    ]
];