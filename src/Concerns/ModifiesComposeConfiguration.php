<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;

trait ModifiesComposeConfiguration
{
    use InteractsWithEnvFile;

    private function getOrSetConfig($key, callable $set = null)
    {
        $value = Config::get($key);

        if (isset($set) && empty($value)) {
            Config::set($key, $value = $set());
        }

        return $value;
    }

    private function getDomainName()
    {
        return $this->getOrSetConfig(
            'compose.domain',
            fn() => $this->setEnv(
                'COMPOSE_DOMAIN',
                text("What domain name would you like to use?",
                    default: str(pathinfo(base_path(), PATHINFO_FILENAME))->slug() . ".localhost",
                    hint   : "This will be used to view your application in the browser",
                )
            )
        );
    }

    private function getGroupId()
    {
        return $this->getOrSetConfig(
            'compose.group_id',
            fn() => $this->setEnv(
                'COMPOSE_GROUP_ID',
                str(Process::run('id -g')->throw()->output())->trim()->value()
            )
        );
    }

    private function getUserId()
    {
        return $this->getOrSetConfig(
            'compose.user_id',
            fn() => $this->setEnv(
                'COMPOSE_USER_ID',
                str(Process::run('id -u')->throw()->output())->trim()->value()
            )
        );
    }

    private function getPhpImageName()
    {
        return $this->getOrSetConfig(
            'compose.services.php.image',
            function () {
                $app_dir = str(base_path())->basename()->slug();
                $hub_username = $this->getDockerHubUsername();

                $image = "$hub_username/$app_dir";

                return $this->setEnv('COMPOSE_PHP_IMAGE',
                    text("PHP Image Name:", default: $image, hint: "Please confirm the PHP image name")
                );
            }
        );
    }

    private function getPhpVersion()
    {
        return $this->getOrSetConfig(
            'compose.services.php.version',
            fn() => $this->setEnv(
                'COMPOSE_PHP_VERSION',
                select("Select PHP Version:", $this->getPhpVersions())
            )
        );
    }

}