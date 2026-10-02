#!/bin/sh
set -e

# Ensure storage directories exist with appropriate permissions
mkdir -p /app/storage/framework/cache/data \
         /app/storage/framework/sessions \
         /app/storage/framework/views \
         /app/storage/logs \
         /app/storage/app/private \
         /app/storage/app/public \
         /app/bootstrap/cache

# A Railway volume must cover the complete private disk used by signatures,
# received documents and generated PDFs. Never silently use the container layer
# when Railway reports a volume that is missing or mounted at a different path.
if [ -n "${RAILWAY_VOLUME_MOUNT_PATH:-}" ]; then
    if [ "$RAILWAY_VOLUME_MOUNT_PATH" != "/app/storage/app/private" ] || ! mountpoint -q /app/storage/app/private; then
        echo "Persistent storage error: mount the Railway volume at /app/storage/app/private." >&2
        exit 1
    fi
elif [ -n "${RAILWAY_ENVIRONMENT:-}" ]; then
    echo "WARNING: Railway has no volume at /app/storage/app/private; uploaded files and PDFs are not persistent." >&2
fi

chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R 775 /app/storage /app/bootstrap/cache

# Create storage link if not present
php artisan storage:link --no-interaction || true

# Run database migrations. An absent or unusable database must stop the deployment.
if [ -z "${DB_CONNECTION:-}" ]; then
    echo "Database error: DB_CONNECTION is required." >&2
    exit 1
elif [ "$DB_CONNECTION" = "libsql" ]; then
    echo "Connecting to Turso libSQL and running database migrations..."
    php artisan migrate --force --no-interaction
elif [ -n "$DB_CONNECTION" ] && [ "$DB_CONNECTION" != "sqlite" ]; then
    echo "Running database migrations ($DB_CONNECTION)..."
    php artisan migrate --force --no-interaction
elif [ "$DB_CONNECTION" = "sqlite" ] && [ -n "${DB_DATABASE:-}" ] && [ -f "$DB_DATABASE" ]; then
    echo "Running SQLite migrations..."
    php artisan migrate --force --no-interaction
else
    echo "Database error: SQLite DB_DATABASE must point to an existing file." >&2
    exit 1
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
