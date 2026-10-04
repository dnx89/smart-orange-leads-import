#!/bin/sh
set -e

[ -f .env ] || cp .env.example .env

#composer.lock -nt vendor/autoload.php 
if [ ! -f vendor/autoload.php ] || [ -n "$(find composer.lock -newer vendor/autoload.php 2>/dev/null)" ]; then
	composer install --no-interaction --prefer-dist
fi

grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

if [ "$RUN_MIGRATIONS" = "true" ]; then
	php artisan migrate --force
	php artisan db:seed --force
	DB_DATABASE="${DB_DATABASE}_test" php artisan migrate --force
fi

exec "$@"
