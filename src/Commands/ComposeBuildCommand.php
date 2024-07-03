<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Docker;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\HasPhpServices;
use Braceyourself\Compose\Concerns\BuildsDockerfile;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use Braceyourself\Compose\Concerns\ModifiesComposeConfiguration;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\confirm;

class ComposeBuildCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:build {--push}';
    protected $description = 'Build the services';

    public function handle()
    {
        $app_path = base_path();
        $context = data_get($this->phpServiceDefinition(), 'build.context');

        // put contents of $base into tar, overwriting if present, excluding lines found in .dockerignore
        Process::run("tar -cf $context/app.tar --exclude-vcs --exclude-from='$context/.dockerignore' -C $app_path .");

        file_put_contents("$context/Dockerfile", $this->getDockerfile());

        spin(fn() => Docker::execute("build --target=app $context -t {$this->getPhpImageName()}"),
            'Building PHP image'
        );

        spin(fn() => Docker::execute("build --target=nginx $context -t {$this->getNginxImageName()}"),
            'Building Nginx image'
        );

        if ($this->option('push')) {
            spin(fn() => $this->call('compose:push'),
                'Pushing images to docker hub'
            );
        }
    }
}
