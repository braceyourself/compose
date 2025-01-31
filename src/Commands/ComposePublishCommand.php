<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Artisan;
use Braceyourself\Compose\Facades\Compose;
use Braceyourself\Compose\Concerns\HasPhpServices;
use Braceyourself\Compose\Concerns\InteractsWithEnvFile;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use Braceyourself\Compose\Concerns\InteractsWithRemoteServer;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\search;
use function Laravel\Prompts\multiselect;

class ComposePublishCommand extends Command
{
    use CreatesComposeServices;
    use InteractsWithRemoteServer;
    use InteractsWithEnvFile;
    use HasPhpServices;

    protected $signature = 'compose:publish 
                            {--publish-path=}
                            {--files=* : Files to publish}
                            {env? : The environment to publish to}';

    protected $description = 'Publish the docker compose files.';

    public function handle()
    {
        $env = $this->argument('env') ?? 'local';
        $publish_path = $this->option('publish-path') ?: '.';
        $compose_build_dir = __DIR__ . '/../../build';
        $files = $this->hasOption('files')
            ? $this->option('files')
            : $this->askForFileInput();

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
            file_put_contents("{$publish_path}/docker-compose.yml", $this->getComposeYaml($env));
        }

        $this->setEnvIfMissing('COMPOSE_PROFILES', 'local');
        $this->setEnvIfMissing('COMPOSE_NETWORK', fn() => text('Enter the compose network', default: 'traefik_default'));
        $this->setEnvIfMissing('USER_ID', fn() => text('What is your system user_id?', default: '1000'));
        $this->setEnvIfMissing('GROUP_ID', fn() => text('What is your system group_id?', default: '1000'));


        // choose from list of images and allow for searching
        //default: 'php:8.0-fpm'


        $this->setEnvIfMissing('COMPOSE_PHP_IMAGE', function () {
            $choice = select("Enter your PHP image", [
                'match'  => "Use the image matching your php version ({$this->getPhpVersion()})",
                'text'   => 'Enter Manually',
                'choose' => 'Choose from list',
                //'search' => "Search for an image",
            ], default: 'match');

            return match($choice){
                'search' => search("Search docker hub for an image", function ($value) {

                }),
                'text' => text("Enter the image name to user for the php service:", default: "php:{$this->getPhpVersion()}", hint: "This can be whatever you like."),
                'choose' => select("Choose from the list of images", collect($this->getPhpVersions())->map(fn($version) => "php:$version")),
                'match' => "php:{$this->getPhpVersion()}"
            };
        });

        $this->info("Compose files published.");
    }

    private function askForFileInput()
    {
        return multiselect(
            'Select files to publish',
            ['docker-compose.yml', 'build'],
            ['docker-compose.yml', 'build'],
        );
    }

    private function setEnvIfMissing($key, $value)
    {
        if (!$this->localEnv($key)) {

            $value = is_callable($value) ? $value() : $value;

            $this->setEnv($key, $value);

        }
    }
}
