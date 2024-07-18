<?php

namespace Braceyourself\Compose\Concerns;

trait HasNginxServices
{
    public function getNginxImageName()
    {
        $hub_username = $this->getDockerHubUsername();
        $app_name = str(config('app.name'))->slug();

        return str("$hub_username/$app_name-nginx")->trim('/')->value();
    }

    private function nginxServiceDefinition($config = [], $env = 'local'): array
    {
        return collect([
            'image'          => $this->getNginxImageName(),
            'container_name' => '${COMPOSE_DOMAIN}',
            'build'          => [
                'dockerfile' => './build/Dockerfile',
                'target'  => 'nginx'
            ],
            'restart'        => 'always',
            'environment'    => [
                'PROXY_PASS'      => 'php',
                'PROXY_PASS_PORT' => '9000',
            ],
            'env_file'       => ['.env'],
            'depends_on'     => ['php'],
            'networks'       => ['default', 'traefik'],
            ...$this->getNginxVolumes($env),
            ...$this->getNginxLabels($env),
        ])->merge($config)->toArray();
    }

    public function getNginxVolumes($env)
    {
        return [
            'volumes' => match ($env) {
                'local' => [
                    './:/var/www/html',
                ],
                default => [
                    './storage:/var/www/html/storage',
                ]
            }
        ];
    }

    public function getNginxLabels($env)
    {
        return [
            'labels' => match ($env) {
                'local' => [],
                default => [
                    'traefik.http.routers.${COMPOSE_ROUTER}.tls='. ($env == 'production' ? 'true' : 'false'),
                    'traefik.http.routers.${COMPOSE_ROUTER}.tls.certresolver=resolver',
                ]
            }
        ];
    }
}