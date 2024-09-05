#!/bin/bash

# throw errors
set -e

docker_compose=''

docker compose
if [ $? -eq 0 ]; then
    docker_compose="docker compose"
else
    docker_compose="docker-compose"
fi

# throw if no compose
if [ -z $docker_compose ]; then
    echo "docker-compose not found"
    exit 1
fi

service_name=php
old_container_id=$($docker_compose ps $service_name --format '{{json .ID}}' | tr -d '"' | tail -n1)

# bring a new container online, running new code
# (nginx continues routing to the old container only)
$docker_compose up -d --no-deps --scale $service_name=2 --no-recreate $service_name

new_container_id=$($docker_compose ps $service_name --format '{{json .ID}}' | tr -d '"' | tail -n1)
isHealthy(){
    echo $($docker_compose ps php --format '{{json .Health}} {{json .ID}}' | grep -qE '"healthy".*'$new_container_id && echo 'yes' || echo 'no')
}

# wait for new container to be available by checking the health
while [ "$(isHealthy)" != 'yes' ]; do
    sleep 1
done


# start routing requests to the new container (as well as the old)
$docker_compose exec nginx /usr/sbin/nginx -s reload

# take the old container offline
docker stop $old_container_id
docker rm $old_container_id

$docker_compose up -d --no-deps --scale $service_name=1 --no-recreate $service_name
# todo: remove this reload and set up a public volume
$docker_compose up -d nginx

# stop routing requests to the old container
$docker_compose exec nginx /usr/sbin/nginx -s reload

