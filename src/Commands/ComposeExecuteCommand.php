<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Remote;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\text;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\error;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\password;

class ComposeExecuteCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:exec {service} {cmd?*}';
    protected $description = 'Execute a command in a service';

    public function handle()
    {
        $service = $this->argument('service');
        $command = str(implode(' ', $this->argument('cmd')));

        if ($command->startsWith('artisan')) {
            $command = $command->prepend('./');
        }

        $this->info("Executing command in $service: $command");

        Compose::execOn($service, $command, fn($type, $output) => $this->info($output));
    }
}
