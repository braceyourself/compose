<?php

namespace Braceyourself\Compose\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Braceyourself\Compose\DockerProcess
 */
class Docker extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'docker';
    }
}