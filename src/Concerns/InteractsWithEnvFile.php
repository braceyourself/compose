<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Process;

trait InteractsWithEnvFile
{
    private function getEnvAsString(): Stringable
    {
        return str(file_get_contents('.env'));
    }

    private function setEnv($key, $value, $force = false)
    {
        if (!$force && $this->getEnvAsString()->contains("{$key}={$value}")) {
            return $value;
        }

        if ($this->getEnvAsString()->contains("{$key}=")) {
            Process::run("sed -i 's/{$key}=.*/$key={$value}/' .env");
        } else {
            // check last line of env
            $last_line = $this->getEnvAsString()->explode("\n")->map(fn($l) => str($l))->last();

            if ($last_line->isNotEmpty() && !$last_line->startsWith('COMPOSE_')) {
                // add a new line to separate from previous entries
                file_put_contents('.env', "\n", FILE_APPEND);
            }

            file_put_contents('.env', "\n{$key}={$value}", FILE_APPEND);
        }

        return $value;
    }
}