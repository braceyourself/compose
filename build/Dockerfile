FROM php:8.3-fpm
RUN apt-get update && apt-get install -y git

USER root
ENV PATH="/var/www/.composer/vendor/bin:$PATH"
ENV PHP_MEMORY_LIMIT=512M
WORKDIR /var/www/html
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin

RUN apt-get update \
    && apt install -y git ffmpeg jq iputils-ping poppler-utils wget \
    && rm -rf /var/lib/apt \
    && chmod +x /usr/local/bin/install-php-extensions && sync
    
RUN install-php-extensions gd bcmath mbstring opcache xsl imap zip ssh2 yaml pcntl intl sockets exif redis pdo_mysql pdo_pgsql sqlsrv pdo_sqlsrv soap @composer \
    && groupmod -og 1000 www-data \
    && usermod -u 1000 www-data
    
# create entrypoint file
COPY php_entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
