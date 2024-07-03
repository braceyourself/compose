<?php

namespace Braceyourself\Compose\Concerns;

trait HasMailServices
{
    private function mailhogServiceDefinition($config): array
    {
        return collect([
            'image'          => 'mailhog/mailhog',
            'container_name' => "mailhog.{$this->getDomainName()}",
            'restart'        => 'always',
            'networks'       => ['default', 'traefik'],
            'profiles'       => ['local'],
            'labels'         => ["traefik.http.services.mailhog-{$this->getTraefikRouterName()}.loadbalancer.server.port=8025"],
        ])->merge($config)->toArray();
    }
}