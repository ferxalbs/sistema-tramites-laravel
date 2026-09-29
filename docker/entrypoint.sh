#!/bin/sh
set -e

# Ensure storage directories exist with appropriate permissions
mkdir -p /app/storage/framework/cache/data \
         /app/storage/framework/sessions \
         /app/storage/framework/views \
         /app/storage/logs \
         /app/storage/app/public \
         /app/bootstrap/cache

chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R 775 /app/storage /app/bootstrap/cache

# Create storage link if not present
php artisan storage:link --no-interaction || true

# Run database migrations if configured
if [ "$DB_CONNECTION" = "libsql" ]; then
    echo "Connecting to Turso libSQL and running database migrations..."
    php artisan migrate --force --no-interaction || true
elif [ -n "$DB_CONNECTION" ] && [ "$DB_CONNECTION" != "sqlite" ]; then
    echo "Running database migrations ($DB_CONNECTION)..."
    php artisan migrate --force --no-interaction || true
elif [ "$DB_CONNECTION" = "sqlite" ] && [ -f "$DB_DATABASE" ]; then
    echo "Running SQLite migrations..."
    php artisan migrate --force --no-interaction || true
fi

# Cache configuration, routes, and views for production performance
if [ "$APP_ENV" = "production" ]; then
    echo "Caching Laravel configuration, routes and views..."
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction
    php artisan event:cache --no-interaction || true
fi

echo "Starting FrankenPHP on port ${PORT:-80}..."
exec frankenphp run --config /etc/caddy/Caddyfile
