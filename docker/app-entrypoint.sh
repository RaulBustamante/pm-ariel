#!/bin/sh
set -eu

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    /backups

chown -R www-data:www-data storage bootstrap/cache /backups

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    su-exec www-data php artisan migrate --force
fi

su-exec www-data php artisan config:cache
su-exec www-data php artisan route:cache
su-exec www-data php artisan view:cache

if [ "$1" = "php" ]; then
    exec su-exec www-data "$@"
fi

exec "$@"

