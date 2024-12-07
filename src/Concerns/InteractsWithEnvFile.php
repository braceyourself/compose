<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Stringable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;

trait InteractsWithEnvFile
{
    private function localEnv($name = null, $throwWhenMissing = false): Stringable
    {
        $env = str(file_get_contents('.env'));

        if ($name) {
            return $env->explode("\n")
                ->mapInto(Stringable::class)
                ->map->explode('=')
                ->mapWithKeys(fn($l) => [$l->first() => str($l->last())])
                ->filter()
                ->when($throwWhenMissing, fn(Collection $c) => $c->filter(fn($l, $k) => str($k)->startsWith($name)))
                ->whenEmpty(fn() => throw new \Exception("{$name} not found in .env"))
                ->first();
        }

        return $env;
    }

    private function setEnv($key, $value, $force = false)
    {
        if (!$force && $this->localEnv()->contains("{$key}={$value}")) {
            return $value;
        }

        if ($this->localEnv()->contains("{$key}=")) {
            Process::run("sed -i 's/{$key}=.*/$key={$value}/' .env");
        } else {
            // check last line of env
            $last_line = $this->localEnv()->explode("\n")->map(fn($l) => str($l))->last();

            if ($last_line->isNotEmpty() && !$last_line->startsWith('COMPOSE_')) {
                // add a new line to separate from previous entries
                file_put_contents('.env', "\n", FILE_APPEND);
            }

            file_put_contents('.env', "\n{$key}={$value}", FILE_APPEND);
        }

        return $value;
    }
}