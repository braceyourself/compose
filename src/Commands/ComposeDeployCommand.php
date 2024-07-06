<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
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

    protected $signature = 'compose:deploy';
    protected $description = 'Deploy the services';

    public function handle()
    {
        $host = $this->getOrSetConfig('compose.deploy.host', fn() => $this->setEnv('COMPOSE_DEPLOY_HOST', text('What is the hostname of the deployment server?')));
        $user = $this->getOrSetConfig('compose.deploy.user', fn() => $this->setEnv('COMPOSE_DEPLOY_USER', text("What user will you use to login to {$host}", default: exec('whoami'))));
        $path = $this->getOrSetConfig('compose.deploy.path', fn() => $this->setEnv('COMPOSE_DEPLOY_PATH', text("Enter the path on {$host} this app should")));
        //$password = $this->getOrSetConfig('compose.deploy.password', fn() => $this->setEnv('COMPOSE_DEPLOY_PASSWORD', password("Enter the password for $user@$host")));

        $this->call('compose:build', ['--push' => true]);

        // ensure the host path exists; also that we can login
        Process::run("ssh $user@$host 'mkdir -p $path'")->throw();

//        ssh -q $HOST [[ -f $FILE_PATH ]] && echo "File exists" || echo "File does not exist";
        if ($this->shouldCreateRemoteEnvFile($user, $host, $path)) {

            info("Creating .env file on remote server");
            $res = textarea("Review & confirm the deployment .env",
                default: str(file_get_contents('.env.example'))
                    ->replaceMatches('/APP_NAME=.*/', 'APP_NAME=' . str(text("APP_NAME", default: config('app.name')))->wrap('"'))
                    ->replaceMatches('/APP_ENV=.*/', 'APP_ENV=production')
                    ->replaceMatches('/APP_DEBUG=.*/', 'APP_DEBUG=false')
                    ->replaceMatches('/APP_URL=.*/', 'APP_URL=' . text('APP_URL', default: "https://"))
                    ->replaceMatches('/DB_CONNECTION=.*/', 'DB_CONNECTION=mysql')
                    ->replaceMatches('/(#.)?DB_HOST=.*/', 'DB_HOST=mysql')
                    ->replaceMatches('/(#.)?DB_PORT=.*/', 'DB_PORT=3306')
                    ->replaceMatches('/(#.)?DB_DATABASE=.*/', 'DB_DATABASE=' . text('DB_DATABASE', default: pathinfo(base_path(), PATHINFO_FILENAME), hint: "Your database name"))
                    ->replaceMatches('/(#.)?DB_USERNAME=.*/', 'DB_USERNAME=' . text('DB_USERNAME', default: 'admin'))
                    ->replaceMatches('/(#.)?DB_PASSWORD=.*/', 'DB_PASSWORD=' . password('DB_PASSWORD'))
                    ->explode("\n")
                    ->push("COMPOSE_PROFILES=production")
                    ->join("\n"),
                rows   : 20
            );

            Process::run("echo '{$res}' | ssh {$user}@{$host} 'cat > {$path}/.env'")->throw();

        }


        # create build/deploy directory
        $build_path = __DIR__ . '/../../build';
        $production_image = $this->getPhpImageName('production');

        $tarball = "{$build_path}/image.tar";
        $this->call('compose:build', [
            '--target' => 'production',
        ]);
        Process::run("docker build --target=production -t {$production_image} {$build_path}")->throw();
        Process::run("docker save {$production_image} -o {$tarball}")->throw();
        Process::run("scp {$tarball} {$user}@{$host}:{$path}/");
        Process::run("ssh {$user}@{$host} docker load -i {$path}/image.tar")->throw();


        Process::tty()->timeout(120)->run("ssh {$user}@{$host} '" . <<<BASH
        #!/bin/bash
        set -e
        mkdir -p {$path} && cd {$path}
        docker load -i image.tar
        BASH . "'")->throw();
    }

    private function createAppTarball()
    {
        $app_path = base_path();
        $build_path = __DIR__ . '/../../build';
        $tarball = "{$build_path}/app.tar";

        Process::run("tar -cf {$tarball} --exclude-vcs --exclude-from='$build_path/.dockerignore' -C $app_path .")->throw();

        return $tarball;
    }

    private function shouldCreateRemoteEnvFile($user, $host, $path)
    {
        $exists = (bool)trim(
            Process::run("ssh -q {$user}@{$host} [[ -f {$path}/.env ]] && echo 1 || echo 0")
                ->throw()
                ->output()
        );

        return !$exists;
    }
}
