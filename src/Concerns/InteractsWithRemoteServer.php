<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Stringable;
use Illuminate\Support\Facades\Cache;
use Braceyourself\Compose\Facades\Remote;
use Illuminate\Support\Traits\ForwardsCalls;
use function Laravel\Prompts\text;

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

    private function loadServerCredentials(): void
    {
        $prompt = function ($text, $default = '', $hint = null) {
            return text($text,
                default: $default,
                hint   : $hint ?? 'Publish and set the compose configuration to avoid this prompt. (artisan vendor:publish --tag=compose-config)'
            );
        };

        $this->host = $this->option('host') ?: $this->getOrSetConfig('compose.deploy.host', fn() => $prompt('What is the hostname of the deployment server?'));
        $this->user = $this->option('user') ?: $this->getOrSetConfig('compose.deploy.user', fn() => $prompt("Enter the user name for '{$this->host}'", exec('whoami')));
        $this->path = $this->option('path') ?: $this->getOrSetConfig('compose.deploy.path', fn() => $prompt("Enter the path on {$this->host} this app should"));
    }
}