<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\confirm;

class ComposeUpCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:up 
                                {--d|detach : Detach from the terminal}
                                {--build : Build the images before starting the services}
                                {--force-recreate : Force recreate the services}
                                {--t|timeout= : Timeout in seconds}
                                {--remove-orphans}
    ';

    protected $description = 'Spin up the services';

    public function handle()
    {
        $this->ensureTraefikIsRunning();

        $removeOrphans = $this->option('remove-orphans') ? '--remove-orphans' : '';
        $forceRecreate = $this->option('force-recreate') ? '--force-recreate' : '';
        $timeout = ($timeout = $this->option('timeout')) !== null ? "--timeout $timeout" : '--timeout=0';


        if ($this->option('build')) {
            $this->createDockerfile();
            $this->runBuild();
        }

        $this->info(
            Process::tty()
                ->run(Compose::command("up -d $removeOrphans $forceRecreate $timeout"))
                ->throw()
                ->output()
        );

        if ($this->hasPendingMigrations() && confirm("There are pending migrations. Would you like to run them?")) {
            $this->call('compose:migrate');
        }
    }

    private function hasPendingMigrations()
    {
        $hasPendingMigrations = false;

        Process::run(
            Compose::artisanCommand("migrate:status"),
            function ($type, $output) use (&$hasPendingMigrations) {
                if (str($output)->trim()->endsWith('Pending')) {
                    $hasPendingMigrations = true;
                }
            }
        );

        return $hasPendingMigrations;
    }
}
