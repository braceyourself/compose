#!/bin/bash

php /var/www/html/artisan optimize;

if [[ "$SERVICE" == "scheduler" ]]; then
    php  /var/www/html/artisan compose:run-startup-commands $SERVICE

    while true; do
      echo "Current time: " "$(date +"%r")"
      echo "running scheduled commands..."

      php /var/www/html/artisan schedule:run;

      echo "sleeping for a minute..."
      sleep 60s
    done

elif [[ "$SERVICE" == "php" ]]; then
    php /var/www/html/artisan storage:link
    php /var/www/html/artisan migrate --force

    #composer dump-autoload

    php  /var/www/html/artisan compose:run-startup-commands $SERVICE

    /usr/local/bin/docker-php-entrypoint -F;
else
    php  /var/www/html/artisan compose:run-startup-commands $SERVICE
fi
