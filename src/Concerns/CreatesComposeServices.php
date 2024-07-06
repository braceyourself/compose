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

    public function getBuildContext($env = 'local')
    {
        return match($env){
            'local' => file_exists(base_path('build'))
                ? base_path('build')
                : __DIR__ . '/../../build',
            'production' => './build'
        };
    }

    public function getServiceDefinition(string $service_name, $environment = 'local')
    {
        $config = config("compose.services.{$service_name}");

        if ($config === false) {
            return [];
        }

        if ($config === true) {
            $config = [];
        }

        return [$service_name => match ($service_name) {
            'php' => $this->phpServiceDefinition($config, $environment),
            'nginx' => $this->nginxServiceDefinition($config, $environment),
            'npm' => $this->npmServiceDefinition($config, $environment),
            'mysql', 'database' => $this->databaseServiceDefinition($config, $environment),
            'scheduler' => $this->schedulerServiceDefinition($config, $environment),
            'redis' => $this->redisServiceDefinition($config, $environment),
            'mailhog' => $this->mailhogServiceDefinition($config, $environment),
            'horizon' => $this->horizonServiceDefinition($config, $environment),
        }];
    }

    private function getServices($environment = 'local')
    {
        return collect(config('compose.services'))
            ->mapWithKeys(fn($config, $service_name) => $this->getServiceDefinition($service_name, $environment))
            ->filter();
    }

    private function getComposeYaml($env = 'local')
    {
//        Cache::store('array')->clear();

        return Yaml::dump($this->getComposeConfig($env), Yaml::DUMP_OBJECT_AS_MAP);
    }

    private function getComposeConfig($environment = 'local')
    {
        return [
            'services' => $this->getServices($environment)->toArray(),
            'networks' => [
                'traefik' => [
                    'external' => true,
                    'name'     => $this->getTraefikNetworkName()
                ]

            ]
        ];
    }
}