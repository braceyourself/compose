<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Facades\Http;

trait HasPhpServices
{
    private function phpServiceDefinition(array $config = [], $environment = 'local'): array
    {
        return collect([
            'image'       => $this->getPhpImageName(),
            'user'        => "{$this->getUserId()}:{$this->getGroupId()}",
            'volumes'     => $this->getPhpVolumes($environment),
            'build' => [
                'context' => $this->getBuildContext($environment),
                'dockerfile' => 'Dockerfile',
                'target' => $environment == 'local' ? 'app' : 'production'
            ],
            'env_file'    => ['.env'],
            'working_dir' => '/var/www/html',
            'restart'     => 'always',
            'environment' => [
                'SERVICE' => 'php'
            ]
        ])->merge($config)
            ->except('extensions', 'packages', 'memory_limit', 'version')
            ->toArray();
    }

    private function schedulerServiceDefinition($config = [], $environment = 'local'): array
    {
        return collect([
            'image'       => $this->getPhpImageName(),
            'restart'     => 'always',
            'volumes'     => $this->getPhpVolumes($environment),
            'depends_on'  => ['php'],
            'environment' => [
                'SERVICE' => 'scheduler'
            ]
        ])->merge($config)->toArray();
    }


    private function horizonServiceDefinition($config = [], $environment = 'local'): array
    {
        return collect([
            'image'       => $this->getPhpImageName(),
            'restart'     => 'always',
            'volumes'     => $this->getPhpVolumes($environment),
            'depends_on'  => ['php'],
            'command'     => 'php artisan horizon',
            'environment' => [
                'SERVICE' => 'horizon'
            ]
        ])->merge($config)->toArray();
    }

    private function getPhpVolumes($environment = 'local')
    {
        $volumes = [
            '$HOME/.config/psysh:/var/www/.config/psysh',
        ];

        return $environment === 'local'
            ? array_merge($volumes, $this->getLocalPhpVolumes())
            : array_merge($volumes, $this->getProductionPhpVolumes());
    }

    private function getLocalPhpVolumes(): array
    {
        $volumes = ['./:/var/www/html'];

        // check if any local paths are defined in the repository section of composer.json
        if (file_exists($composer_json = base_path('composer.json'))) {
            $volumes = collect(data_get(json_decode(file_get_contents($composer_json), true), 'repositories', []))
                ->where('type', 'path')
                ->map->url
                // map it directly to the container
                ->map(fn($path) => "$composer_json:$composer_json")
                ->merge($volumes)
                ->toArray();
        }

        return $volumes;
    }

    private function getProductionPhpVolumes(): array
    {
        return [
            './.env:/var/www/html/.env',
        ];
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
