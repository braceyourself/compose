<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Cache;
use Braceyourself\Compose\Facades\Remote;
use Illuminate\Support\Traits\ForwardsCalls;

trait InteractsWithRemoteServer
{
    public function getRemoteEnv($name = null): Stringable
    {
        $env = Cache::store('array')->rememberForever(
            'compose-remote-env' . $this->user . $this->host . $this->path,
            fn() => str(
                Remote::run("cat .env")->throw()->output()
            )
        );

        if ($name) {
            return $env->explode("\n")
                ->mapInto(Stringable::class)
                ->filter(fn($line) => $line->startsWith($name))
                ->map(fn($line) => $line->after('='))
                ->first() ?? new Stringable();
        }

        return $env;
    }
}