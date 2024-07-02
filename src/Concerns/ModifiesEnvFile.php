<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Facades\Process;
use function Laravel\Prompts\confirm;

trait ModifiesEnvFile
{
    private function setEnv($key, $value, $force = false)
    {
        if(!$force && str(file_get_contents('.env'))->contains("{$key}=$value")){
            return $value;
        }

        if ($force || confirm("Would you like to add this to your .env file?", hint: "{$key}={$value}")) {
            if (str(file_get_contents('.env'))->contains("{$key}=")){
                Process::run("sed -i 's/{$key}=.*/$key={$value}/' .env");
            } else {
                // check last line of env
                $last_line = str(file_get_contents('.env'))->explode("\n")->map(fn($l) => str($l))->last();

                if ($last_line->isNotEmpty() && !$last_line->startsWith('COMPOSE_')) {
                    // add a new line to separate from previous entries
                    file_put_contents('.env', "\n", FILE_APPEND);
                }

                file_put_contents('.env', "\n{$key}={$value}", FILE_APPEND);
            }
        }

        return $value;
    }
}