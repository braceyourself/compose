<?php

namespace Braceyourself\Compose\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
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
                            {--user_id=}
                            {--group_id=}
                            {path}
                            ';

    protected $description = 'Publish the docker compose files.';

    public function info($string, $verbosity = null)
    {
        if ($this->option('verbose')) {
            return parent::info($string, $verbosity);
        }
    }

    public function handle()
    {
        $user_id = $this->option('user_id');
        $group_id = $this->option('group_id');
        $env = $this->option('env') ?? 'local';
        $dir_name = str($this->argument('path'))->basename()->slug()->value();
        $publish_path = $this->option('publish-path') ?: '.';
        $compose_build_dir = __DIR__ . '/../../build';
        $files = $this->hasOption('files')
            ? $this->option('files')
            : $this->askForFileInput();

        if(config('compose.setup_complete') == false){
            $connection_name = $this->setEnv('DB_CONNECTION', function () {
                $name = select(
                    'Select the database connection',
                    collect(config('database.connections'))->keys()->push('custom')->toArray(),
                    config('database.default')
                );

                if ($name == 'custom') {
                    $name = text('Enter the custom connection name');
                }

                return $name;

            }, 'database.default');


            if($connection_name !== 'sqlite'){
                $this->setEnv('DB_HOST', function () use ($connection_name) {
                    return text("Enter the host address for your {$connection_name} database", default: config("database.connections.{$connection_name}.host", $connection_name));
                }, "database.connections.{$connection_name}.host");

                $this->setEnv('DB_PORT', function () use ($connection_name) {
                    return text("Enter the database port", default: config("database.connections.{$connection_name}.port"));
                }, "database.connections.{$connection_name}.port");

                $this->setEnv('DB_DATABASE', function () use ($connection_name) {
                    return text("Enter the database name", default: config("database.connections.{$connection_name}.database"));
                }, "database.connections.{$connection_name}.database");

                $this->setEnv('DB_USERNAME', function () use ($connection_name) {
                    return text("Enter the database username", default: config("database.connections.{$connection_name}.username"));
                }, "database.connections.{$connection_name}.username");

                $this->setEnv('DB_PASSWORD', function () use ($connection_name) {
                    return text("Enter the database password", default: config("database.connections.{$connection_name}.password"));
                }, "database.connections.{$connection_name}.password");

            }

            $this->setEnv('USER_ID', $user_id);
            $this->setEnv('GROUP_ID', $group_id);
            $this->setEnv('COMPOSE_NAME', $dir_name);
            $this->setEnv('COMPOSE_ROUTER', $dir_name);
            $this->setEnv('COMPOSE_PROFILES', 'local');
            $this->setEnv('COMPOSE_NETWORK', fn() => text('Enter the compose network', default: 'traefik_default'));
            $this->setEnv('COMPOSE_PHP_IMAGE', function () {
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
            }, 'services.php.image');

            $this->setEnv('COMPOSE_SETUP_COMPLETE', true);
        }


        if (in_array('docker-compose.yml', $files)) {
            file_put_contents("{$publish_path}/docker-compose.yml", $this->getComposeYaml($env));
            $this->info("docker-compose.yml published.");
        }

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

            $this->info("Build files published.");
        }
    }

    private function askForFileInput()
    {
        return multiselect(
            'Select files to publish',
            ['docker-compose.yml', 'build'],
            ['docker-compose.yml', 'build'],
        );
    }

}
