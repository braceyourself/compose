<?php

namespace Braceyourself\Compose;

use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Concerns\CreatesComposeServices;

class DockerComposeProcess
{
    use CreatesComposeServices;

    private bool $tty = false;
    private bool $throw = false;

    public function buildCommand($command): string
    {
        return "echo '{$this->getComposeConfig()}' | docker compose -f - $command";
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
        return Process::tty($this->tty)->run($this->buildCommand($command))->throwIf($this->throw);
    }

    public function runArtisanCommand($command, $tty = false)
    {
        return Process::tty($this->tty)->run($this->buildArtisanCommand($command))->throwIf($this->throw);
    }

    public function runServiceCommand($service, $command, $tty = false)
    {
        return Process::tty($this->tty)->run($this->buildServiceCommand($service, $command))->throwIf($this->throw);
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