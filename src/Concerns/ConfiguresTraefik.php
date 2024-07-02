<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Facades\Process;

trait ConfiguresTraefik
{
    private function ensureTraefikNetworkExists()
    {
        return str(Process::run("docker network ls --format '{{.Name}}'")->throw()->output())
            ->explode("\n")->filter(fn($network) => str($network)->contains('traefik'))
            ->whenEmpty(function () {
                Process::tty()->run("docker network create traefik")->throw()->output();

                return 'traefik';
            }, fn($c) => $c->first());
    }

    private function getTraefikNetworkName()
    {
        return $this->ensureTraefikNetworkExists();
    }

    private function ensureTraefikIsRunning()
    {
        str(Process::run("docker ps --format '{{.Image}}'")->throw()->output())
            ->explode("\n")->filter(fn($container) => str($container)->contains('traefik'))
            ->whenEmpty(function () {
                $compose_file = __DIR__ . '/../../traefik/docker-compose.yml';

                $this->info("Starting Traefik...");
                $this->info(
                    Process::run("docker compose {$compose_file} up -d")->throw()->output()
                );

            });
    }

    private function getTraefikRouterName()
    {
        return str(base_path())->basename()->slug();
    }
}