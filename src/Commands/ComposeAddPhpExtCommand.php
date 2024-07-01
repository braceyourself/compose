<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use function Laravel\Prompts\confirm;

/**
 * todo: finish building this command
 */
class ComposeAddPhpExtCommand extends Command
{
    protected $signature = 'compose:add-php-ext {ext?*}';
    protected $description = 'Add PHP extensions to the PHP service';

    public function handle()
    {
        $this->config('php_image_type', 'custom');

        $services = collect($this->argument('services'))->join(' ');
        Process::tty()->run("docker compose config $services")->throw();
    }
}
