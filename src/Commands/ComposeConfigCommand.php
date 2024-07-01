<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Concerns\ComposeServices;
use function Laravel\Prompts\confirm;

class ComposeConfigCommand extends Command
{
    use ComposeServices;

    protected $signature = 'compose:config {services?*}';
    protected $description = 'Show the configuration of the services';

    public function handle()
    {
        $services = collect($this->argument('services'))->join(' ');

        Process::tty()
            ->run("echo '{$this->getComposeConfig()}' | docker compose -f - config $services")
            ->throw();
    }
}
