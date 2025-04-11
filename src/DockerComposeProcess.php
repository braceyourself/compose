<?php

namespace Braceyourself\Compose;

use Closure;
use Illuminate\Process\Factory;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Braceyourself\Compose\Concerns\CreatesComposeServices;
use Laravel\SerializableClosure\Support\ReflectionClosure;
use Braceyourself\Compose\Exceptions\ComposeProcessFailedException;
use function Laravel\Prompts\spin;

class DockerComposeProcess extends PendingProcess
{
    use CreatesComposeServices;
    use Macroable;

    private bool $throw = false;
    private array $env = [];
    private string|bool $spin = false;
    private $service;

    public function __construct(Factory $factory)
    {
        parent::__construct($factory);
        $env = getenv();

        $env['USER_ID'] ??= exec('id -u');
        $env['GROUP_ID'] ??= exec('id -g');
        $env['COMPOSE_ROUTER'] ??= str(base_path())->basename()->slug()->value();

        $this->env($env);
    }

    public function buildCommand($command): string
    {
        if (file_exists(base_path('docker-compose.yml'))) {
            return "/usr/bin/docker compose $command";
        }

        $project_dir = base_path();
        $compose_file = '/tmp/braceyourself-compose.yml';

        file_put_contents($compose_file, $this->getComposeYaml());

        return "/usr/bin/docker compose --file {$compose_file} --project-directory {$project_dir} $command";
    }

    public function buildArtisanCommand($command): string
    {
        return $this->buildServiceRunCommand('php', "./artisan $command");
    }

    public function buildServiceRunCommand($service, $command): string
    {
        return "run --entrypoint bash --rm $service -c '$command'";
    }

    public function buildServiceExecCommand($service, $command): string
    {
        return "exec $service $command";
    }

    public function artisan($command = null, ?callable $output = null): \Illuminate\Process\ProcessResult|ProcessResult
    {
        $command ??= $this->command;

        return $this->runCallable(fn() => $this->runOn('php', "./artisan $command", $output));
    }

    public function runOn($service, $command, ?callable $output = null): \Illuminate\Process\ProcessResult|ProcessResult
    {
        $command ??= $this->command;

        return $this->runCallable(fn() => $this->run(
            static::buildServiceRunCommand($service, $command),
            $output
        ));
    }

    public function execOn($service, $command, ?callable $output = null): \Illuminate\Process\ProcessResult|ProcessResult
    {
        $command ??= $this->command;

        return $this->runCallable(fn() => $this->run(
            static::buildServiceExecCommand($service, $command),
            $output
        ));
    }

    public function run(array|string|null $command = null, ?callable $output = null)
    {
        return parent::run(
            static::buildCommand($command ?? $this->command),
            $output
        );
    }

    public function service($service_name): static
    {
        $this->service = $service_name;

        return $this;
    }

    public function spin(string|bool $spin = true)
    {
        $this->spin = $spin;

        return $this;
    }

    private function runCallable(Closure $callable)
    {
        if ($this->spin) {
            return spin(
                fn() => $callable()->throw($this->processResultExceptionHandler(...)),
                is_string($this->spin)
                    ? $this->spin
                    : (new ReflectionClosure($callable))->getClosureUsedVariables()['command'] ?? ''
            );
        }

        return $callable()->throw($this->processResultExceptionHandler(...));
    }

    private function processResultExceptionHandler(ProcessResult $res, ProcessFailedException $exception)
    {
        throw new ComposeProcessFailedException($res);
    }
}