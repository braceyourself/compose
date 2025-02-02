<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Stringable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use function PHPUnit\Framework\isCallable;

trait InteractsWithEnvFile
{
    private function localEnv($name = null, $throwWhenMissing = false): ?Stringable
    {
        $this->ensureEnvFileExists();

        $env = $this->getEnvContents();

        return $name
            ? $env->explode("\n")
                ->mapInto(Stringable::class)
                ->map->explode('=')
                ->mapWithKeys(fn($l) => [$l->first() => str($l->last())])
                ->filter()
                ->filter(fn($l, $k) => str($k)->startsWith($name))
                ->when($throwWhenMissing, fn(Collection $c) => $c->whenEmpty(fn() => throw new \Exception("{$name} not found in .env")))
                ->first()
            : $env;
    }

    private function setEnv($key, $value, $config_key = null, $force = false)
    {
        $config_key ??= $this->getConfigKeyForEnv($key);
        $value = is_callable($value) ? $value() : $value;


        // if the key already exists and we're not forcing an update, return the value
        if (!$force && $this->localEnv($key) == "$value") {
            config([
                $config_key => $value
            ]);

            return config($config_key, $value);
        }

        if ($this->localEnv()->contains("{$key}=")) {
            $env_contents = $this->getEnvContents();

            // if line starting with key exists, replace anything on the same line with this new definition
            $env_contents = $env_contents->explode("\n")
                ->mapInto(Stringable::class)
                ->map(function ($l) use ($key, $value) {
                    return $l->contains("{$key}=") ? "{$key}={$value}" : $l;
                })
                ->join("\n");

            $this->updateEnvContents($env_contents);

        } else {
            // check last line of env
            $last_line = $this->localEnv()->explode("\n")->map(fn($l) => str($l))->last();

            $this->appendToEnv("{$key}={$value}");
        }


        config([
            $config_key => $value
        ]);

        return config($config_key);
    }

    private function ensureEnvFileExists()
    {
        if (class_exists(Storage::class)) {
            $fs = $this->disk();
        }

        if (isset($fs)) {
            if(!$fs->exists('.env') && $fs->exists('.env.example')) {
                // copy from .env.example
                $fs->copy('.env.example', '.env');
            } else if (!$fs->exists('.env')) {
                // create a new .env file
                $fs->put('.env', '');
            }

        } else {
            if (!file_exists('.env') && file_exists('.env.example')) {
                // copy from .env.example
                copy('.env.example', '.env');
            } else if (!file_exists('.env')) {
                // create a new .env file
                file_put_contents('.env', '');
            }
        }
    }

    private function disk()
    {
        return Storage::build([
            'driver' => 'local',
            'root' => base_path()
        ]);
    }

    private function getEnvContents(): Stringable
    {

        return str(
            class_exists(Storage::class)
                ? $this->disk()->get('.env')
                : file_get_contents('.env')
        );
    }

    private function updateEnvContents($content)
    {
        if (class_exists(Storage::class)) {
            $this->disk()->put('.env', $content);
        } else {
            file_put_contents('.env', $content);
        }
    }

    public function getConfigKeyForEnv($key)
    {
        return match($key){
            'DB_CONNECTION' => 'database.default',
            'DB_HOST' => "database.connections.".config('database.default').".host",
            'DB_PORT' => "database.connections.".config('database.default').".port",
            'DB_DATABASE' => "database.connections.".config('database.default').".database",
            'DB_USERNAME' => "database.connections.".config('database.default').".username",
            'DB_PASSWORD' => "database.connections.".config('database.default').".password",
            'COMPOSE_PHP_IMAGE' => 'compose.services.php.image',
            default => str($key)->lower()->replace('compose_', 'compose.')->value()
        };
    }

    private function appendToEnv($content)
    {
        if (class_exists(Storage::class)) {
            $this->disk()->append('.env', $content);
        } else {
            file_put_contents('.env', $content, FILE_APPEND);
        }
    }
}