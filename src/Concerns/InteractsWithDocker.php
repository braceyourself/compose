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
        return $this->getOrSetConfig('compose.deploy.docker_hub_username', fn() => $this->setEnv('DOCKER_HUB_USERNAME', text('What is your docker hub username?')));
    }

    public function getDockerHubPassword()
    {
        $username = $this->getDockerHubUsername();

        return $this->getOrSetConfig('compose.deploy.docker_hub_password', fn() => $this->setEnv('DOCKER_HUB_PASSWORD', password("Enter the password for $username")));
    }
}