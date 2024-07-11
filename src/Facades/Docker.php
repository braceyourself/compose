<?php

namespace Braceyourself\Compose\Facades;

use RuntimeException;
use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Braceyourself\Compose\DockerProcess
 */
class Docker extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'braceyourself-compose-docker';
    }

    public static function __callStatic($method, $args)
    {
        $app = static::getFacadeApplication();

        if (!$app) {
            throw new RuntimeException('A facade root has not been set.');
        }

        $instance = $app->make(static::getFacadeAccessor());

        return $instance->$method(...$args);
    }
}