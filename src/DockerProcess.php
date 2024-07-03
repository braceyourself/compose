<?php

namespace Braceyourself\Compose;

use Illuminate\Support\Facades\Process;

class DockerProcess
{
    private bool $tty = false;
    private bool $throw = false;

    public function execute($command)
    {
        return Process::tty($this->tty)
            ->run("docker $command")
            ->throwIf($this->throw);
    }

    public function tty($tty = true)
    {
        $this->tty = $tty;

        return $this;
    }

    public function throw()
    {
        $this->throw = true;

        return $this;
    }
}