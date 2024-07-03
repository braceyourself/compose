<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\confirm;

class ComposeMigrateCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:migrate';
    protected $description = 'Run the migrations';


    public function handle()
    {
        Process::tty()->run(
            Compose::buildArtisanCommand("migrate"),
            fn($type, $output) => $this->info($output)
        );
    }
}
