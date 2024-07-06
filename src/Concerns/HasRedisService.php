<?php

namespace Braceyourself\Compose\Concerns;

trait HasRedisService
{
    private function redisServiceDefinition($config = [], $environment = 'local'): array
    {
        $env = str(file_get_contents('.env'));

        if (!$env->contains('REDIS_HOST=redis')) {
            $this->setEnv('REDIS_HOST', 'redis', force: true);
        }

        return collect([
            'image'    => 'redis:alpine',
            'restart'  => 'always',
            'profiles' => ['production']
        ])->merge($config)->toArray();
    }

}