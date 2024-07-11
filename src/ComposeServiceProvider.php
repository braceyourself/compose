<?php

namespace Braceyourself\Compose;

use Illuminate\Process\Factory;
use Illuminate\Process\ProcessResult;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Commands\ComposeUpCommand;
use Braceyourself\Compose\Commands\ComposeSetupCommand;

class ComposeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/compose.php', 'compose');

        $this->publishes([
            __DIR__ . '/../config/compose.php' => config_path('compose.php'),
        ], 'compose-config');
    }

    public function register(): void
    {
        $this->app->bind('braceyourself-compose', function () {
            return new DockerComposeProcess(app(Factory::class));
        });

        $this->app->bind('braceyourself-docker', function () {
            return new DockerProcess();
        });

        $this->app->bind('braceyourself-remote', function () {
            return new RemoteProcess(app(Factory::class));
        });

        $this->registerCommandsIn(__DIR__ . '/Commands');

        $this->setUpMacros();
    }

    private function registerCommandsIn($dir)
    {
        foreach (scandir($dir) as $file) {
            if (is_file($dir . '/' . $file)) {
                $this->commands('Braceyourself\Compose\Commands\\' . pathinfo($file, PATHINFO_FILENAME));
            }
        }
    }

    private function setUpMacros()
    {
        Process::macro('remote', function ($user, $host) {
            $this->remote = true;
            $this->user = $user;
            $this->host = $host;
            return $this;
        });
    }
}