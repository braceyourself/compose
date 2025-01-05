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

    $config = (new ComposePublishCommand())->loadConfigFromFile('production');

    expect($config)->toHaveKey('env_test', 'production');

});