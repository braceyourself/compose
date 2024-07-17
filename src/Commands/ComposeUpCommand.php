<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Laravel\Prompts\ConfirmPrompt;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\spin;

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
        if (!$this->localEnv()->contains('COMPOSE_PROFILES=')) {
            $this->setEnv('COMPOSE_PROFILES', 'local');
        }

        $removeOrphans = $this->option('remove-orphans') ? '--remove-orphans' : '';
        $forceRecreate = $this->option('force-recreate') ? '--force-recreate' : '';
        $timeout = ($timeout = $this->option('timeout')) !== null ? "--timeout $timeout" : '--timeout=0';

        if ($this->option('build')) {
            Compose::tty()->forever()->run('build')->throw();
        }

        spin(fn() =>
            Compose::run("up -d --no-build $removeOrphans $forceRecreate $timeout"),
            "Starting services..."
        );

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
            $this->call('migrate:status',['--pending' => true])
        )->trim("\n ")->is('*No pending migrations*');
    }
}
