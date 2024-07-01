<?php

namespace Braceyourself\Compose\Concerns;

use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;

trait ComposeServices
{
    use ConfiguresTraefik;

    public function getServiceDefinition(array $config, string $service_name)
    {
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
        ])->merge($config)->toArray();
    }


    private function buildNginxService($config): array
    {
        return collect([
            'container_name' => $this->getDomainName(),
            'image'          => 'nginx',
            'build'          => [
                'target' => 'nginx'
            ],
            'restart'        => 'always',
            'environment'    => [
                'PROXY_PASS'      => 'php',
                'PROXY_PASS_PORT' => '9000',
            ],
            'volumes'        => [
                __DIR__.'/../../build/nginx.conf:/etc/nginx/templates/default.conf.template',
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
        return collect([
            'image'          => 'node',
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

    private function getDomainName()
    {
        return \Cache::store('array')->rememberForever('compose-' . __FUNCTION__, function () {
            $env_name = 'COMPOSE_DOMAIN';
            $value = config('compose.domain');

            if (empty($value)) {
                $value = text("What domain name would you like to use?", hint: "example.com");
                $this->storeEnv($env_name, $value);
            }


            return $value;
        });
    }

    private function storeEnv($key, $value)
    {
        $this->warn("{$key}={$value}");

        if (confirm("Would you like to add this to your .env file?")) {
            if (str(file_get_contents('.env'))->contains("{$key}="))
                Process::run("sed -i 's/{$key}=.*/$key={$value}/' .env");
            else {
                Process::run("echo '\n{$key}={$value}' >> .env");
            }
        }
    }

    private function getGroupId()
    {
        return \Cache::store('array')->rememberForever('compose-' . __FUNCTION__, function () {
            $value = config('compose.group_id');
            $value ??= str(Process::run('id -g')->throw()->output())->trim()->value();

            return $value;
        });
    }

    private function getUserId()
    {
        return \Cache::store('array')->rememberForever('compose-' . __FUNCTION__, function () {
            $value = config('compose.user_id');
            $value ??= str(Process::run('id -u')->throw()->output())->trim()->value();

            return $value;
        });
    }

    private function getPhpImageName()
    {
        return \Cache::store('array')->rememberForever('compose-php-image-name', function () {
            $image = config('compose.services.php.image');

            if (empty($image)) {
                $app_dir = str(base_path())->basename()->slug();
                $php_version = $this->getPhpVersion();
                $image = "$app_dir:php-{$php_version}";

                $this->setEnv('COMPOSE_PHP_IMAGE', $image);
            }

            return $image;
        });
    }

    private function getPhpVersion()
    {
        return Cache::store('array')->rememberForever('compose-' . __FUNCTION__, function () {
            return config('compose.php') ?: tap(select("Select PHP Version:", $this->getPhpVersions()), function ($version) {
                $this->setEnv('COMPOSE_PHP_VERSION', $version);
            });
        });
    }

    private function setEnv($key, $value)
    {
        $this->warn("{$key}={$value}");

        if (confirm("Would you like to add this to your .env file?")) {
            if (str(file_get_contents('.env'))->contains("{$key}="))
                Process::run("sed -i 's/{$key}=.*/$key={$value}/' .env");
            else {
                Process::run("echo '\n{$key}={$value}' >> .env");
            }
        }
    }

    private function getComposeConfig()
    {
        Cache::store('array')->clear();

        return Yaml::dump([
            'services' => collect(config('compose.services'))
                ->mapWithKeys($this->getServiceDefinition(...))
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
            ...(fn(): array => app()->environment('local') ? [
                // local volumes
                './:/var/www/html',
            ] : [
                // production volumes
                './.env:/var/www/html/.env'
            ])(),
            // always
            '$HOME/.config/psysh:/var/www/.config/psysh',
        ];
    }

    private function getTraefikRouterName()
    {
        return str(base_path())->basename()->slug();
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

    private function ensureDockerIsInstalled()
    {
        try {
            Process::run('docker --version')->throw();
        } catch (\Throwable $th) {

            if (!confirm("Docker needs to be installed on this system. Would you like to install it now?")) {
                return;
            }

            // install docker
            Process::run('curl -fsSL https://get.docker.com -o /tmp/get-docker.sh')->throw();
            Process::run('sh /tmp/get-docker.sh')->throw();
        }
    }

    private function getDockerfile()
    {
        return <<<DOCKERFILE
        FROM php:{$this->getPhpVersion()}-fpm
        RUN apt-get update && apt-get install -y git
        
        USER root
        ENV PATH="/var/www/.composer/vendor/bin:\$PATH"
        ENV PHP_MEMORY_LIMIT=512M
        WORKDIR /var/www/html
        ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin
        
        RUN apt-get update \
            && apt install -y {$this->getServerPackages()} \
            && rm -rf /var/lib/apt \
            && chmod +x /usr/local/bin/install-php-extensions && sync
            
        RUN install-php-extensions gd bcmath mbstring opcache xsl imap zip ssh2 yaml pcntl intl sockets exif redis pdo_mysql pdo_pgsql sqlsrv pdo_sqlsrv soap @composer \
            && groupmod -og {$this->getGroupId()} www-data \
            && usermod -u {$this->getGroupId()} www-data
            
        # create entrypoint file
        COPY php_entrypoint.sh /usr/local/bin/entrypoint.sh
        RUN chmod +x /usr/local/bin/entrypoint.sh

        USER www-data
        
        ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
        
        DOCKERFILE;
    }

    private function getServerPackages()
    {
        return 'git ffmpeg jq iputils-ping poppler-utils wget';
    }

}