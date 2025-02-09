<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Facades\Process;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\confirm;

trait HasNodeServices
{
    private function npmServiceDefinition($config, $env = 'local'): array
    {
        if (file_exists(base_path('vite.config.js'))) {
            // match contents of defineConfig({ ... })
            if (!$this->getViteConfig()->contains('server:')) {
                warning("It looks like you're using Vite, but you haven't defined server settings in your vite.config.js file.");
                if (confirm("Would you like to add server settings to your vite.config.js file?")) {
                    $this->addViteServerSettings();

                    info("HMR Server settings have been added to your vite.config.js file.");
                    warning("Be sure to review the settings to ensure they are correct.");
                }
            }
        }

        $app_name = str(config('app.name'))->slug();

        return collect([
            'image'          => data_get($config, 'image', "{$app_name}-node"),
            'build' => [
                'context'    => $env == 'production' ? './app' : '.',
                'dockerfile' => './build/Dockerfile',
                'target'     => 'npm'
            ],
            'container_name' => 'hmr.${COMPOSE_DOMAIN}',
            'user'           => '${USER_ID}:${GROUP_ID}',
            'working_dir'    => '/var/www/html',
            'command'        => 'npm run dev -- --host --port=80',
            'labels'         => [
                'traefik.http.services.hmr-${COMPOSE_ROUTER}.loadbalancer.server.port=80',
            ],
            'env_file'       => ['.env'],
            'volumes'        => ['./:/var/www/html'],
            'depends_on'     => ['php'],
            'networks'       => ['default', 'traefik'],
            'profiles'       => ['local']
        ])->merge($config)->toArray();
    }

    protected function addViteServerSettings()
    {
        file_put_contents(base_path('vite.config.js'), $this->getViteConfig()->replace(
            'defineConfig({',
            <<<EOF
            defineConfig({
                server: { 
                    hmr: {
                        host: 'hmr.{$this->getDomainName()}',
                        port: 80
                    }, 
                },
            EOF
        ));
    }

    public function getViteConfig()
    {
        return str(file_get_contents(base_path('vite.config.js')));
    }
}