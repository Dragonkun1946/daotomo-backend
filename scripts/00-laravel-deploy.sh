#!/usr/bin/env bash
set -e

echo "==> [DaoTomo Render Deployment] Initializing Laravel..."

cd /var/www/html

echo "==> Setting up storage directories and permissions..."
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache \
         public/uploads/skins

if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    if [ ! -f database/database.sqlite ]; then
        echo "==> Initializing database.sqlite..."
        touch database/database.sqlite
    fi
    chown www-data:www-data database/database.sqlite 2>/dev/null || true
fi

chown -R www-data:www-data storage bootstrap/cache public/uploads 2>/dev/null || true
chmod -R 775 storage bootstrap/cache public/uploads 2>/dev/null || true

echo "==> Running safe database migrations (php artisan migrate --force)..."
php artisan migrate --force

echo "==> Caching Laravel configuration, routes, and views..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "==> [DaoTomo Render Deployment] Ready to accept traffic!"
