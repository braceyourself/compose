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
    private string $setup_type;

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

        if (config('compose.setup_complete') == false) {

            $this->setup_type = select('Would you like to use the compose defaults or choose your own?', [
                'default' => 'Use the default compose setup',
                'custom'  => 'Choose my own setup'
            ], default: 'default');



            $this->setEnv('COMPOSE_PHP_VERSION', $this->getDefaultPhpVersion());
            $this->setEnv('USER_ID', $user_id);
            $this->setEnv('GROUP_ID', $group_id);
            $this->setEnv('COMPOSE_NAME', $dir_name);
            $this->setEnv('COMPOSE_ROUTER', $dir_name);
            $this->setEnv('COMPOSE_PROFILES', 'local');
            $this->setEnv('COMPOSE_NETWORK', 'traefik_default');
            $this->setEnv('COMPOSE_DOMAIN', $this->getComposeDomain());
            $this->setEnv('COMPOSE_PHP_IMAGE', "ethanabrace/php:{$this->getPhpVersion()}");
            $this->ensureViteServerSettings();

            $connection_name = $this->setEnv('DB_CONNECTION', $this->getDatabaseConnectionName());

            if ($connection_name !== 'sqlite') {
                $this->setEnv('DB_HOST', $this->getDbHost($connection_name), "database.connections.{$connection_name}.host");
                $this->setEnv('DB_PORT', $this->getDbPort($connection_name), "database.connections.{$connection_name}.port");
                $this->setEnv('DB_DATABASE', $this->getDbDatabase($connection_name), "database.connections.{$connection_name}.database");
                $this->setEnv('DB_USERNAME', $this->getDbUsername($connection_name), "database.connections.{$connection_name}.username");
                $this->setEnv('DB_PASSWORD', $this->getDbPassword($connection_name), "database.connections.{$connection_name}.password");
            }

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

    private function promptForPhpImageName(): string
    {
        $choice = select("Enter your PHP image", [
            'match'  => "Use the image matching your php version ({$this->getPhpVersion()})",
            'text'   => 'Enter Manually',
            'choose' => 'Choose from list',
            //'search' => "Search for an image",
        ], default: 'match');

        return match ($choice) {
            'search' => search("Search docker hub for an image", function ($value) {

            }),
            'text' => text("Enter the image name to user for the php service:", default: "php:{$this->getPhpVersion()}", hint: "This can be whatever you like."),
            'choose' => select("Choose from the list of images", collect($this->getPhpVersions())->map(fn($version) => "php:$version")),
            'match' => "php:{$this->getPhpVersion()}"
        };
    }

    private function getDatabaseConnectionName()
    {
        if ($this->setup_type == 'default') {
            return 'mysql';
        }

        return function () {
            $name = select(
                'Select the database connection',
                collect(config('database.connections'))->keys()->push('custom')->toArray(),
                config('database.default')
            );

            if ($name == 'custom') {
                $name = text('Enter the custom connection name');
            }

            return $name;

        };

    }

    private function getDbHost($connection_name)
    {
        if ($this->setup_type == 'default') {
            return 'database';
        }

        return function () use ($connection_name) {
            return text("Enter the host address for your {$connection_name} database", default: config("database.connections.{$connection_name}.host", $connection_name));
        };
    }

    private function getDbPort(mixed $connection_name)
    {
        if ($this->setup_type == 'default') {
            return '3306';
        }

        return function () use ($connection_name) {
            return text("Enter the database port", default: config("database.connections.{$connection_name}.port"));
        };
    }

    private function getDbDatabase(mixed $connection_name)
    {
        if ($this->setup_type == 'default') {
            return str(config('compose.name'))->snake()->value();
        }

        return function () use ($connection_name) {
            return text("Enter the database name", default: config("database.connections.{$connection_name}.database"));
        };
    }

    private function getDbUsername(mixed $connection_name)
    {
        if ($this->setup_type == 'default') {
            return 'admin';
        }

        return function () use ($connection_name) {
            return text("Enter the database username", default: config("database.connections.{$connection_name}.username"));
        };
    }

    private function getDbPassword(mixed $connection_name)
    {
        if ($this->setup_type == 'default') {
            return 'password';
        }

        return function () use ($connection_name) {
            return text("Enter the database password", default: config("database.connections.{$connection_name}.password"));
        };
    }

    private function getDefaultPhpVersion()
    {
        if($this->setup_type == 'default'){
            return $this->getPhpVersions()->first();
        }

        return fn() => select("Select PHP Version:", $this->getPhpVersions());
    }

    private function ensureViteServerSettings()
    {
        $needs_config = !$this->getViteConfig()->contains('server:');
        if($this->setup_type == 'default' && $needs_config){
            $this->addViteServerSettings();
        }


    }

    private function getComposeDomain()
    {
        if($this->setup_type == 'default'){
            return str(config('compose.name'))->slug() . ".localhost";
        }

        return fn() => text("What domain name would you like to use?",
            default: str(config('compose.name'))->slug() . ".localhost",
            hint: "This will be used to view your application in the browser"
        );
    }

}
