<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Remote;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use Braceyourself\Compose\Concerns\InteractsWithRemoteServer;
use function Laravel\Prompts\text;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\error;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\password;

class ComposeDeployCommand extends Command
{
    use CreatesComposeServices;
    use InteractsWithRemoteServer;

    protected $signature = 'compose:deploy {--down} {--logs} {--host=} {--path=} {--user=}';
    protected $description = 'Deploy the services';

    private string $build_path = __DIR__ . '/../../build';
    private mixed $user;
    private mixed $host;
    private mixed $path;
    private string $repo_url = 'git@github.com:braceyourself/ethanbrace.com';
    private $docker_compose;

    public function handle()
    {
        $start = now();

        try {
            $this->setRepoUrl();

            $this->loadServerCredentials();

            $this->loginToRemoteServer();

            if ($this->option('down')) {
                $this->stopApp();
                return;
            }

            $this->tryPushingLocalImages();

            $this->checkRemoteDockerInstallation();

            $this->getDockerComposeExecutablePath();

            $this->checkRemoveEnv();

            $this->setupAppforDeploy();

            $this->setupAppOnServer();

            $this->buildAppOnRemote();

            $this->setUpStorage();

            $this->ensureTraefikIsSetup();

            $this->startServices();

            $this->waitForDatabaseContainer();

            $this->runMigrations();

            $this->generateAppKey();

            $this->optimizeApp();

            $this->info('Deployed in ' . now()->longAbsoluteDiffForHumans($start));
            $this->info("Your app is now live at " . $this->getRemoteEnv('APP_URL')->after('='));

        } finally {
            $this->cleanUpDeploy();
        }
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
        $this->info("Creating .env file on remote server");

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

    private function shouldUpdateRemoteEnvFile(): bool
    {
        if ($this->envExampleDiffFromRemote()->isNotEmpty()) {
            $this->warn('There are differences between the .env.example and the remote .env file.');

            return confirm("Would you like to update the remote .env file?");
        }

        return false;
    }


    private function updateRemoteEnv(): void
    {
        $this->info("Updating .env file on remote server");

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


    private function setUpStorage()
    {
        spin(function () {

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
            
            BASH);

        }, 'Setting up storage...');
    }

    private function runRemoteComposeCommand(string $command)
    {
        return Remote::forever()
            ->addOption('-t')
            ->run("{$this->docker_compose} {$command}");
    }

    private function runRemoteScript(string $script, $tty = false, $timeout = 120)
    {
        return Remote::timeout($timeout)->run($script);
    }

    private function copyToServer(string $local_path, mixed $path, $spinner = false): void
    {
        $path = str($path)->rtrim('/');

        $options = collect([])
            ->when(is_dir($local_path), fn($c) => $c->push('-r'))
            ->join(' ');

        Process::timeout(120)
            ->run("scp {$options} {$local_path} {$this->user}@{$this->host}:{$path}")
            ->throw();
    }

    private function ensureAppKeyIsSet(): void
    {
        // set the app key if not set
        if ($this->getRemoteEnv('APP_KEY')->isEmpty()) {
            $this->runArtisanCommand("key:generate");
        }
    }


    private function runArtisanCommand(string $command): void
    {
        $this->runRemoteScript("{$this->docker_compose} run --entrypoint=bash --rm php -c './artisan {$command}'", tty: true)->throw();
    }

    private function extractAppTarball(): void
    {
        $this->runRemoteScript("cd build && tar -xf app.tar")->throw();
    }

    private function cleanUpDeploy(): void
    {
        $this->runRemoteScript("rm -rf {$this->path}/app")->throw();
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
            ->replaceMatches('/(#.)?DB_HOST=.*/', 'DB_HOST=database')
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
            ->push("COMPOSE_NETWORK=traefik_default")
            ->push("USER_ID={$remote_ids->first()}")
            ->push("GROUP_ID={$remote_ids->last()}")
            ->join("\n");
    }

    private function getRemoteContainers($service)
    {
        return str(
            Remote::run("{$this->docker_compose} ps --format json {$service}")
                ->throw()
                ->output()
        )
            ->explode("\n")
            ->filter()
            ->map(fn($l) => json_decode($l))
            ->sortBy('CreatedAt');
    }

    private function scaleService(string $service, int $replicas): void
    {
        Remote::run("{$this->docker_compose} up -d --no-deps --scale {$service}={$replicas} --no-build --no-recreate {$service}")->throw();
        do {
            sleep(2);
        } while ($this->getRemoteContainers($service)->every('Health', 'healthy'));
        Remote::run("{$this->docker_compose} exec nginx /usr/sbin/nginx -s reload")->throw();
    }

    private function ensureTraefikIsSetup()
    {
        spin(function(){
            $traefik_dir = "{$this->path}/traefik";
            $compose_network = $this->runRemoteScript("source {$this->path}/.env && echo \$COMPOSE_NETWORK")->output();

            // create ~/treafik dir
            $this->runRemoteScript("mkdir -p {$traefik_dir}")->throw();

            // ensure traefik network exists
            str($this->runRemoteScript("docker network ls --format '{{.Name}}'")->output())
                ->when(fn(Stringable $s) => !$s->contains($compose_network), function () {
                    $this->runRemoteScript("source {$this->path}/.env && docker network create \$COMPOSE_NETWORK", tty: true);
                });

            // ensure treafik is running
            str($this->runRemoteScript("docker ps --format '{{.Image}}'")->throw()->output())
                ->explode("\n")
                ->filter(fn($container) => str($container)->contains('traefik'))
                ->whenEmpty(function () use ($traefik_dir) {

                    // copy file to that dir
                    $this->copyToServer(__DIR__ . '/../../traefik/docker-compose.yml', "{$traefik_dir}/docker-compose.yml");

                    $this->runRemoteScript("cd $traefik_dir && {$this->docker_compose} up -d")
                        ->throw()
                        ->output();

                });

        }, 'Setting up Traefik...');
    }

    private function cloneRepoToServer()
    {
        $path = str($this->path)->trim()->value();

        $this->runRemoteScript("rm -rf {$path}/app")->throw();
        $this->runRemoteScript(str("git clone {$this->repo_url} app")->replace("\n", " ")->value(), 300)->throw();
    }

    private function copyLocalAppToServer()
    {
        $this->createAppTarball();

        // package the app and copy to the server
        $this->copyToServer("{$this->build_path}/app.tar", "{$this->path}/app.tar");

        // extract
        $this->runRemoteScript("rm -rf {$this->path}/app")->throw();
        $this->runRemoteScript("mkdir app && tar -xf app.tar -C app")->throw();
    }

    private function hasRepoUrl()
    {
        return isset($this->repo_url);
    }

    private function loginToRemoteServer()
    {
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

            throw $th;
        }
    }

    private function stopApp()
    {
        spin(fn() => $this->runRemoteComposeCommand("down -t0")->throw(),
            "Stopping services on {$this->host}..."
        );

        $this->info("Services stopped on {$this->host}");
    }

    private function tryPushingLocalImages()
    {
        try {
            spin(function () {

                $local_compose = $this->getLocalComposeBinary();

                Process::run("$local_compose push php");
                Process::run("$local_compose push nginx");

            }, 'Pushing local docker-compose images...');
        } catch (\Throwable $e) {
        }
    }

    public function getLocalComposeBinary()
    {
        try {
            Process::run("docker-compose --version")->throw();
        } catch (\Throwable $th) {
            return "docker compose";
        }

        return "docker-compose";
    }

    private function checkRemoteDockerInstallation()
    {
        try {
            spin(function () {
                $this->runRemoteScript("docker --version")->throw();
            }, 'Checking remote docker installation...');
        } catch (\Throwable $th) {
            $this->error("Docker is not installed on the remote server. Please install docker and try again.");
            return;
        }
    }

    private function getDockerComposeExecutablePath()
    {
        // get compose command to use
        try {
            $this->runRemoteScript("docker-compose --version")->throw();
            $this->docker_compose = "docker-compose";
        } catch (\Throwable $th) {
            $this->docker_compose = "docker compose";
        }
    }

    private function checkRemoveEnv()
    {
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
    }

    private function setupAppforDeploy()
    {
        spin(function () {
            $this->createDockerfile();
        }, 'Packaging app for deployment...');
    }

    private function setupAppOnServer()
    {
        spin(function () {

            $this->hasRepoUrl()
                ? $this->cloneRepoToServer()
                : $this->copyLocalAppToServer();

        }, 'Setting up app on remote server...');

        spin(function () {
            if(file_exists("{$this->build_path}/app.tar")){
                // remove tarball from build path
                unlink("{$this->build_path}/app.tar");
            }

            // overwrite app/build with compose build
            $this->copyToServer($this->build_path, "{$this->path}/app");

            // create docker-compose file
            file_put_contents('/tmp/docker-compose.yml', $this->getComposeYaml('production'));
            $this->copyToServer('/tmp/docker-compose.yml', $this->path);

        }, 'Copying build files to server...');
    }

    private function buildAppOnRemote()
    {
        spin(function () {
            $vite_args = $this->getViteBuildArgStringForDockerCommand();

            try {
                $this->runRemoteComposeCommand("pull php");
            } catch (\Throwable $e) {
            }

            try {
                $this->runRemoteComposeCommand("pull nginx");
            } catch (\Throwable $e) {
            }

            $this->runRemoteComposeCommand("build {$vite_args} php")->throw();

            if (config('compose.services.nginx') !== false) {
                $this->runRemoteComposeCommand("build {$vite_args} nginx")->throw();
            }
        }, 'Building images...');
    }

    private function startServices()
    {
        spin(function () {

            $running_services = str(Remote::run("{$this->docker_compose} config --services")->output())->explode("\n")
                ->filter(fn($s) => !in_array($s, ['php', 'nginx', 'database', 'redis']))->filter()->join(' ');

            // restart everything except php
            Remote::run("{$this->docker_compose} up -d {$running_services} --force-recreate --remove-orphans -t0")->throw();

            $deploy_type = config('compose.services.php.container_name') ? 'restart' : 'rolling';

            $this->runRemoteScript("chmod +x {$this->path}/app/build/deploy.sh && {$this->path}/app/build/deploy.sh {$deploy_type}")->throw();

        }, 'Starting services...');
    }

    private function waitForDatabaseContainer()
    {
        if (config('compose.services.database')) {
            spin(function () {
                // wait until database is healthy
                while (true) {
                    $database = json_decode(Remote::run("{$this->docker_compose} ps --format json database")->throw()->output());

                    if ($database->Health == 'healthy') {
                        break;
                    }

                    sleep(1);
                }
            }, 'Waiting for database...');
        }
    }

    private function runMigrations()
    {
        spin(function () {
            $this->runArtisanCommand("migrate --force");
        }, 'Running migrations...');
    }

    private function generateAppKey()
    {
        spin(function () {
            $this->runArtisanCommand("key:generate");
        }, 'Generating app key...');
    }

    private function optimizeApp()
    {
        spin(function () {
            $this->runArtisanCommand("optimize");
        }, 'Optimizing...');
    }

    private function setRepoUrl()
    {
        if (file_exists(base_path('.git'))) {
            $this->repo_url = Process::run("git remote get-url origin")->output();
        }
    }
}
