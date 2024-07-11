<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Remote;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\text;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\error;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\password;

class ComposeDeployCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:deploy {--down}';
    protected $description = 'Deploy the services';

    private string $build_path = __DIR__ . '/../../build';
    private mixed $user;
    private mixed $host;
    private mixed $path;

    public function handle()
    {
        $start = now();

        $this->loadServerCredentials();

        try {
            spin(function () {
                Remote::connect($this->host, $this->user)
                    ->run("mkdir -p $this->path")
                    ->throw();
            },
                "Logging in to server..."
            );

            Remote::path($this->path);

        } catch (\Throwable $th) {
            error(str($th->getMessage())->afterLast('==='));
            warning("Could not login to server. Please check your credentials and try again.");
            return;
        }

        if ($this->option('down')) {
            spin(fn() => $this->runRemoteComposeCommand("down -t0"),
                "Stopping services on {$this->host}..."
            );

            $this->info("Services stopped on {$this->host}");
            return;
        }

        match (spin(function () {
            if (!Remote::fileExists(".env")) {
                return 'create';
            } else if ($this->shouldUpdateRemoteEnvFile()) {
                return 'update';
            }

            return null;
        }, 'Checking remote environment...')) {
            'create' => $this->createRemoteEnv(),
            'update' => $this->updateRemoteEnv(),
            default => null
        };

        spin(function () {
            $this->createDockerfile();
            $this->createAppTarball();
        }, 'Packaging app for deployment...');

        spin(fn() => $this->copyToServer($this->build_path, $this->path),
            'Copying app to remote server...'
        );

        spin($this->extractAppTarball(...),
            'Setting up app on remote server...'
        );

        spin(function () {
            $content = $this->getComposeYaml('production');
            file_put_contents('/tmp/docker-compose.yml', $content);
            $this->copyToServer('/tmp/docker-compose.yml', $this->path);
        }, 'Setting up docker compose...');

        spin(function () {
            $vite_args = str(collect($this->getRemoteEnv()->explode("\n"))
                ->filter(fn($line) => str($line)->startsWith('VITE_'))
                ->map(fn($value) => "--build-arg '{$value}'")->join(' '))->trim(' ');

            $this->runRemoteComposeCommand("build {$vite_args}");
        }, 'Building images...');

        spin($this->setUpStorage(...), 'Setting up storage...');

        spin(function () {
            $this->runRemoteComposeCommand("up -d -t0 --remove-orphans");
            $this->ensureAppKeyIsSet();

            // scale up php
            Remote::run('docker-compose up -d --no-deps --scale php=2 --no-build --no-recreate php')->throw();

            do {
                sleep(2);
            } while ($this->getRemoteContainers('php')->last()->Health != 'healthy');

            // reload nginx
            Remote::run("docker-compose exec nginx /usr/sbin/nginx -s reload")->throw();

            // stop and remove old container
            $old_id = $this->getRemoteContainers('php')->first()->ID;
            Remote::run("docker stop {$old_id} && docker rm {$old_id}")->throw();

            // reload nginx
            Remote::run("docker-compose exec nginx /usr/sbin/nginx -s reload")->throw();
        }, 'Starting services...');

        spin(function () {
            // wait until database is healthy
            while (true) {
                $database = json_decode(Remote::run("docker-compose ps --format json database")->throw()->output());

                if ($database->Health == 'healthy') {
                    break;
                }

                sleep(1);
            }
        }, 'Waiting for database...');

        spin(function () {
            $this->runArtisanCommand("artisan migrate --force");
        }, 'Running migrations...');

        spin(function () {
            $this->runArtisanCommand("artisan optimize");
        }, 'Optimizing...');

        spin($this->cleanUpDeploy(...), 'Cleaning up...');

        $this->info('Deployed in ' . now()->longAbsoluteDiffForHumans($start));

        $this->info("Your app is now live at " . $this->getRemoteEnv('APP_URL')->after('='));
    }

    private function createAppTarball(): string
    {
        $app_path = base_path();
        $tarball = "{$this->build_path}/app.tar";

        if (file_exists(base_path('.git'))) {
            Process::run("git archive --format=tar --output={$tarball} HEAD")->throw();
        } else {
            $excludes = collect([
                "{$this->build_path}/.dockerignore",
                base_path('.dockerignore'),
            ])
                ->filter(fn($f) => file_exists($f))
                ->map(fn($f) => "--exclude-from={$f}")
                ->join(" ");

            Process::run("tar -cf {$tarball} {$excludes} -C {$app_path} .")->throw();
        }


        return $tarball;
    }

    private function createRemoteEnv(): void
    {
        $res = $this->buildEnvFromExampleFile();
        $temp_name = tempnam(sys_get_temp_dir(), 'env-') . '.env';

        file_put_contents($temp_name, $res);

        retry(3, function ($attempt) use ($temp_name) {
            if ($attempt > 1) {
                $this->runRemoteScript("rm -rf {$this->path}/.env");
            }

            return $this->copyToServer($temp_name, "{$this->path}/.env");
        });

        unlink($temp_name);
    }

    private function envExampleDiffFromRemote()
    {
        $remote_env = $this->getRemoteEnv();

        return str(file_get_contents('.env.example'))
            ->explode("\n")
            ->filter()
            ->filter(fn($line) => !$remote_env->contains(str($line)->trim("# ")->before('=')));
    }

    private function shouldUpdateRemoteEnvFile()
    {
        if ($this->envExampleDiffFromRemote()->isNotEmpty()) {
            $this->warn('There are differences between the .env.example and the remote .env file.');

            return confirm("Would you like to update the remote .env file?");
        }

        return false;
    }


    private function updateRemoteEnv()
    {
        $diff = $this->envExampleDiffFromRemote();

        $this->info("Updating .env file on remote server");

        $res = $diff->map(function ($line) {
            $line = str($line);
            return str(
                text($line->before('='), default: $line->after('='))
            )->prepend($line->before('=') . '=')->value();
        })->join("\n");


        Remote::appendToFile('.env', $res);
    }

    private function getRemoteEnv($name = null): Stringable
    {
        $env = Cache::store('array')->rememberForever(
            'compose-remote-env' . $this->user . $this->host . $this->path,
            fn() => str(
                Remote::run("cat .env")->throw()->output()
            )
        );

        if ($name) {
            return $env->explode("\n")
                ->mapInto(Stringable::class)
                ->filter(fn($line) => $line->startsWith($name))
                ->map(fn($line) => $line->after('='))
                ->first() ?? new Stringable();
        }

        return $env;
    }

    private function setUpStorage()
    {
        $this->runRemoteScript(<<<BASH
        cd {$this->path} 
        
        # ensure all app storage directories exist
        mkdir -p storage/framework/{sessions,views,cache}
        mkdir -p storage/app/public
        mkdir -p storage/logs
            
        // directories should have 755 permissions
        find storage -type d -exec chmod 755 -- {} +
        
        // files should have 644 permissions
        find storage -type f -exec chmod 644 -- {} +
        
        BASH
        );
    }

    private function runRemoteScript(string $script)
    {
        return Remote::run($script);
    }

    private function copyToServer(string $local_path, mixed $path): void
    {
        $path = str($path)->trim('/');

        $options = collect([])
            ->when(is_dir($local_path), fn($c) => $c->push('-r'))
            ->join(' ');

        Process::run("scp {$options} {$local_path} {$this->user}@{$this->host}:{$path}")->throw();
    }

    private function runRemoteComposeCommand(string $command)
    {
        Remote::forever()
            ->addOption('-t')
            ->run("docker-compose {$command}")
            ->throw();
    }

    private function ensureAppKeyIsSet(): void
    {
        // set the app key if not set
        if ($this->getRemoteEnv('APP_KEY')->isEmpty()) {
            $this->runArtisanCommand("artisan key:generate");
        }
    }

    private function loadServerCredentials(): void
    {
        $prompt = function ($text, $default = '', $hint = null) {
            return text($text,
                default: $default,
                hint   : $hint ?? 'Publish and set the compose configuration to avoid this prompt. (artisan vendor:publish --tag=compose-config)'
            );
        };

        $this->host = $this->getOrSetConfig('compose.deploy.host', fn() => $prompt('What is the hostname of the deployment server?'));
        $this->user = $this->getOrSetConfig('compose.deploy.user', fn() => $prompt("Enter the user name for '{$this->host}'", exec('whoami')));
        $this->path = $this->getOrSetConfig('compose.deploy.path', fn() => $prompt("Enter the path on {$this->host} this app should"));
    }

    private function runArtisanCommand(string $command): void
    {
        $this->runRemoteScript("docker-compose exec -T php php {$command}")->throw();
    }

    private function extractAppTarball(): void
    {
        $this->runRemoteScript("cd build && tar -xf app.tar")->throw();
    }

    private function cleanUpDeploy(): void
    {
        $this->runRemoteScript("rm -rf {$this->path}/build")->throw();
    }

    private function buildEnvFromExampleFile(): string
    {
        $remote_ids = str(Remote::run('id -u && id -g')->output())->explode("\n")->filter();

        return str(file_get_contents('.env.example'))
            ->replaceMatches('/APP_NAME=.*/', 'APP_NAME=' . str(text("APP_NAME", default: config('app.name')))->wrap('"'))
            ->replaceMatches('/APP_ENV=.*/', 'APP_ENV=production')
            ->replaceMatches('/APP_DEBUG=.*/', 'APP_DEBUG=false')
            ->replaceMatches('/APP_URL=.*/', 'APP_URL=' . $url = text('APP_URL', default: "https://"))
            ->replaceMatches('/DB_CONNECTION=.*/', 'DB_CONNECTION=mysql')
            ->replaceMatches('/(#.)?DB_HOST=.*/', 'DB_HOST=mysql')
            ->replaceMatches('/(#.)?DB_PORT=.*/', 'DB_PORT=3306')
            ->replaceMatches('/(#.)?DB_DATABASE=.*/', 'DB_DATABASE=' . pathinfo(base_path(), PATHINFO_FILENAME))
            ->replaceMatches('/(#.)?DB_USERNAME=.*/', 'DB_USERNAME=admin')
            ->replaceMatches('/(#.)?DB_PASSWORD=.*/', 'DB_PASSWORD=' . password('DB_PASSWORD', hint: "The password for the database user. This is the first time you're deploying the app, so you can set this to anything."))
            ->replaceMatches('/(#.)?REDIS_HOST=.*/', 'REDIS_HOST=redis')
            ->replaceMatches('/(#.)?QUEUE_CONNECTION=.*/', 'QUEUE_CONNECTION=redis')
            ->explode("\n")
            ->push("COMPOSE_PROFILES=production")
            ->push("COMPOSE_PHP_IMAGE={$this->getPhpImageName()}")
            ->push("COMPOSE_DOMAIN=" . $domain = text("Confirm the domain name", default: str($url)->after('://')->before('/')->value(), hint: "This is the domain name where your app is hosted"))
            ->push("COMPOSE_ROUTER=" . str($domain)->slug())
            ->push("USER_ID={$remote_ids->first()}")
            ->push("GROUP_ID={$remote_ids->last()}")
            ->join("\n");
    }

    private function getRemoteContainers($service)
    {
        return str(
            Remote::run("docker-compose ps --format json {$service}")
                ->throw()
                ->output()
        )
            ->explode("\n")
            ->filter()
            ->map(fn($l) => json_decode($l))
            ->sortBy('CreatedAt');
    }
}
