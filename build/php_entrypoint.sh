#!/bin/bash

# wait for database
php /var/www/html/artisan compose:wait-for-database;

echo "Running startup commands for $SERVICE..."
php  /var/www/html/artisan compose:run-startup-commands "$SERVICE"

if [[ "$SERVICE" == "scheduler" ]]; then

    while true; do
      echo "Current time: " "$(date +"%r")"
      echo "running scheduled commands..."

      php /var/www/html/artisan schedule:run;

      echo "sleeping for a minute..."
      sleep 60s
    done

elif [[ "$SERVICE" == "php" ]]; then
    echo "Starting PHP service..."
    /usr/local/bin/docker-php-entrypoint -F;
fi