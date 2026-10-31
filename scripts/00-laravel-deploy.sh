#!/usr/bin/env bash
set -e

echo "==> [DaoTomo Render Deployment] Initializing Laravel..."

cd /var/www/html

echo "==> Setting up storage directories, database directory, and permissions..."
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         bootstrap/cache \
         public/uploads/skins \
         database

# Ensure .env exists from .env.example
if [ ! -f .env ]; then
    echo "==> Creating .env from .env.example..."
    cp .env.example .env
fi

# Ensure APP_KEY exists
if [ -z "$APP_KEY" ]; then
    echo "==> WARNING: APP_KEY not provided in environment! Generating application key..."
    php artisan key:generate --force
fi

# Ensure cache store is file to avoid pooler transaction locks
if [ -f .env ]; then
    sed -i 's/^CACHE_STORE=.*/CACHE_STORE=file/' .env 2>/dev/null || true
    if ! grep -q "^CACHE_STORE=" .env; then
        echo "CACHE_STORE=file" >> .env
    fi
fi

# Auto-detect database connection
if [ -n "$DATABASE_URL" ]; then
    echo "==> PostgreSQL DATABASE_URL detected. Using pgsql."
    export DB_CONNECTION=pgsql
    if [ -f .env ]; then
        sed -i '/^DB_CONNECTION=/d' .env 2>/dev/null || true
        echo "DB_CONNECTION=pgsql" >> .env
        sed -i '/^DATABASE_URL=/d' .env 2>/dev/null || true
        printf 'DATABASE_URL="%s"\n' "$DATABASE_URL" >> .env
    fi
else
    echo "==> No PostgreSQL DATABASE_URL detected. Configuring SQLite database..."
    export DB_CONNECTION=sqlite
    if [ ! -f database/database.sqlite ]; then
        echo "==> Creating database/database.sqlite..."
        touch database/database.sqlite
    fi
    chmod 666 database/database.sqlite 2>/dev/null || true
fi

# Set directory permissions for www-data (including database directory for SQLite locks)
chown -R www-data:www-data storage bootstrap/cache public/uploads database 2>/dev/null || true
chmod -R 775 storage bootstrap/cache public/uploads database 2>/dev/null || true

echo "==> Running safe database migrations (php artisan migrate --force)..."
php artisan migrate --force

echo "==> Seeding initial products and data (idempotent seeder)..."
php artisan db:seed --force || true

echo "==> Caching Laravel configuration, routes, and views..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "==> [DaoTomo Render Deployment] Ready to accept traffic!"
