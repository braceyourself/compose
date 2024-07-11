<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Artisan;

trait HasDatabaseServices
{
    private function databaseServiceDefinition($config = []): array
    {
        return collect([
            'image'       => 'mysql',
            'restart'     => 'always',
            'healthcheck' => [
                'test'     => ['CMD', 'mysqladmin', 'ping', '-h', 'localhost'],
                'interval' => '15s',
                'timeout'  => '10s',
                'retries'  => 3,
            ],
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

    public function getDefault($key, $value)
    {
        return match("$key"){
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => 'mysql',
            'DB_PORT' => '3306',
            'DB_DATABASE' => $value ?: str(config('app.name'))->slug()->value(),
            'DB_USERNAME' => 'admin',
            'DB_PASSWORD' => '',
        };
    }
}