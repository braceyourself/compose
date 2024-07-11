<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Laravel\Prompts\ConfirmPrompt;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\CreatesComposeServices;

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
        if (!$this->localEnv()->contains('COMPOSE_PROFILES=')) {
            $this->setEnv('COMPOSE_PROFILES', 'local');
        }

        $removeOrphans = $this->option('remove-orphans') ? '--remove-orphans' : '';
        $forceRecreate = $this->option('force-recreate') ? '--force-recreate' : '';
        $timeout = ($timeout = $this->option('timeout')) !== null ? "--timeout $timeout" : '--timeout=0';

        if ($this->option('build')) {
            Compose::tty()->forever()->run('build')->throw();
        }

        Compose::tty()->run("up -d --no-build $removeOrphans $forceRecreate $timeout");

        if ($this->hasPendingMigrations() && $this->confirm("There are pending migrations. Would you like to run them?")) {
            Compose::tty()->artisan("migrate");
        }

        $domain = $this->localEnv('COMPOSE_DOMAIN');
        $schema = app()->isProduction() ? 'https' : 'http';

        $this->info(<<<EOF
        
        \tYour services are now running. You can view your application at:
        \t\t{$schema}://{$domain}

        EOF);
    }

    private function hasPendingMigrations()
    {
        return !str(
            Compose::artisan("migrate:status --pending")->output()
        )->trim("\n ")->is('*No pending migrations*');
    }
}
