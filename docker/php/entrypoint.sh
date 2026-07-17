#!/bin/sh
set -e
cd /var/www/html
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache storage/app/public/imagine
chown -R www-data:www-data storage bootstrap/cache database 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true
touch database/database.sqlite 2>/dev/null || true
php artisan config:clear || true
php artisan migrate --force --no-interaction 2>/dev/null || true
php artisan storage:link 2>/dev/null || true
exec docker-php-entrypoint "$@"
