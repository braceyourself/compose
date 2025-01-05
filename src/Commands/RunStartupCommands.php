<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunStartupCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compose:run-startup-commands {service}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the startup commands for a service';

    protected $defaults = [
        'php' => [
            'storage:link',
            'migrate' => ['force' => true],
            'config:clear',
            'clear',
            'clear-compiled',
            'chown www-data: public/storage',
        ],
        'horizon' => [
            'horizon',
        ],
        'websockets' => [
            'websockets:serve',
        ],
        'scheduler' => [
            //
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = $this->argument('service');
        $commands = config("compose.startup_commands.{$service}")
            ?? $this->defaults[$service];

        if (empty($commands)) {
            return;
        }

        $this->info("Running startup commands for $service");

        foreach ($commands as $key => $command) {
            $this->info("> $command");

            // handle 'command' => 'args',
            if($this->artisanCommandExists($key)){
                $args = $command;
                $command = $key;
                $this->callSilent($command, $args);
                continue;
            }


            // handle 'command',
            if($this->artisanCommandExists($command)){
                $this->callSilent($command);
                continue;
            }

            // handle non artisan commands
            try {
                exec($command);
            } catch (\Exception $e) {
                $this->error(" - Command Failed.");
                report($e);
            }
        }

    }

    private function artisanCommandExists(string $command): bool
    {
        return collect(Artisan::all())->has($command);
    }

}
