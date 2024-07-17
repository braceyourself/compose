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

    protected $signature = 'compose:publish 
                            {--publish-path=}
                            {--C|context=}
                            {--files=* : Files to publish}
    ';
    protected $description = 'Publish the docker compose files.';

    public function handle()
    {
        $publish_path = $this->option('publish-path') ?: base_path();
        $compose_build_dir = __DIR__.'/../../build';
        $files = $this->option('files') ?: ['docker-compose.yml', 'build'];
        $context = $this->option('context') ?: "{$publish_path}/build";

        if (in_array('build', $files)) {
            $this->createDockerfile();

            if (!file_exists("{$publish_path}/build")) {
                mkdir("{$publish_path}/build", recursive: true);
            }

            foreach(scandir($compose_build_dir) as $file) {
                if (in_array($file, ['.', '..', 'app.tar'])) {
                    continue;
                }
                copy("{$compose_build_dir}/{$file}", "{$publish_path}/build/".basename($file));
            }
        }

        if (in_array('docker-compose.yml', $files)) {
            $yaml = str($this->getComposeYaml())
                ->replaceMatches('/context:.*/', "context: {$publish_path}/build")
                ->value();

            file_put_contents("{$publish_path}/docker-compose.yml", $yaml);
        }

        $this->info("Compose files published:");

        if(file_exists("{$publish_path}/docker-compose.yml")){
            $this->info("  - {$publish_path}/docker-compose.yml");
        }

        if(file_exists("{$publish_path}/build")){
            foreach(scandir("{$publish_path}/build") as $file) {
                !in_array($file, ['.', '..'])
                && file_exists("{$publish_path}/build/{$file}")
                && $this->info("  - {$publish_path}/build/{$file}");
            }
        }
    }
}
