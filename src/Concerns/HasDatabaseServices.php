<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Artisan;

trait HasDatabaseServices
{
    private function databaseServiceDefinition($config = [], $env = 'local'): array
    {
        return collect([
            'image'          => 'mysql',
            'restart'        => 'always',
            'container_name' => str(config('app.name'))->slug() . '-mysql',
            'healthcheck'    => [
                'test'     => ['CMD', 'mysqladmin', 'ping', '-h', 'localhost'],
                'interval' => '15s',
                'timeout'  => '10s',
                'retries'  => 3,
            ],
            'environment'    => [
                'MYSQL_ROOT_PASSWORD' => '${DB_PASSWORD}',
                'MYSQL_DATABASE'      => '${DB_DATABASE}',
                'MYSQL_USER'          => '${DB_USERNAME}',
                'MYSQL_PASSWORD'      => '${DB_PASSWORD}',
            ],
            'volumes'        => [
                './database/.data:/var/lib/mysql',
            ],
            ...$this->getPortMappings($env)
        ])->merge($config)
            // ignore ports mapping if empty
            ->filter(function ($v, $k) {
                if ($k == 'ports') {
                    return !empty($v);
                }

                return !in_array($k, ['expose_on_port']);
            })
            // don't map ports in production'
            ->when($env == 'production', fn($c) => $c->filter(fn($v, $k) => $k !== 'ports'))
            ->toArray();
    }

    private function getPortMappings($env = 'local'): array
    {
        if ($host_port = config('compose.services.database.expose_on_port')) {
            return [
                'ports' => [
                    "{$host_port}:3306"
                ]
            ];
        }

        return [];
    }

    public function getDefault($key, $value)
    {
        return match ("$key") {
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => 'mysql',
            'DB_PORT' => '3306',
            'DB_DATABASE' => $value ?: str(config('app.name'))->slug()->value(),
            'DB_USERNAME' => 'admin',
            'DB_PASSWORD' => '',
        };
    }
}