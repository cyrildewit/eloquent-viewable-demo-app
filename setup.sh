#!/bin/bash

function printline {
    echo $1;
}

function setupEnvFile() {
    if ! [ -f ".env" ];
    then
        ENVIRONMENT_FILE=".env.docker";
        printline "==[ Copying $ENVIRONMENT_FILE to .env"
        cp $ENVIRONMENT_FILE .env

        printline "==[ Install composer"
        runScript composer install

        printline "==[ Generating new app key"
        runScript php artisan key:generate

        printline "==[ Run migrations"
        runScript php artisan migrate

        printline "==[ Create storage link"
        runScript php artisan storage:link
    fi
}

function runScript {
    docker exec -it evd-php "$@";
}

printline "==[ DOCKER INIT ]==";
docker-compose --env-file .env.docker up -d

setupEnvFile;

printline "==[ All checks are passed, starting with the setup"

printline "==[ Setting permissions for html directory"
runScript chown -R www-data:www-data /var/www/html

printline "==[ Install composer"
runScript composer install

printline "==[ Run migrations"
runScript php artisan migrate

printline "==[ Run NPM"
runScript npm install
runScript npm run dev
