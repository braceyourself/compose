<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Concerns\ComposeServices;
use function Laravel\Prompts\confirm;

class ComposeStatusCommand extends Command
{
    use ComposeServices;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compose:status';

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
        $this->info(
            Process::tty()
                ->run("echo '{$this->getComposeConfig()}' | docker compose -f - ps")
                ->throw()
                ->output()
        );
    }
}
