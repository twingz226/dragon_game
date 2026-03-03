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
    && sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf

# Create startup script using a heredoc to avoid echo escaping issues
RUN cat <<'EOF' > /start.sh
#!/bin/bash
set -e

echo "==> Starting DinoRace..."

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    echo "==> Generating APP_KEY..."
    php artisan key:generate --force
fi

# Wait for database connection if DB_HOST is set
if [ -n "$DB_HOST" ]; then
    echo "==> Waiting for database at $DB_HOST..."
    until php artisan db:show 2>/dev/null | grep -q "mysql\|sqlite\|pgsql"; do
        echo "    Database not ready, retrying in 2s..."
        sleep 2
    done
    echo "==> Database is ready!"
fi

# Run database migrations
echo "==> Running migrations..."
php artisan migrate --force --no-interaction

# Cache configurations for performance
echo "==> Caching config, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

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
