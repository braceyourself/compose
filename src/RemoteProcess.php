<?php

namespace Braceyourself\Compose;

use Illuminate\Process\Factory;
use Illuminate\Support\Stringable;
use Illuminate\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Process\InvokedProcess;

class RemoteProcess extends PendingProcess
{
    public $user;
    public $host;
    public $remotePath;
    public bool $connected = false;
    public array $commandOptions = [];
    private string $base_command = 'ssh';

    public function __construct(Factory $factory)
    {
        parent::__construct($factory);
    }

    public function connect(string $host, string $user = null, string $path = null)
    {
        $this->host = $host;
        $this->user = $user;

        if ($path) {
            $this->path($path);
        }

        $this->confirmConnection();

        return $this;
    }

    public function confirmConnection(): void
    {
        if ($this->connected) {
            return;
        }

        $this->run('echo "Connected to {$this->host}"')->throw();
        $this->connected = true;
    }

    public function newInstance()
    {
        $instance = new static($this->factory);

        $instance->connected = $this->connected;
        $instance->connect($this->host, $this->user, $this->remotePath);

        return $instance;
    }

    public function toArray()
    {
        return [
            'host' => $this->host,
            'user' => $this->user,
            'remotePath' => $this->remotePath,
            'connected' => $this->connected,
        ];
    }

    public function fileExists($path)
    {
        return (bool)trim($this->quietly()->run("[[ -f {$path} ]] && echo 1 || echo 0")->output());
    }

    public function directoryExists($path)
    {
        return (bool)trim($this->quietly()->run("[[ -d {$path} ]] && echo 1 || echo 0")->output());
    }

    public function appendToFile($file, $content)
    {
        if (!$this->fileExists($file)) {
            throw new \Exception("{$file} does not exist on {$this->host}");
        }

        return $this->run("echo '{$content}' >> {$file}")->throw();
    }

    public function addOption($option, $value = null): static
    {
        $this->commandOptions[] = $value ? "{$option}={$value}" : "{$option}";

        return $this;
    }

    public function quietly()
    {
        return $this->addOption('-q');
    }

    public function run(array|string|null $command = null, ?callable $output = null): ProcessResult|\Illuminate\Contracts\Process\ProcessResult
    {
        return parent::run($this->buildCommand($command), $output)->throw();
    }

    public function start(array|string|null $command = null, ?callable $output = null): InvokedProcess
    {
        return parent::start($this->buildCommand($command), $output);
    }

    public function command(array|string $command): RemoteProcess
    {
        return parent::command($this->buildCommand($command));
    }

    public function path(string $path)
    {
        $this->remotePath = $path;

        return $this;
    }

    public function getCommand(): array|string|null
    {
        return $this->buildCommand($this->command);
    }

    private function buildCommand(array|string|null $command): string
    {
        if (str($command)->startsWith($this->base_command)) {
            return $command;
        }

        $command ??= $this->command;

        $commands = collect();

        if ($this->remotePath) {
            $commands->push("cd {$this->remotePath}");
        }

        $command = $commands->push($command)->filter()->join('; ');

        return str($this->base_command)
            ->when($this->commandOptions, function ($s, $options) {
                foreach($options as $option){
                    $s->append(" $option");
                }

                return $s;
            })
            ->when($this->user, fn($s, $user) => $s->append(" {$this->user}@"))
            ->append("{$this->host} '$command'")
            ->value();
    }

    private function baseCommand(string $command)
    {
        $this->base_command = $command;

        return $this;
    }

    public function sshTty($tty = true)
    {
        if ($tty) {
            $this->commandOptions[] = '-t';
        }else{
            $this->commandOptions = collect($this->commandOptions)->filter(fn($option) => $option !== '-t')->toArray();
        }

        return $this;
    }
}