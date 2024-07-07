<?php

namespace Braceyourself\Compose;

use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Concerns\CreatesComposeServices;

class DockerComposeProcess
{
    use CreatesComposeServices;

    private bool $tty = false;
    private bool $throw = false;
    private array $env;

    public function __construct()
    {
        $this->env = getenv();
    }

    public function buildCommand($command): string
    {
        if(file_exists(base_path('docker-compose.yml'))) {
            return "docker compose $command";
        }

        $config = str($this->getComposeYaml())->replace('${', '\${');

        return "echo '{$config}' | docker compose -f - $command";
    }

    public function buildServiceCommand($service, $command): string
    {
        return $this->buildCommand("exec -T $service $command");
    }

    public function buildArtisanCommand($command): string
    {
        return $this->buildServiceCommand('php', "php artisan $command");
    }

    public function run($command)
    {
        return Process::tty($this->tty)
            ->env($this->env)
            ->run($this->buildCommand($command))
            ->throwIf($this->throw);
    }

    public function runArtisanCommand($command)
    {
        return Process::tty($this->tty)
            ->env($this->env)
            ->run($this->buildArtisanCommand($command))
            ->throwIf($this->throw);
    }

    public function runServiceCommand($service, $command)
    {
        return Process::tty($this->tty)
            ->env($this->env)
            ->run($this->buildServiceCommand($service, $command))
            ->throwIf($this->throw);
    }


    public function env(array $variables)
    {
        $this->env = array_merge($this->env, $variables);

        return $this;
    }

    public function tty($tty = true)
    {
        $this->tty = true;

        return $this;
    }

    public function throw($throw = true)
    {
        $this->throw = true;

        return $this;
    }
}