<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Facades\Process;

trait BuildsDockerfile
{
    private function createDockerfile()
    {
        file_put_contents(__DIR__ . '/../../build/Dockerfile', $this->getDockerfile());
    }

    public function copyLocalRepositories()
    {
        return collect(data_get(json_decode(file_get_contents(base_path('composer.json'))), 'repositories'))
            ->filter(function ($repo) {
                return $repo->type === 'path' && (
                        str($repo->url)->startsWith('./')
                        || file_exists(base_path(dirname($repo->url)))
                    );
            })->map(function ($repo) {
                $dirname = dirname($repo->url);
                return "COPY --chown=www-data:www-data {$dirname} {$dirname}";
            })->join("\n");
    }

    private function getDockerfile()
    {
        return <<<DOCKERFILE
        FROM php:{$this->getPhpVersion()}-fpm AS php
        
        USER root
        ENV PATH="/var/www/.composer/vendor/bin:\$PATH"
        ENV PHP_MEMORY_LIMIT={$this->getPhpMemoryLimit()}
        WORKDIR /var/www/html
        ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin
        
        RUN apt-get update \
            && apt install -y {$this->getPackageList()} \
            && rm -rf /var/lib/apt \
            && chmod +x /usr/local/bin/install-php-extensions && sync
            
        RUN install-php-extensions {$this->getExtList()} @composer \
            && groupmod -og {$this->getGroupId()} www-data \
            && usermod -u {$this->getUserId()} www-data
            
        COPY build/php_entrypoint.sh /usr/local/bin/entrypoint.sh
        RUN chmod +x /usr/local/bin/entrypoint.sh \
            && chown -R www-data:www-data /var/www/html \
            && chown -R www-data:www-data /var/www

        USER www-data
        
        COPY composer.json composer.lock ./
        {$this->copyLocalRepositories()}
        RUN composer install --no-dev --no-interaction --no-progress --no-scripts
        COPY --chown=www-data:www-data . .
        RUN  mkdir -p /var/www/html/storage/logs /var/www/html/bootstrap/cache \
            && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
            && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
            && composer run-script post-autoload-dump
        
        ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
        
        
        ### npm ###
        FROM node AS npm
        WORKDIR /var/www/html
        
        ARG VITE_APP_NAME
        ARG VITE_PUSHER_APP_KEY
        ARG VITE_PUSHER_HOST
        ARG VITE_PUSHER_PORT
        ARG VITE_PUSHER_PORT_SECURE
        ARG VITE_PUSHER_SCHEME
        ARG VITE_PUSHER_APP_CLUSTER
        ARG VITE_PUSHER_APP_HOST
        ARG VITE_REVERB_APP_KEY
        ARG VITE_REVERB_HOST
        ARG VITE_REVERB_PORT
        ARG VITE_REVERB_SCHEME
        
        # set node user and group id
        RUN groupmod -og {$this->getGroupId()} node \
            && usermod -u {$this->getUserId()} -g {$this->getGroupId()} -d /var/www node \
            && chown -R node:node /var/www
        
        USER node
        
        COPY --from=php /var/www/html /var/www/html
        
        RUN rm -rf /var/www/.npm \
            && npm install \
            && npm run build
         
        ### production
        FROM php AS production
        COPY --from=npm /var/www/html/public /var/www/html/public
        
        ### nginx ###
        FROM nginx AS nginx
        COPY build/nginx.conf /etc/nginx/templates/default.conf.template
        COPY --from=production /var/www/html/public /var/www/html/public
        RUN ln -sf /var/www/html/storage/app/public /var/www/html/public/storage
        
        DOCKERFILE;
    }

    private function getPackageList()
    {
        return collect(config('compose.services.php.packages'))->join(' ');
    }


    private function getExtList()
    {
        return collect(config('compose.services.php.extensions'))->join(' ');
    }

    private function getPhpMemoryLimit()
    {
        return config('compose.services.php.memory_limit');
    }

    public function getViteArgs()
    {
        return collect($this->getRemoteEnv()->explode("\n"))
            ->filter(fn($line) => str($line)->startsWith('VITE_'));
    }

    public function getViteBuildArgStringForDockerCommand()
    {
        return str($this->getViteArgs()->map(function ($value) {
            $value = str($value)->replace(' ', '\ ');
            return "--build-arg '{$value}'";
        })->join(' '))
            ->trim(' ');
    }

}