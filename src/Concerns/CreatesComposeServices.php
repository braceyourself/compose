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

    public function getServiceDefinition($config, string $service_name)
    {
        if ($config === false) {
            return [];
        }

        if ($config === true) {
            $config = [];
        }

        return [$service_name => match ($service_name) {
            'php' => $this->buildPhpService($config),
            'nginx' => $this->buildNginxService($config),
            'npm' => $this->buildNpmService($config),
            'mysql', 'database' => $this->buildDatabaseService($config),
            'scheduler' => $this->buildSchedulerService($config),
            'redis' => $this->buildRedisService($config),
            'mailhog' => $this->buildMailhogService($config),
            'horizon' => $this->buildHorizonService($config),
        }];
    }

    private function buildPhpService(array $config): array
    {
        return collect([
            'image'       => $this->getPhpImageName(),
            'user'        => "{$this->getUserId()}:{$this->getGroupId()}",
            'volumes'     => $this->getPhpVolumes(),
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


    private function buildNginxService($config): array
    {
        return collect([
            'image'          => 'nginx',
            'container_name' => $this->getDomainName(),
            'build'          => [
                'target' => 'nginx'
            ],
            'restart'        => 'always',
            'environment'    => [
                'PROXY_PASS'      => 'php',
                'PROXY_PASS_PORT' => '9000',
            ],
            'volumes'        => [
                __DIR__ . '/../../build/nginx.conf:/etc/nginx/templates/default.conf.template',
            ],
            'depends_on'     => ['php'],
            'labels'         => [
                "traefik.http.routers.{$this->getTraefikRouterName()}.tls" => app()->environment('production'),
            ],
            'networks'       => ['default', 'traefik'],
        ])->merge($config)->toArray();
    }

    private function buildNpmService($config): array
    {
        if (file_exists(base_path('vite.config.js'))) {
            $vite_config_content = str(file_get_contents(base_path('vite.config.js')));
            // match contents of defineConfig({ ... })
            if (!$vite_config_content->contains('server:')) {
                warning("It looks like you're using Vite, but you haven't defined server settings in your vite.config.js file.");
                if (confirm("Would you like to add server settings to your vite.config.js file?")) {
                    $vite_config_content = $vite_config_content->replace(
                        'defineConfig({',
                        <<<EOF
                        defineConfig({
                            server: { 
                                hmr: 'hmr.{$this->getDomainName()}', 
                                port: 80 
                            },
                        EOF
                    );

                    file_put_contents(base_path('vite.config.js'), $vite_config_content);

                    info("HMR Server settings have been added to your vite.config.js file.");
                    warning("Be sure to review the settings to ensure they are correct.");
                }
            }
        }

        $image = data_get($config, 'image', 'node');

        // ensure node_modules is installed
        if (!file_exists(base_path('node_modules'))) {
            spin(function () use ($image) {
                Process::tty()
                    ->run("docker run --rm -u {$this->getUserId()} -v $(pwd):/var/www/html -w /var/www/html {$image} npm install")
                    ->throw();
            }, "Installing node_modules...");
        }

        return collect([
            'image'          => $image,
            'container_name' => "hmr.{$this->getDomainName()}",
            'user'           => "{$this->getUserId()}:{$this->getGroupId()}",
            'working_dir'    => '/var/www/html',
            'command'        => 'npm run dev -- --host --port=80',
            'labels'         => [
                "traefik.http.services.{$this->getTraefikRouterName()}.loadbalancer.server.port" => 80,
            ],
            'env_file'       => ['.env'],
            'volumes'        => ['./:/var/www/html'],
            'depends_on'     => ['php'],
            'networks'       => ['default', 'traefik'],
            ...(fn() => app()->environment('local') ? [] : ['profiles' => ['do-not-run']])()
        ])->merge($config)->toArray();
    }

    private function buildDatabaseService($config): array
    {
        $env = str(file_get_contents('.env'));
        if($env->contains(['# DB_', '#DB_']) && confirm("There are commented DB_ values in your .env. Would you like to update them?")){
            // loop over the env DB_* values
            $env->explode("\n")->each(function ($line) {
                $line = str($line);
                if ($line->startsWith('#') && $line->contains('DB_')) {
                    $key = $line->before('=')->ltrim('# ');
                    $value = $line->after('=');

                    $this->setEnv($key, text($key, default: $value), force: true);

                    // uncomment
                    Process::run("sed -i '/{$key}/s/^{$line->before('DB_')}//' .env");
                }
            });
        }

        Artisan::call('config:clear');

        if (($db_default = config('database.default')) != 'mysql') {
            return [];
        }

        return collect([
            'image'       => 'mysql',
            'restart'     => 'always',
            'environment' => [
                'MYSQL_ROOT_PASSWORD' => '${DB_PASSWORD}',
                'MYSQL_DATABASE'      => '${DB_DATABASE}',
                'MYSQL_USER'          => '${DB_USERNAME}',
                'MYSQL_PASSWORD'      => '${DB_PASSWORD}',
            ],
            'volumes'     => [
                './database/.data:/var/lib/mysql',
            ],
        ])->merge($config)->toArray();
    }

    private function buildSchedulerService($config): array
    {
        return collect([
            'image'       => $this->getPhpImageName(),
            'restart'     => 'always',
            'volumes'     => $this->getPhpVolumes(),
            'depends_on'  => ['php'],
//            'profiles'   => ['production']
            'environment' => [
                'SERVICE' => 'scheduler'
            ]
        ])->merge($config)->toArray();

    }

    private function buildRedisService($config): array
    {
        return collect([
            'image'    => 'redis:alpine',
            'restart'  => 'always',
            'profiles' => ['production']
        ])->merge($config)->toArray();
    }

    private function buildMailhogService($config): array
    {
        return collect([
            'image'          => 'mailhog/mailhog',
            'container_name' => "mailhog.{$this->getDomainName()}",
            'restart'        => 'always',
            'networks'       => ['default', 'traefik'],
            'profiles'       => ['local'],
            'labels'         => ['traefik.http.services.mailhog-ethanbrace.loadbalancer.server.port=8025'],
        ])->merge($config)->toArray();
    }

    private function buildHorizonService($config): array
    {
        return collect([
            'image'       => $this->getPhpImageName(),
            'restart'     => 'always',
            'volumes'     => ['./:/var/www/html'],
            'depends_on'  => ['php'],
            'command'     => 'php artisan horizon',
            'profiles'    => ['production'],
            'environment' => [
                'SERVICE' => 'horizon'
            ]
        ])->merge($config)->toArray();
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
        return Http::get('https://www.php.net/releases/active')
            ->collect()
            ->map(fn($list, $version) => collect($list)->keys())
            ->flatten()
            ->mapWithKeys(fn($version) => [$version => $version])
            ->sortDesc();
    }
}