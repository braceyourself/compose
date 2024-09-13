#!/bin/bash

php /var/www/html/artisan optimize;

if [[ "$SERVICE" == "scheduler" ]]; then
    while true; do
      echo "Current time: " "$(date +"%r")"
      echo "running scheduled commands..."

      php /var/www/html/artisan schedule:run;

      echo "sleeping for a minute..."
      sleep 60;
    done
fi

echo "$COMPOSE_DOMAIN"

if [[ "$SERVICE" == "reverb" ]]; then
  php /var/www/html/artisan reverb:start --host="0.0.0.0" --hostname="reverb";
fi

if [[ "$SERVICE" == "horizon" ]]; then
  php /var/www/html/artisan horizon;
fi

if [[ "$SERVICE" == "websockets" ]]; then
  php /var/www/html/artisan websockets:serve;
fi

if [[ "$SERVICE" == "php" ]]; then
  php /var/www/html/artisan storage:link
  php /var/www/html/artisan migrate --force

  #composer dump-autoload

  php artisan config:clear
  php artisan clear
  php artisan clear-compiled
  php artisan storage:link && chown www-data: public/storage

  /usr/local/bin/docker-php-entrypoint -F;
fi
