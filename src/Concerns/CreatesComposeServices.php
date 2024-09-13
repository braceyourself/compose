<?php

namespace Braceyourself\Compose\Concerns;

use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Artisan;
use function Laravel\Prompts\text;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\warning;

trait CreatesComposeServices
{
    use ConfiguresTraefik;
    use InteractsWithDocker;
    use ModifiesComposeConfiguration;
    use BuildsDockerfile;
    use HasPhpServices;
    use HasNginxServices;
    use HasNodeServices;
    use HasRedisService;
    use HasDatabaseServices;
    use HasMailServices;

    public function getServiceDefinition($config, string $service_name, $env = 'local')
    {
        if ($config === false) {
            return [];
        }

        if ($config === true) {
            $config = [];
        }

        return [$service_name => match ($service_name) {
            'php' => $this->phpServiceDefinition($config, $env),
            'npm' => $this->npmServiceDefinition($config, $env),
            'redis' => $this->redisServiceDefinition($config, $env),
            'nginx' => $this->nginxServiceDefinition($config, $env),
            'horizon' => $this->horizonServiceDefinition($config, $env),
            'scheduler' => $this->schedulerServiceDefinition($config, $env),
            'database' => $this->databaseServiceDefinition($config, $env),
            'mailhog' => $this->mailhogServiceDefinition($config, $env),
            'default' => $this->buildCustomService($config, $service_name, $env),
        }];
    }

    private function getServices($env = 'local')
    {
        return collect(config('compose.services'))
            ->mapWithKeys(fn($config, $service) => $this->getServiceDefinition($config, $service, $env))
            ->filter();
    }

    private function getComposeYaml($env = 'local')
    {
        return Yaml::dump($this->getComposeConfig($env), Yaml::DUMP_OBJECT_AS_MAP);
    }

    private function getComposeConfig($env = 'local')
    {
        return [
            'services' => $this->getServices($env)->toArray(),
            'networks' => [
                'traefik' => [
                    'external' => true,
                    'name'     => '${COMPOSE_NETWORK}'
                ]

            ]
        ];
    }

    public function getLocalBuildPath()
    {
        if (file_exists(base_path('build'))) {
            return base_path('build');
        }

        return __DIR__ . '/../../build';
    }

    public function buildCustomService($config, $service_name, $env)
    {
        return $config;
    }
}