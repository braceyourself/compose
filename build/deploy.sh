#!/bin/bash

service_name=php
old_container_id=$(docker-compose ps $service_name --format '{{json .ID}}' | tr -d '"' | tail -n1)

# bring a new container online, running new code
# (nginx continues routing to the old container only)
docker-compose up -d --no-deps --scale $service_name=2 --no-recreate $service_name

new_container_id=$(docker-compose ps $service_name --format '{{json .ID}}' | tr -d '"' | tail -n1)
isHealthy(){
    echo $(docker-compose ps php --format '{{json .Health}} {{json .ID}}' | grep -qE '"healthy".*'$new_container_id && echo 'yes' || echo 'no')
}

# wait for new container to be available by checking the health
health=$(isHealthy)
while [ "$health" != 'yes' ]; do
    sleep 1
    health=$(isHealthy)
done


# start routing requests to the new container (as well as the old)
docker-compose exec nginx /usr/sbin/nginx -s reload

# take the old container offline
docker stop $old_container_id
docker rm $old_container_id

docker-compose up -d --no-deps --scale $service_name=1 --no-recreate $service_name

# stop routing requests to the old container
docker-compose exec nginx /usr/sbin/nginx -s reload

