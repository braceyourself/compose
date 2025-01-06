<?php

use Braceyourself\Compose\Commands\ComposePublishCommand;

test('env variables can be used in the config file', function () {
    $config_file = __DIR__. "/../../config/compose.php";
    $og_config = file_get_contents($config_file);

    // get contents of config/compose.php
    $config = str($og_config)
        ->replaceFirst("[", <<<PHP
            [
                'env_test' => env('APP_ENV'),
            PHP)
        ->value();

    // write the contents to config/compose.php
    file_put_contents($config_file, $config);

    // publish to the application
    $this->artisan('vendor:publish', [
        '--tag' => 'compose-config',
        '--force' => true
    ]);

    // update original file contents
    file_put_contents($config_file, $og_config);

    $config = (new ComposePublishCommand())->loadConfig('production');

    expect($config)->toHaveKey('env_test', 'production');

});

test('networks defined in the config file are published to the compose file', function () {

    config([
        'compose.domain' => 'test',
        'compose.networks' => [
            'test' => [
                'external' => true,
                'name' => 'test'
            ]
        ]
    ]);

    $config = (new ComposePublishCommand())->getComposeConfig('production');

    expect($config)->toHaveKey('networks', [
        'traefik' => [
            'external' => true,
            'name'     => '${COMPOSE_NETWORK}'
        ],
        'test' => [
            'external' => true,
            'name' => 'test'
        ]
    ]);
});