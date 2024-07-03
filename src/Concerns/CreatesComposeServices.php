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

    public function getServiceDefinition($config, string $service_name)
    {
        if ($config === false) {
            return [];
        }

        if ($config === true) {
            $config = [];
        }

        return [$service_name => match ($service_name) {
            'php' => $this->phpServiceDefinition($config),
            'nginx' => $this->nginxServiceDefinition($config),
            'npm' => $this->npmServiceDefinition($config),
            'mysql', 'database' => $this->databaseServiceDefinition($config),
            'scheduler' => $this->schedulerServiceDefinition($config),
            'redis' => $this->redisServiceDefinition($config),
            'mailhog' => $this->mailhogServiceDefinition($config),
            'horizon' => $this->horizonServiceDefinition($config),
        }];
    }

    private function getComposeConfig()
    {
        Cache::store('array')->clear();

        return Yaml::dump([
            'services' => collect(config('compose.services'))
                ->mapWithKeys($this->getServiceDefinition(...))
                ->filter()
                ->toArray(),
            'networks' => [
                'traefik' => [
                    'external' => true,
                    'name'     => $this->getTraefikNetworkName()
                ]

            ]
        ], Yaml::DUMP_OBJECT_AS_MAP);
    }
}