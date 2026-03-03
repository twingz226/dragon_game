# Multi-stage build for Laravel application on Railway
FROM node:24-alpine AS frontend-build

WORKDIR /var/www/html

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
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    gd \
    zip \
    bcmath \
    opcache \
    intl \
    mbstring \
    xml \
    curl \
    dom \
    filter \
    hash \
    json \
    session \
    tokenizer

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

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create .env file if it doesn't exist
RUN if [ ! -f .env ]; then cp .env.example .env; fi

# Optimize Laravel for production
RUN php artisan config:clear \
    && php artisan config:cache \
    && php artisan route:clear \
    && php artisan route:cache \
    && php artisan view:clear \
    && php artisan view:cache \
    && php artisan optimize:clear

# Configure Apache for Laravel and Railway port
RUN sed -i 's/DocumentRoot \/var\/www\/html/DocumentRoot \/var\/www\/html\/public/' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf

# Create startup script
RUN echo '#!/bin/bash\n\
# Wait for database connection if needed\n\
if [ ! -z "$DB_HOST" ]; then\n\
    echo "Waiting for database connection..."\n\
    until php artisan db:show --database=laravel 2>/dev/null; do\n\
        sleep 2\n\
    done\n\
    echo "Database is ready!"\n\
fi\n\
\n\
# Run database migrations\n\
php artisan migrate --force --no-interaction\n\
\n\
# Clear and cache configurations\n\
php artisan config:cache\n\
php artisan route:cache\n\
php artisan view:cache\n\
\n\
# Start Apache in foreground\n\
apache2-foreground' > /start.sh && chmod +x /start.sh

# Expose port for Railway
EXPOSE 8080

# Set environment variables
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV APACHE_LOG_DIR=/var/log/apache2
ENV APACHE_LOCK_DIR=/var/lock/apache2
ENV APACHE_PID_FILE=/var/run/apache2/apache2.pid

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:8080/ || exit 1

# Start the application
CMD ["/start.sh"]
