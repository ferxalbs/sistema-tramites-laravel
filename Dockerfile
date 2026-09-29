# ==============================================================
# Production Dockerfile for Laravel + React (Inertia) on Railway
# Powered by FrankenPHP (Modern Caddy-based application server)
# ==============================================================

FROM dunglas/frankenphp:1-php8.5-bookworm

# Environment settings
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    NODE_ENV=production \
    APP_ENV=production \
    PORT=80

# Install production PHP extensions (including ffi for Turso libSQL)
RUN install-php-extensions \
    ffi \
    pdo_pgsql \
    pdo_mysql \
    bcmath \
    zip \
    intl \
    opcache \
    pcntl \
    redis \
    && echo "ffi.enable=true" > /usr/local/etc/php/conf.d/turso-ffi.ini

# Install Node.js 22 LTS & pnpm for frontend asset compilation
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && corepack enable \
    && corepack prepare pnpm@12.6.0 --activate \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

# 1. Install PHP dependencies first (cached layer)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# 2. Install Node.js dependencies (cached layer)
COPY package.json pnpm-lock.yaml* pnpm-workspace.yaml* ./
RUN pnpm install --frozen-lockfile

# 3. Copy application codebase
COPY . .

# 4. Finalize Composer dump-autoload
RUN composer dump-autoload --optimize --no-dev

# 5. Build frontend assets with Vite/pnpm and clean up node_modules to keep image lean
RUN php artisan wayfinder:generate --with-form \
    && pnpm run build \
    && rm -rf node_modules

# 6. Configure Caddy & entrypoint
COPY Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# 7. Configure storage permissions
RUN mkdir -p /app/storage /app/bootstrap/cache \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

EXPOSE 80 443

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
