# Multi-stage build for Laravel application on Railway
FROM node:24-alpine AS frontend-build

WORKDIR /var/www/html

# Set NODE_OPTIONS to increase memory limit for Node.js
ENV NODE_OPTIONS="--max-old-space-size=4096"

# Copy package files
COPY package*.json ./

# Install Node.js dependencies (including dev dependencies for build)
RUN npm ci

# Copy frontend source files
COPY resources/js/ resources/js/
COPY resources/css/ resources/css/
COPY vite.config.js ./

# Build frontend assets
RUN npm run build

# Production stage
FROM php:8.2-apache-bullseye

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libwebp-dev \
    libxpm-dev \
    zip \
    unzip \
    git \
    curl \
    libpq-dev \
    libsqlite3-dev \
    libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions separately for better error handling
RUN docker-php-ext-install -j$(nproc) pdo_mysql pdo_sqlite
RUN docker-php-ext-install -j$(nproc) zip bcmath opcache intl
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install -j$(nproc) gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache modules
RUN a2enmod rewrite headers

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY . .

# Copy built frontend assets from previous stage
COPY --from=frontend-build /var/www/html/public/build/ public/build/

# Ensure directories exist
RUN mkdir -p bootstrap/cache storage/framework/sessions storage/framework/views storage/framework/cache

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Create .env file if it doesn't exist
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# Configure Apache for Laravel and Railway port
RUN sed -i 's/DocumentRoot \/var\/www\/html/DocumentRoot \/var\/www\/html\/public/' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf

# Add Directory directive for Laravel public — essential for .htaccess / mod_rewrite
RUN echo '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>' >> /etc/apache2/sites-available/000-default.conf

# Create startup script using a heredoc to avoid echo escaping issues
RUN cat <<'EOF' > /start.sh
#!/bin/bash
# Do NOT use set -e — we want Apache to start even if migrations fail

echo "==> Starting DinoRace..."

# Ensure the SQLite DB lives in a writable location
export DB_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
if [ ! -f "$DB_DATABASE" ]; then
    echo "==> Creating SQLite database at $DB_DATABASE..."
    touch "$DB_DATABASE"
    chown www-data:www-data "$DB_DATABASE"
fi

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    echo "==> Generating APP_KEY..."
    php artisan key:generate --force || echo "WARNING: key:generate failed, continuing..."
fi

# Wait for database connection if DB_HOST is set (MySQL/Postgres mode)
if [ -n "$DB_HOST" ]; then
    echo "==> Waiting for database at $DB_HOST..."
    MAX_RETRIES=30
    COUNT=0
    until php artisan db:show 2>/dev/null | grep -q "mysql\|pgsql" || [ $COUNT -ge $MAX_RETRIES ]; do
        echo "    Database not ready, retrying in 2s... ($COUNT/$MAX_RETRIES)"
        sleep 2
        COUNT=$((COUNT+1))
    done
    if [ $COUNT -ge $MAX_RETRIES ]; then
        echo "WARNING: Database never became ready, proceeding anyway..."
    else
        echo "==> Database is ready!"
    fi
fi

# Run database migrations (non-fatal — app still boots without migrations)
echo "==> Running migrations..."
php artisan migrate --force --no-interaction || echo "WARNING: Migrations failed, continuing..."

# Cache configurations for performance (non-fatal)
echo "==> Caching config and views..."
php artisan config:cache || echo "WARNING: config:cache failed"
# NOTE: route:cache CANNOT be used with closure-based routes — skip it
php artisan route:clear  || true
php artisan view:cache   || echo "WARNING: view:cache failed"

echo "==> Starting Apache on port 8080..."
exec apache2-foreground
EOF
RUN chmod +x /start.sh

# Set environment variables
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV APACHE_LOG_DIR=/var/log/apache2
ENV APACHE_LOCK_DIR=/var/lock/apache2
ENV APACHE_PID_FILE=/var/run/apache2/apache2.pid

# Expose port for Railway
EXPOSE 8080

# Start the application
CMD ["/bin/bash", "/start.sh"]
