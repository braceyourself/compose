<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Concerns\ComposeServices;
use function Laravel\Prompts\confirm;

class ComposeMigrateCommand extends Command
{
    use ComposeServices;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compose:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the migrations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (str(exec('alias | grep artisan='))->isEmpty()) {
            $this->warn("If you would like to run 'artisan migrate', like normal, you should add 'alias artisan=\"docker compose exec -T php php artisan\"' to your shell configuration file (e.g. '.bashrc', '.zshrc', etc.)");
        }

        $this->info(
            Process::tty()
                ->run("echo '{$this->getComposeConfig()}' | docker compose -f - exec -T php php artisan migrate")
                ->throw()
                ->output()
        );
    }
}
