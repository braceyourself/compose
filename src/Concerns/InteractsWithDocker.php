<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Facades\Process;
use function Laravel\Prompts\text;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;

trait InteractsWithDocker
{
    private function ensureDockerIsInstalled()
    {
        try {
            Process::run('docker --version')->throw();
        } catch (\Throwable $th) {

            if (!confirm("Docker needs to be installed on this system. Would you like to install it now?")) {
                return;
            }

            // install docker
            Process::run('curl -fsSL https://get.docker.com -o /tmp/get-docker.sh')->throw();
            Process::run('sh /tmp/get-docker.sh')->throw();
        }
    }

    public function getDockerHubUsername()
    {
        return config('compose.deploy.docker_hub_username');
    }

    public function getDockerHubPassword()
    {
        if ($this->getDockerHubUsername()) {
            return $this->getOrSetConfig('compose.deploy.docker_hub_password');
        }
    }
}