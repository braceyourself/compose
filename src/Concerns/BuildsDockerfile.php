<?php

namespace Braceyourself\Compose\Concerns;

use Illuminate\Support\Facades\Process;

trait BuildsDockerfile
{
    private function createDockerfile()
    {
        file_put_contents(__DIR__.'/../../build/Dockerfile', $this->getDockerfile());
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
            
        COPY php_entrypoint.sh /usr/local/bin/entrypoint.sh
        RUN chmod +x /usr/local/bin/entrypoint.sh \
            && chown -R www-data:www-data /var/www/html \
            && chown -R www-data:www-data /var/www

        USER www-data
        
        ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
        
        ### npm
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
        
        # set node user and group id
        RUN groupmod -og {$this->getGroupId()} node \
            && usermod -u {$this->getUserId()} -g {$this->getGroupId()} -d /var/www node \
            && chown -R node:node /var/www
        
        USER node
        
        COPY --chown=node:node app.tar /var/www
        
        RUN rm -rf /var/www/.npm \
            && tar -xf /var/www/app.tar "./package.json" \
            && tar -xf /var/www/app.tar "./package-lock.json" \
            && npm install \
            && tar -xf /var/www/app.tar "./tailwind.config.js" \
            && tar -xf /var/www/app.tar "./postcss.config.js" \
            && tar -xf /var/www/app.tar "./vite.config.js" \
            && tar -xf /var/www/app.tar "./resources" \
            && tar -xf /var/www/app.tar "./public" \
            && npm run build
        
         
        ### app
        FROM php AS app
        
        ADD app.tar /var/www/html
        COPY --chown=www-data:www-data --from=npm /var/www/html/public /var/www/html/public
        
        ### nginx
        FROM nginx AS nginx
        COPY nginx.conf /etc/nginx/templates/default.conf.template
        COPY --from=app /var/www/html/public /var/www/html/public
        
        ### production
        FROM app AS production
        
        RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader
        
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

}