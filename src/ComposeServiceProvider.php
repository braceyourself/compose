<?php

namespace Braceyourself\Compose;

use Illuminate\Support\ServiceProvider;
use Braceyourself\Compose\Commands\ComposeUpCommand;
use Braceyourself\Compose\Commands\ComposeSetupCommand;

class ComposeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/compose.php', 'compose');

        $this->publishes([
            __DIR__ . '/../config/compose.php' => config_path('compose.php'),
        ]);
    }

    public function register(): void
    {
        $this->app->bind('compose', function () {
            return new DockerComposeProcess();
        });
        $this->app->bind('docker', function () {
            return new DockerProcess();
        });

        $this->registerCommandsIn(__DIR__ . '/Commands');
    }

    private function registerCommandsIn($dir)
    {
        foreach (scandir($dir) as $file) {
            if (is_file($dir . '/' . $file)) {
                $this->commands('Braceyourself\Compose\Commands\\' . pathinfo($file, PATHINFO_FILENAME));
            }
        }
    }
}