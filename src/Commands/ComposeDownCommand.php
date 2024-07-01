<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Process;
use Braceyourself\Compose\Concerns\ComposeServices;
use function Laravel\Prompts\confirm;

class ComposeDownCommand extends Command
{
    use ComposeServices;

    protected $signature = 'compose:down {--volumes : Remove the named volumes}';
    protected $description = 'Spin down the services';

    public function handle()
    {
//        --remove-orphans -t0
        $this->info(
            Process::tty()
                ->run("echo '{$this->getComposeConfig()}' | docker compose down")
                ->throw()
                ->output()
        );
    }
}
