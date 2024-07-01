<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Concerns\ComposeServices;
use function Laravel\Prompts\confirm;

class ComposeUpCommand extends Command
{
    use ComposeServices;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compose:up 
                                {--d|detatch : Detach from the terminal}
                                {--build : Build the images before starting the services}
                                {--force-recreate : Force recreate the services}
                                {--t|timeout= : Timeout in seconds}
                                {--remove-orphans}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Spin up the services';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting the services...");

        $this->ensureTraefikIsRunning();

        $removeOrphans = $this->option('remove-orphans') ? '--remove-orphans' : '';
        $forceRecreate = $this->option('force-recreate') ? '--force-recreate' : '';
        $timeout = ($timeout = $this->option('timeout')) !== null ? "--timeout $timeout" : '';

        $build_dir = __DIR__.'/../../build';
        file_put_contents("$build_dir/Dockerfile", $this->getDockerfile());

        $this->info(
            Process::tty()
                ->forever()
                ->run("docker build $build_dir -t {$this->getPhpImageName()}")
                ->throw()
                ->output()
        );

        $this->info(
            Process::tty()
                ->run("echo '{$this->getComposeConfig()}' | docker compose -f - up -d $removeOrphans $forceRecreate $timeout")
                ->throw()
                ->output()
        );

        if (confirm("Run migrations?")) {
            $this->call('compose:migrate');
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
