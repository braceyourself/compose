<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Compose;
use function Laravel\Prompts\spin;


trait ConfiguresTraefik
{
    private function ensureTraefikNetworkExists($proc = Process::class)
    {
        return str($proc::run("docker network ls --format '{{.Name}}'")->throw()->output())
            ->explode("\n")->filter(fn($network) => str($network)->contains('traefik'))
            ->whenEmpty(function () use ($proc) {
                $proc::tty()->run("docker network create traefik")->throw()->output();

                return 'traefik';
            }, fn($c) => $c->first());
    }

    private function ensureTraefikIsRunning($proc = Process::class)
    {
        str($proc::run("docker ps --format '{{.Image}}'")->throw()->output())
            ->explode("\n")->filter(fn($container) => str($container)->contains('traefik'))
            ->whenEmpty(function () {
                $compose_file = __DIR__ . '/../../traefik/docker-compose.yml';

                spin(
                    fn() => Compose::run("--file {$compose_file} up -d")->throw()->output(),
                    'Starting Traefik...'
                );
            });
    }
}