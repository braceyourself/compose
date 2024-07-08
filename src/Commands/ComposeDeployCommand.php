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
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\password;
use function Laravel\Prompts\textarea;

class ComposeDeployCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:deploy {--down}';
    protected $description = 'Deploy the services';


    # create build/deploy directory
    public string $build_path = __DIR__ . '/../../build';
    private mixed $user;
    private mixed $host;
    private mixed $path;

    public function handle()
    {
        $start = now();

        $this->host = $this->getOrSetConfig('compose.deploy.host', fn() => $this->setEnv('COMPOSE_DEPLOY_HOST', text('What is the hostname of the deployment server?')));
        $this->user = $this->getOrSetConfig('compose.deploy.user', fn() => $this->setEnv('COMPOSE_DEPLOY_USER', text("What user will you use to login to {$this->host}", default: exec('whoami'))));
        $this->path = $this->getOrSetConfig('compose.deploy.path', fn() => $this->setEnv('COMPOSE_DEPLOY_PATH', text("Enter the path on {$this->host} this app should")));
        //$password = $this->getOrSetConfig('compose.deploy.password', fn() => $this->setEnv('COMPOSE_DEPLOY_PASSWORD', password("Enter the password for $user@$host")));

        if ($this->option('down')) {
            spin(fn() => Process::run("ssh {$this->user}@{$this->host} docker-compose -f {$this->path}/docker-compose.yml down -t0")->throw(),
                "Stopping services on {$this->host}"
            );
            return;
        }


        // ensure the host path exists; also, check that we can login
        Process::run("ssh $this->user@$this->host 'mkdir -p $this->path'")->throw();

        // create the remote .env file
        if ($this->shouldCreateRemoteEnvFile()) {
            $this->createRemoteEnv();
        } else if ($this->shouldUpdateRemoteEnvFile()) {
            $this->updateRemoteEnv();
        }

        $this->createDockerfile();
        $this->createAppTarball();

        // copy the build directory to the server
        spin(fn() => Process::run("scp -r {$this->build_path} {$this->user}@{$this->host}:{$this->path}/")->throw(),
            'Copying app to remote server'
        );

        $this->createRemoteComposeFile();

        Process::tty()
            ->forever()
            ->run("ssh -t {$this->user}@{$this->host} 'docker-compose -f {$this->path}/docker-compose.yml up -d -t0 --remove-orphans --force-recreate'")
            ->throw();

        // set the app key if not set
        if (!$this->getRemoteEnv()->contains("APP_KEY=base64:")) {
            $this->info("Setting APP_KEY on remote server");
            Process::tty()->run("ssh -t {$this->user}@{$this->host} 'cd {$this->path} && docker-compose exec -T php php artisan key:generate'")->throw();
        }

        // optimize app
        Process::tty()->run("ssh -t {$this->user}@{$this->host} 'cd {$this->path} && docker-compose exec -T php php artisan optimize'")->throw();

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
        BASH)->throw();

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
                ->replaceMatches('/DB_CONNECTION=.*/', 'DB_CONNECTION=mysql')
                ->replaceMatches('/(#.)?DB_HOST=.*/', 'DB_HOST=mysql')
                ->replaceMatches('/(#.)?DB_PORT=.*/', 'DB_PORT=3306')
                ->replaceMatches('/(#.)?DB_DATABASE=.*/', 'DB_DATABASE=' . text('DB_DATABASE', default: pathinfo(base_path(), PATHINFO_FILENAME), hint: "Your database name"))
                ->replaceMatches('/(#.)?DB_USERNAME=.*/', 'DB_USERNAME=' . text('DB_USERNAME', default: 'admin'))
                ->replaceMatches('/(#.)?DB_PASSWORD=.*/', 'DB_PASSWORD=' . password('DB_PASSWORD'))
                ->replaceMatches('/(#.)?REDIS_HOST=.*/', 'REDIS_HOST=redis')
                ->replaceMatches('/(#.)?QUEUE_CONNECTION=.*/', 'QUEUE_CONNECTION=redis')
                ->explode("\n")
                ->push("COMPOSE_PROFILES=production")
                ->push("COMPOSE_PHP_IMAGE={$this->getPhpImageName()}")
                ->push("COMPOSE_DOMAIN=".text("Confirm the domain name", default: str($url)->after('://')->before('/')->value(), hint: "This is the domain name where your app is hosted"))
                ->push("COMPOSE_ROUTER=".str($url)->after('://')->before('/')->slug())
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
        return $this->envExampleDiffFromRemote()->isNotEmpty()
            && confirm("There are differences between the .env.example and the remote .env file. Would you like to update the remote .env file?");
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

    private function getRemoteEnv():Stringable
    {
        return Cache::store('array')->rememberForever(
            'compose-remote-env' . $this->user . $this->host . $this->path,
            fn() => str(Process::run("ssh -q {$this->user}@{$this->host} 'cat {$this->path}/.env'")->throw()->output())
        );
    }

    private function createRemoteComposeFile()
    {
        file_put_contents('/tmp/docker-compose.yml', $this->getComposeYaml('production'));
        spin(fn() => Process::run("scp -r /tmp/docker-compose.yml {$this->user}@{$this->host}:{$this->path}/")->throw(),
            'Copying compose file to remote server'
        );
    }
}
