<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Artisan;
use function Laravel\Prompts\text;
use function Laravel\Prompts\info;
use function Laravel\Prompts\confirm;

trait HasDatabaseServices
{
    private function databaseServiceDefinition($config = []): array
    {
        $env = str(file_get_contents('.env'));
        if ($env->contains(['# DB_', '#DB_']) && confirm("There are commented DB_ values in your .env. Would you like to update them?")) {
            // loop over the env DB_* values
            $env->explode("\n")->each(function ($line) {
                $line = str($line);
                if ($line->startsWith('#') && $line->contains('DB_')) {
                    $key = $line->before('=')->ltrim('# ');
                    $value = $line->after('=');

                    $this->setEnv($key, text($key, default: $value), force: true);

                    // uncomment
                    Process::run("sed -i '/{$key}/s/^{$line->before('DB_')}//' .env");
                }
            });
        }

        Artisan::call('config:clear');

        if (($db_default = config('database.default')) != 'mysql') {
            info("DB_CONNECTION is currently set to $db_default.");
            if (confirm("Would you like to change it to mysql?")) {
                $this->setEnv('DB_CONNECTION', 'mysql', force: true);
            }

        }

        return collect([
            'image'       => 'mysql',
            'restart'     => 'always',
            'environment' => [
                'MYSQL_ROOT_PASSWORD' => '${DB_PASSWORD}',
                'MYSQL_DATABASE'      => '${DB_DATABASE}',
                'MYSQL_USER'          => '${DB_USERNAME}',
                'MYSQL_PASSWORD'      => '${DB_PASSWORD}',
            ],
            'volumes'     => [
                './database/.data:/var/lib/mysql',
            ],
            ...$this->getPortMappings()
        ])->merge($config)
            // ignore ports mapping if empty
            ->filter(function ($v, $k) {
                if ($k == 'ports') {
                    return !empty($v);
                }

                return !in_array($k, ['expose_on_port']);
            })
            ->toArray();
    }

    private function getPortMappings(): array
    {
        if ($host_port = config('compose.services.mysql.expose_on_port')) {
            return [
                'ports' => [
                    "{$host_port}:3306"
                ]
            ];
        }

        return [];
    }
}