<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\confirm;

class ComposeStatusCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:status';
    protected $description = 'Show the status of the services';

    public function handle()
    {
        $this->info(
            Process::tty()
                ->run(Compose::command("ps"))
                ->throw()
                ->output()
        );
    }
}
