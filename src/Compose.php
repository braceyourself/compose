<?php

namespace Braceyourself\Compose;

use Braceyourself\Compose\Concerns\CreatesComposeServices;

class Compose
{
    use CreatesComposeServices;

    public function command($command): string
    {
        if(!app()->runningInConsole()) {
            throw new \Exception('Compose should not be run outside of the console.');
        }

        return "echo '{$this->getComposeConfig()}' | docker compose -f - $command";
    }

    public function serviceCommand($service, $command): string
    {
        return $this->command("exec -T $service $command");
    }

    public function artisanCommand($command): string
    {
        return $this->serviceCommand('php', "php artisan $command");
    }
}