#!/bin/bash
#
# Builds and starts the Inkwell demo for Eloquent Viewable 9.x.
#
# Expects the package checked out next to this app, at ../eloquent-viewable,
# on the 9.x branch, and an external `traefik-gateway` network with Traefik
# serving *.localhost (see the README).

set -e

function step {
    echo "==[ $1"
}

function run {
    docker exec -it evd-php "$@"
}

if [ ! -d "../eloquent-viewable" ]; then
    echo "The package is not at ../eloquent-viewable. Clone it there and check out the 9.x branch:"
    echo "  git clone https://github.com/cyrildewit/eloquent-viewable.git ../eloquent-viewable && git -C ../eloquent-viewable checkout 9.x"
    exit 1
fi

if [ ! -f ".env" ]; then
    step "Copying .env.docker to .env"
    cp .env.docker .env
fi

step "Starting the containers"
docker compose --env-file .env.docker up -d --build

step "Installing Composer packages (the package is symlinked from ../eloquent-viewable)"
run composer install

if ! grep -q '^APP_KEY=base64' .env; then
    step "Generating an app key"
    run php artisan key:generate
fi

step "Building the frontend"
run npm install --no-audit --no-fund
run npm run build

step "Migrating and simulating two years of traffic"
run php artisan demo:reset

step "Restarting Horizon and the scheduler so they pick up the code"
docker compose --env-file .env.docker restart horizon scheduler

step "Done: https://eloquent-viewable-demo.localhost/tour"
