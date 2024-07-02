<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\confirm;

class ComposeLogsCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:logs {services?*}
                            {--f|follow : Follow the logs}
                            {--t|tail= : Number of lines to show from the end of the logs}
    ';

    protected $description = 'Show the status of the services';

    public function handle()
    {
        $services = collect($this->argument('services'))->join(' ');
        $follow = $this->option('follow') ? '-f' : '';

        $this->info(
            Process::tty()
                ->run(Compose::command("logs $follow $services"))
                ->throw()
                ->output()
        );
    }
}
