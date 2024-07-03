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

class ComposePushCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:push {service?}';
    protected $description = 'Build the services';

    public function handle()
    {
        $service = $this->argument('service');
        spin(fn() => Compose::run("push {$service}"),
            empty($service) ? 'Pushing images to docker hub' : "Pushing $service image to docker hub"
        );
    }
}
