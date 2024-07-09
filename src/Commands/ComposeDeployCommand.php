<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Docker;
use Braceyourself\Compose\Facades\Compose;
use Illuminate\Contracts\Process\InvokedProcess;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\text;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\password;
use function Laravel\Prompts\textarea;

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

        $this->getLoadServerCredentials();

        spin(fn() => Process::run("ssh $this->user@$this->host 'mkdir -p $this->path'")->throw(),
            "Logging in to server..."
        );

        if ($this->option('down')) {
            return spin(fn() => Process::run("ssh {$this->user}@{$this->host} docker-compose -f {$this->path}/docker-compose.yml down -t0")->throw(),
                "Stopping services on {$this->host}"
            );
        }

        $this->updateOrCreateEnv();

        $this->createDockerfile();

        $this->createAppTarball();

        spin(fn() => $this->copyToServer($this->build_path, $this->path),
            'Copying app to remote server'
        );

        spin(fn() => $this->createRemoteComposeFile(),
            'Setting up docker-compose.yml'
        );


        $args = str(collect($this->getRemoteEnv()->explode("\n"))->filter()->map(fn($value) => "--build-arg='{$value}'")->join(' '))->trim(' ');

        $this->runRemoteComposeCommand("build {$args}");

        $this->setUpStorage();

        $this->runRemoteComposeCommand("up -d -t0 --remove-orphans --force-recreate");

        $this->ensureAppKeyIsSet();

        $this->runArtisanCommand("artisan optimize");

        $this->info('Deployed in ' . now()->longAbsoluteDiffForHumans($start));
    }

    private function createAppTarball()
    {
        $app_path = base_path();
        $tarball = "{$this->build_path}/app.tar";

        Process::run(<<<BASH
        tar -cf {$tarball} \
            --exclude-vcs \
            --exclude-from='{$this->build_path}/.dockerignore' \
            --exclude-from={$app_path}/.dockerignore \
            -C {$app_path} .
        BASH
        )->throw();

        return $tarball;
    }

    private function shouldCreateRemoteEnvFile()
    {
        $exists = (bool)trim(
            Process::run("ssh -q {$this->user}@{$this->host} [[ -f {$this->path}/.env ]] && echo 1 || echo 0")
                ->throw()
                ->output()
        );

        return !$exists;
    }

    private function createRemoteEnv()
    {
        $remote_ids = str(Process::run("ssh {$this->user}@{$this->host} 'id -u && id -g'")->throw()->output())->explode("\n");

        info("Creating .env file on remote server");
        $res = textarea("Review & confirm the deployment .env",
            default: str(file_get_contents('.env.example'))
                ->replaceMatches('/APP_NAME=.*/', 'APP_NAME=' . str(text("APP_NAME", default: config('app.name')))->wrap('"'))
                ->replaceMatches('/APP_ENV=.*/', 'APP_ENV=production')
                ->replaceMatches('/APP_DEBUG=.*/', 'APP_DEBUG=false')
                ->replaceMatches('/APP_URL=.*/', 'APP_URL=' . $url = text('APP_URL', default: "https://"))
                ->replaceMatches('/DB_CONNECTION=.*/', 'DB_CONNECTION=' . $db_conn = select('DB_CONNECTION', [
                        'mysql',
                        'pgsql',
                        'sqlite',
                    ])
                )
                ->replaceMatches('/(#.)?DB_HOST=.*/', 'DB_HOST=' . text('DB_HOST', default: 'mysql', hint: "The server address where your database is hosted."))
                ->when(in_array($db_conn, ['mysql', 'pgsql']), function ($c) use ($db_conn) {
                    return $c->replaceMatches(
                        '/(#.)?DB_PORT=.*/', 'DB_PORT=' . text('DB_PORT',
                            default: match ($db_conn) {
                                'mysql' => 3306,
                                'pgsql' => 5432,
                            })
                    );
                })
                ->replaceMatches('/(#.)?DB_DATABASE=.*/', 'DB_DATABASE=' . text('DB_DATABASE', default: pathinfo(base_path(), PATHINFO_FILENAME), hint: "Your database name"))
                ->replaceMatches('/(#.)?DB_USERNAME=.*/', 'DB_USERNAME=' . text('DB_USERNAME', default: 'admin'))
                ->replaceMatches('/(#.)?DB_PASSWORD=.*/', 'DB_PASSWORD=' . password('DB_PASSWORD'))
                ->replaceMatches('/(#.)?REDIS_HOST=.*/', 'REDIS_HOST=redis')
                ->replaceMatches('/(#.)?QUEUE_CONNECTION=.*/', 'QUEUE_CONNECTION=redis')
                ->explode("\n")
                ->push("COMPOSE_PROFILES=production")
                ->push("COMPOSE_PHP_IMAGE={$this->getPhpImageName()}")
                ->push("COMPOSE_DOMAIN=" . $domain = text("Confirm the domain name", default: str($url)->after('://')->before('/')->value(), hint: "This is the domain name where your app is hosted"))
                ->push("COMPOSE_ROUTER=" . str($domain)->slug())
                ->push("USER_ID={$remote_ids->first()}")
                ->push("GROUP_ID={$remote_ids->last()}")
                ->join("\n"),
            rows   : 20
        );

        Process::run("echo '{$res}' | ssh {$this->user}@{$this->host} 'cat > {$this->path}/.env'")->throw();
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

        info("Updating .env file on remote server");

        $res = $diff->map(function ($line) {
            $line = str($line);
            return str(
                text($line->before('='), default: $line->after('='))
            )->prepend($line->before('=') . '=')->value();
        })->join("\n");


        Process::run("echo '{$res}' | ssh {$this->user}@{$this->host} 'cat >> {$this->path}/.env'")->throw();
    }

    private function getRemoteEnv($name = null): Stringable
    {
        $env = Cache::store('array')->rememberForever(
            'compose-remote-env' . $this->user . $this->host . $this->path,
            fn() => str(Process::run("ssh -q {$this->user}@{$this->host} 'cat {$this->path}/.env'")->throw()->output())
        );

        if ($name) {
            return $env->explode("\n")
                ->mapInto(Stringable::class)
                ->filter(fn($line) => $line->startsWith($name))
                ->first();
        }

        return $env;
    }

    private function createRemoteComposeFile()
    {
        file_put_contents('/tmp/docker-compose.yml', $this->getComposeYaml('production'));
        $this->copyToServer('/tmp/docker-compose.yml', $this->path);
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
        
        BASH);
    }

    private function runRemoteScript(string $script)
    {
        Process::run("ssh -t {$this->user}@{$this->host} '{$script}'")->throw();
    }

    private function copyToServer(string $build_path, mixed $path)
    {
        Process::run("scp -r {$build_path} {$this->user}@{$this->host}:{$path}/")->throw();
    }

    private function runRemoteComposeCommand(string $command)
    {
        Process::tty()
            ->forever()
            ->run("ssh -t {$this->user}@{$this->host} 'docker-compose -f {$this->path}/docker-compose.yml {$command}'")
            ->throw();
    }

    private function ensureAppKeyIsSet()
    {
        // set the app key if not set
        if ($this->getRemoteEnv('APP_KEY')->after('=')->isEmpty()) {
            $this->info("Setting APP_KEY on remote server");
            $this->runArtisanCommand("artisan key:generate");
        }
    }

    private function updateOrCreateEnv()
    {
        // create the remote .env file
        if ($this->shouldCreateRemoteEnvFile()) {
            $this->createRemoteEnv();
        } else if ($this->shouldUpdateRemoteEnvFile()) {
            $this->updateRemoteEnv();
        }
    }

    private function getLoadServerCredentials()
    {
        $this->host = $this->getOrSetConfig('compose.deploy.host', fn() => $this->setEnv('COMPOSE_DEPLOY_HOST', text('What is the hostname of the deployment server?')));
        $this->user = $this->getOrSetConfig('compose.deploy.user', fn() => $this->setEnv('COMPOSE_DEPLOY_USER', text("What user will you use to login to {$this->host}", default: exec('whoami'))));
        $this->path = $this->getOrSetConfig('compose.deploy.path', fn() => $this->setEnv('COMPOSE_DEPLOY_PATH', text("Enter the path on {$this->host} this app should")));
        //$password = $this->getOrSetConfig('compose.deploy.password', fn() => $this->setEnv('COMPOSE_DEPLOY_PASSWORD', password("Enter the password for $user@$host")));
    }

    private function runArtisanCommand(string $command)
    {
        $this->runRemoteScript("docker-compose -f {$this->path}/docker-compose.yml exec -T php php {$command}");
    }
}
