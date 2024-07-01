<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Concerns\ComposeServices;
use function Laravel\Prompts\confirm;

class ComposeLogsCommand extends Command
{
    use ComposeServices;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compose:logs {services?*}
                            {--f|follow : Follow the logs}
                            {--t|tail= : Number of lines to show from the end of the logs}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the status of the services';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $services = collect($this->argument('services'))->join(' ');
        $follow = $this->option('follow') ? '-f' : '';

        $this->info(
            Process::tty()
                ->run("echo '{$this->getComposeConfig()}' | docker compose -f - logs $follow $services")
                ->throw()
                ->output()
        );
    }
}
