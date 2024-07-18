<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Facades\Http;

trait HasPhpServices
{
    private function phpServiceDefinition(array $config = [], $env = 'local'): array
    {
        return collect([
            'image'          => '${COMPOSE_PHP_IMAGE}',
            //'container_name' => str(config('app.name'))->slug() . '-php',
            'user'           => '${USER_ID}:${GROUP_ID}',
            'volumes'        => $this->getPhpVolumes($env),
            'build'          => [
                'target'     => $env == 'production' ? 'production' : 'php',
                'dockerfile' => './build/Dockerfile',
            ],
            'healthcheck'    => [
                'test'     => ['CMD', 'php', '-v'],
                'interval' => '5s',
                'timeout'  => '10s',
                'retries'  => 5,
            ],
            'env_file'       => ['.env'],
            'working_dir'    => '/var/www/html',
            'restart'        => 'always',
            'environment'    => [
                'SERVICE' => 'php'
            ]
        ])->merge($config)
            ->except('extensions', 'packages', 'memory_limit', 'version')
            ->toArray();
    }

    private function schedulerServiceDefinition($config = [], $env = 'local'): array
    {
        return collect([
            'image'          => '${COMPOSE_PHP_IMAGE}',
            'container_name' => str(config('app.name'))->slug() . '-scheduler',
            'user'           => '${USER_ID}:${GROUP_ID}',
            'restart'        => 'always',
            'volumes'        => $this->getPhpVolumes($env),
            'depends_on'     => ['php'],
            'environment'    => [
                'SERVICE' => 'scheduler'
            ]
        ])->merge($config)->toArray();
    }


    private function horizonServiceDefinition($config = [], $env = 'local'): array
    {
        return collect([
            'image'          => '${COMPOSE_PHP_IMAGE}',
            'container_name' => str(config('app.name'))->slug() . '-horizon',
            'user'           => '${USER_ID}:${GROUP_ID}',
            'restart'        => 'always',
            'volumes'        => $this->getPhpVolumes($env),
            'depends_on'     => ['php'],
            'command'        => 'php artisan horizon',
            'environment'    => [
                'SERVICE' => 'horizon'
            ]
        ])->merge($config)->toArray();
    }

    private function getPhpVolumes($env = 'local')
    {
        return match ($env) {
            'local' => $this->getLocalPhpVolumes(),
            default => [
                '$HOME/.config/psysh:/var/www/.config/psysh',
                './.env:/var/www/html/.env',
                './storage:/var/www/html/storage',
            ]
        };
    }

    private function getLocalPhpVolumes(): array
    {
        $volumes = [
            './:/var/www/html',
            '~/.ssh:/var/www/.ssh',
            '$HOME:$HOME',
        ];

        // check if any local paths are defined in the repository section of composer.json
        if (file_exists($composer_json = base_path('composer.json'))) {
            $volumes = collect(data_get(json_decode(file_get_contents($composer_json), true), 'repositories', []))
                ->where('type', 'path')
                ->map->url
                // map it directly to the container
                ->map(fn($path) => "$path:$path")
                ->merge($volumes)
                ->toArray();
        }

        return $volumes;
    }

    private function getPhpVersions()
    {
        return Http::throw()->get('https://www.php.net/releases/active')
            ->collect()
            ->map(fn($list, $version) => collect($list)->keys())
            ->flatten()
            ->mapWithKeys(fn($version) => [$version => $version])
            ->sortDesc();
    }
}
