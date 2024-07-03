<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\confirm;

class ComposeDownCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:down {--volumes : Remove the named volumes}';
    protected $description = 'Spin down the services';

    public function handle()
    {
        $this->info(
            Process::tty()
                ->run(Compose::buildCommand("down -t0"))
                ->output()
        );
    }
}
