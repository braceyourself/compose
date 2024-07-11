<?php

namespace Braceyourself\Compose\Facades;

use RuntimeException;
use Illuminate\Support\Facades\Facade;
use Braceyourself\Compose\RemoteProcess;

/**
 * @mixin \Braceyourself\Compose\RemoteProcess
 */
class Remote extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'braceyourself-remote';
    }

    public static function __callStatic($method, $args)
    {
        /** @var RemoteProcess $instance */
        $instance = static::getFacadeRoot();

        if (!$instance) {
            throw new RuntimeException('A facade root has not been set.');
        }

        if ($instance->connected) {
            $instance->command = '';
            $instance->commandOptions = [];
        }

        // only save connection info if we already connected
        return $instance->$method(...$args);
    }
}