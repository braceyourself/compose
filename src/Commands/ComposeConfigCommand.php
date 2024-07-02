<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\confirm;

class ComposeConfigCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:config {services?*}';
    protected $description = 'Show the configuration of the services';

    public function handle()
    {
        $services = collect($this->argument('services'))->join(' ');

        Process::tty()
            ->run(Compose::command("config $services"))
            ->throw();
    }
}
