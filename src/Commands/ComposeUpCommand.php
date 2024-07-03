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
        $this->getPhpVersion();
        $this->getPhpImageName();

        $this->ensureTraefikIsRunning();

        $removeOrphans = $this->option('remove-orphans') ? '--remove-orphans' : '';
        $forceRecreate = $this->option('force-recreate') ? '--force-recreate' : '';
        $timeout = ($timeout = $this->option('timeout')) !== null ? "--timeout $timeout" : '--timeout=0';


        if ($this->option('build')) {
            $this->call('compose:build');
        }

        $run_migrations = $this->hasPendingMigrations() && confirm("There are pending migrations. Would you like to run them?");

        Compose::tty()->run("up -d $removeOrphans $forceRecreate $timeout");

        if ($run_migrations) {
            Compose::runArtisanCommand("migrate");
        }

    }

    private function hasPendingMigrations()
    {
        $output = Compose::runArtisanCommand("migrate:status")->output();

        return str($output)->contains([
            'Pending',
            'Migration table not found'
        ]);
    }
}
