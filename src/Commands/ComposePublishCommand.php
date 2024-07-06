<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Artisan;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use function Laravel\Prompts\info;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;

class ComposePublishCommand extends Command
{
    use CreatesComposeServices;

    protected $signature = 'compose:publish';
    protected $description = 'Publish the docker compose files.';

    public function handle()
    {
        $compose_build_dir = __DIR__.'/../../build';
        $this->createDockerfile();

        if (!file_exists(base_path('build'))) {
            mkdir(base_path('build'));
        }

        foreach(scandir($compose_build_dir) as $file) {
            if (in_array($file, ['.', '..', 'app.tar'])) {
                continue;
            }
            copy("{$compose_build_dir}/{$file}", base_path('build/'.basename($file)));
        }

        $yaml = str($this->getComposeYaml())
            ->replaceMatches('/context:.*/', 'context: ./build')
            ->value();

        file_put_contents(base_path('docker-compose.yml'), $yaml);

        $this->info("Compose files published:");
        $this->info("  - docker-compose.yml");
        foreach(scandir(base_path('build')) as $file) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }
            $this->info("  - build/{$file}");
        }
    }
}
