<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Docker;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\HasPhpServices;
use Braceyourself\Compose\Concerns\BuildsDockerfile;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use Braceyourself\Compose\Concerns\ModifiesComposeConfiguration;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\info;
use function Laravel\Prompts\confirm;

class ComposeBuildCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:build {--push} {--target=app}';
    protected $description = 'Build the services';

    public function handle()
    {
        $app_path = base_path();
        $context = data_get($this->phpServiceDefinition(), 'build.context');
        $target = $this->option('target');

        // remove app.tar if it exists
        if (file_exists("$context/app.tar")) {
            unlink("$context/app.tar");
        }

        // put contents of $base into tar, overwriting if present, excluding lines found in .dockerignore
        Process::run("tar -cf $context/app.tar --ignore-failed-read --exclude-vcs --exclude-from='$context/.dockerignore' -C $app_path .")->throw();

        file_put_contents("$context/Dockerfile", $this->getDockerfile());


        info(
            str(
                spin(fn() => Docker::execute("build --target={$target} $context -t {$this->getPhpImageName($target)}")->throw()->output(),
                    'Building PHP image'
                )
            )->explode("\n")->mapInto(Stringable::class)->filter(fn($line) => str($line)->startsWith('Successfully built'))->first()->prepend("PHP ")
        );


        if ($this->option('push')) {
            info(
                str(
                    spin(fn() => Docker::execute("push {$this->getPhpImageName()}")->output(),
                        'Pushing PHP image'
                    )
                )->explode("\n")->mapInto(Stringable::class)->filter(fn($line) => str($line)->startsWith('The push refers to repository'))->first()
            );
        }


        info(
            str(
                spin(fn() => Docker::execute("build --target=nginx $context -t {$this->getNginxImageName()}")->output(),
                    'Building Nginx image'
                )
            )->explode("\n")->mapInto(Stringable::class)->filter(fn($line) => str($line)->startsWith('Successfully built'))->first()->prepend("Nginx ")
        );

        if ($this->option('push')) {
            info(
                str(
                    spin(fn() => Docker::execute("push {$this->getNginxImageName()}")->output(),
                        'Pushing Nginx image'
                    )
                )->explode("\n")->mapInto(Stringable::class)->filter(fn($line) => str($line)->startsWith('The push refers to repository'))->first()
            );
        }
    }
}
