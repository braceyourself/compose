<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Artisan;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\InteractsWithEnvFile;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use Braceyourself\Compose\Concerns\InteractsWithRemoteServer;
use function Laravel\Prompts\multiselect;

class ComposePublishCommand extends Command
{
    use CreatesComposeServices;
    use InteractsWithRemoteServer;
    use InteractsWithEnvFile;

    protected $signature = 'compose:publish 
                            {--publish-path=}
                            {--files=* : Files to publish}
    ';
    protected $description = 'Publish the docker compose files.';

    public function handle()
    {
        $publish_path = $this->option('publish-path') ?: '.';
        $compose_build_dir = __DIR__ . '/../../build';
        $files = $this->hasOption('files')
            ? $this->option('files')
            : multiselect(
                'Select files to publish',
                ['docker-compose.yml', 'build'],
                ['docker-compose.yml', 'build'],
            );

        if (in_array('build', $files)) {
            $this->createDockerfile();

            if (!file_exists("{$publish_path}/build")) {
                mkdir("{$publish_path}/build", recursive: true);
            }

            foreach (scandir($compose_build_dir) as $file) {
                if (in_array($file, ['.', '..', 'app.tar'])) {
                    continue;
                }
                copy("{$compose_build_dir}/{$file}", "{$publish_path}/build/" . basename($file));
            }
        }

        if (in_array('docker-compose.yml', $files)) {
            file_put_contents("{$publish_path}/docker-compose.yml", $this->getComposeYaml());
        }

        if(!$this->localEnv('COMPOSE_PROFILES')){
            $this->setEnv('COMPOSE_PROFILES', 'local');
        }

        $this->info("Compose files published.");
    }
}
