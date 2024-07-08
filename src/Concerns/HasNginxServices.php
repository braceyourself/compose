<?php

namespace Braceyourself\Compose\Concerns;

trait HasNginxServices
{
    public function getNginxImageName()
    {
        $hub_username = $this->getDockerHubUsername();
        $app_name = pathinfo(base_path(), PATHINFO_FILENAME);

        return str("$hub_username/$app_name-nginx")->trim('/')->value();
    }

    private function nginxServiceDefinition($config = [], $env): array
    {
        return collect([
            'image'          => $this->getNginxImageName(),
            'container_name' => '${COMPOSE_DOMAIN}',
            'build'          => [
                'context' => $env == 'production' ? './build' : $this->getLocalBuildPath(),
                'target'  => 'nginx'
            ],
            'restart'        => 'always',
            'environment'    => [
                'PROXY_PASS'      => 'php',
                'PROXY_PASS_PORT' => '9000',
            ],
            'depends_on'     => ['php'],
            'labels'         => [
                "traefik.http.routers.{$this->getTraefikRouterName()}.tls" => $env == 'production',
                "traefik.http.routers.{$this->getTraefikRouterName()}.tls.certresolver" => 'resolver',
            ],
            'networks'       => ['default', 'traefik'],
        ])->merge($config)->toArray();
    }
}