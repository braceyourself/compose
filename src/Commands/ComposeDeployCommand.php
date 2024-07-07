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
        $start = now();
        $host = $this->getOrSetConfig('compose.deploy.host', fn() => $this->setEnv('COMPOSE_DEPLOY_HOST', text('What is the hostname of the deployment server?')));
        $user = $this->getOrSetConfig('compose.deploy.user', fn() => $this->setEnv('COMPOSE_DEPLOY_USER', text("What user will you use to login to {$host}", default: exec('whoami'))));
        $path = $this->getOrSetConfig('compose.deploy.path', fn() => $this->setEnv('COMPOSE_DEPLOY_PATH', text("Enter the path on {$host} this app should")));
        //$password = $this->getOrSetConfig('compose.deploy.password', fn() => $this->setEnv('COMPOSE_DEPLOY_PASSWORD', password("Enter the password for $user@$host")));

        // ensure the host path exists; also, check that we can login
        Process::run("ssh $user@$host 'mkdir -p $path'")->throw();

        // create the remote .env file
        if ($this->shouldCreateRemoteEnvFile($user, $host, $path)) {
            $this->updateEnvOnRemote($user, $host, $path);
        }

        # create build/deploy directory
        $build_path = __DIR__ . '/../../build';

        $this->createDockerfile();
        $this->createAppTarball();
//        $compose_yaml = spin(fn() => , 'Generating compose configuration...');

        // copy the build directory to the server
        spin(fn() => Process::run("scp -r {$build_path} {$user}@{$host}:{$path}/")->throw(),
            'Copying app to remote server'
        );


        // run docker build on server
        spin(fn() => Process::forever()->run("ssh {$user}@{$host} docker build --target=production -t {$this->getPhpImageName()} {$path}/build")->throw(),
            'Building production image...'
        );

        file_put_contents('/tmp/docker-compose.yml', $this->getComposeYaml('production'));

        spin(fn() => Process::run("scp -r /tmp/docker-compose.yml {$user}@{$host}:{$path}/")->throw(),
            'Copying compose file to remote server'
        );

//
//        Process::tty()->timeout(120)->run("ssh -t {$user}@{$host} 'docker-compose -f {$path}/docker-compose.yml up -d -t0'")->throw();

        $this->info('Deployed in '. now()->since($start));
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

    private function updateEnvOnRemote(mixed $user, mixed $host, mixed $path)
    {
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
}
