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
            default => $this->buildCustomService($config, $service_name, $env),
        }];
    }

    public function getServices($env = 'local')
    {
        $config = $this->loadConfig($env);

        return collect(data_get($config, 'services'))
            ->mapWithKeys(fn($config, $service) => $this->getServiceDefinition($config, $service, $env))
            ->filter(fn($config) => $config !== null && $config !== false);
    }

    public function loadConfig($env = 'local')
    {
        putenv("APP_ENV={$env}");

        if(file_exists(config_path('compose.php'))){
            return eval(str_replace('<?php', '', file_get_contents(config_path('compose.php'))));
        }

        return config('compose');
    }

    public function getComposeYaml($env = 'local')
    {
        return Yaml::dump($this->getComposeConfig($env), Yaml::DUMP_OBJECT_AS_MAP);
    }

    public function getComposeConfig($env = 'local')
    {
        return [
            'services' => $this->getServices($env)->toArray(),
            'networks' => [
                'traefik' => [
                    'external' => true,
                    'name'     => '${COMPOSE_NETWORK}'
                ],
                ...config('compose.networks', [])

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
        return array_merge([
            'image'          => $this->getPhpImageName($env),
            'restart'        => 'always',
            'user'           => '${USER_ID}:${GROUP_ID}',
            'env_file'       => ['.env'],
        ], $config);
    }
}