<?php

namespace Braceyourself\Compose\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Braceyourself\Compose\DockerComposeProcess
 */
class Compose extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'compose';
    }
}