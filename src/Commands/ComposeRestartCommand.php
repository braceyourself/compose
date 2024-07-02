<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\confirm;

class ComposeRestartCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:restart 
                                {--build : Build the images before starting the services}
                                {--t|timeout=0 : Timeout in seconds}
    ';

    protected $description = 'Restart all services';

    public function handle()
    {
        $this->call('compose:up', [
            '--force-recreate' => true,
            '--remove-orphans' => true,
            '--build'          => $this->option('build'),
            '--timeout'        => $this->option('timeout'),
        ]);
    }
}
