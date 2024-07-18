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

    private function ensureTraefikIsRunning()
    {
        str(Process::run("docker ps --format '{{.Image}}'")->throw()->output())
            ->explode("\n")->filter(fn($container) => str($container)->contains('traefik'))
            ->whenEmpty(function () {
                $compose_file = __DIR__ . '/../../traefik/docker-compose.yml';

                $this->info("Starting Traefik...");
                Process::run(<<<BASH
                docker run -d \
                  --name traefik.localhost \
                  --restart always \
                  -p 80:80 \
                  -p 443:443 \
                  -v "\$HOME/.config/braceyourself/compose/traefik/letsencrypt:/letsencrypt" \
                  -v /var/run/docker.sock:/var/run/docker.sock:ro \
                  traefik:3.0 \
                  --log.level=TRACE \
                  --api.insecure=true \
                  --providers.docker=true \
                  --providers.docker.exposedbydefault=true \
                  --providers.docker.network=traefik_default \
                  --providers.docker.defaultRule=Host(`{{ .ContainerName }}`) \
                  --accesslog=true \
                  --entrypoints.web.address=:80 \
                  --label "traefik.http.services.traefik.loadbalancer.server.port=8080"
                BASH);
            });
    }
}