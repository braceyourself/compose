<?php

namespace Braceyourself\Compose\Concerns;

trait HasNginxServices
{
    public function getNginxImageName()
    {
        $hub_username = $this->getDockerHubUsername();
        $app_name = pathinfo(base_path(), PATHINFO_FILENAME);

        return "$hub_username/$app_name-nginx";
    }

    private function nginxServiceDefinition($config = [], $environment = 'local'): array
    {
        return collect([
            'image'          => $this->getNginxImageName(),
            'container_name' => $this->getDomainName(),
            'build'          => [
                'context' => $this->getBuildContext($environment),
                'target'  => 'nginx'
            ],
            'restart'        => 'always',
            'environment'    => [
                'PROXY_PASS'      => 'php',
                'PROXY_PASS_PORT' => '9000',
            ],
            'depends_on'     => ['php'],
            'labels'         => [
                "traefik.http.routers.{$this->getTraefikRouterName()}.tls" => app()->environment('production'),
            ],
            'networks'       => ['default', 'traefik'],
        ])->merge($config)->toArray();
    }
}