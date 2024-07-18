<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Stringable;

trait HasNginxServices
{
    public function getNginxImageName($env = 'local')
    {
        $hub_username = $this->getDockerHubUsername();
        $app_name = config('app.name') ?: basename(base_path());

        return str("{$app_name}-{$env}-nginx")->slug()
            ->when(!empty($hub_username), fn(Stringable $str) => $str->prepend("{$hub_username}/"))
            ->value();
    }

    private function nginxServiceDefinition($config = [], $env = 'local'): array
    {
        return collect([
            'image'          => $this->getNginxImageName($env),
            'container_name' => '${COMPOSE_DOMAIN}',
            'build'          => [
                'context'    => $env == 'production' ? './build' : '.',
                'dockerfile' => $env == 'production' ? './Dockerfile' : './build/Dockerfile',
                'target'     => 'nginx'
            ],
            'restart'        => 'always',
            'environment'    => [
                'PROXY_PASS'      => 'php',
                'PROXY_PASS_PORT' => '9000',
            ],
            'env_file'       => ['.env'],
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
                    'traefik.http.routers.${COMPOSE_ROUTER}.tls=' . ($env == 'production' ? 'true' : 'false'),
                    'traefik.http.routers.${COMPOSE_ROUTER}.tls.certresolver=resolver',
                ]
            }
        ];
    }
}