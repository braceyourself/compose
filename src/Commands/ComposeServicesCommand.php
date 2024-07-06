<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\info;
use function Laravel\Prompts\confirm;

class ComposeServicesCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:services';
    protected $description = 'Show which services are enabled';

    public function handle()
    {
        Compose::tty()->run('config --services');
    }
}
