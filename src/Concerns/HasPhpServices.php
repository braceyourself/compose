<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Facades\Http;

trait HasPhpServices
{
    private function phpServiceDefinition(array $config = []): array
    {
        return collect([
            'image'       => $this->getPhpImageName(),
            'user'        => "{$this->getUserId()}:{$this->getGroupId()}",
            'volumes'     => $this->getPhpVolumes(),
            'build' => [
                'context' => __DIR__.'/../../build',
                'dockerfile' => 'Dockerfile',
                'target' => 'app'
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

    private function schedulerServiceDefinition($config = []): array
    {
        return collect([
            'image'       => $this->getPhpImageName(),
            'restart'     => 'always',
            'volumes'     => $this->getPhpVolumes(),
            'depends_on'  => ['php'],
            'environment' => [
                'SERVICE' => 'scheduler'
            ]
        ])->merge($config)->toArray();
    }


    private function horizonServiceDefinition($config = []): array
    {
        return collect([
            'image'       => $this->getPhpImageName(),
            'restart'     => 'always',
            'volumes'     => $this->getPhpVolumes(),
            'depends_on'  => ['php'],
            'command'     => 'php artisan horizon',
            'environment' => [
                'SERVICE' => 'horizon'
            ]
        ])->merge($config)->toArray();
    }

    private function getPhpVolumes()
    {
        return [
            ...(fn(): array => app()->environment('local')
                ? $this->getLocalPhpVolumes()
                : [
                    // production volumes
                    './.env:/var/www/html/.env'
                ])(),
            // always
            '$HOME/.config/psysh:/var/www/.config/psysh',
        ];
    }

    private function getLocalPhpVolumes(): array
    {
        $volumes = ['./:/var/www/html'];

        // check if any local paths are defined in the repository section of composer.json
        if (file_exists($path = base_path('composer.json'))) {
            $volumes = collect(data_get(json_decode(file_get_contents($path), true), 'repositories', []))
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
