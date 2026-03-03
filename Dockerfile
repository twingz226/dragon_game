# Multi-stage build for Laravel application on Railway
FROM node:24-alpine AS frontend-build

WORKDIR /var/www/html

# Define ARGs for Railway build variables so Vite will bundle them
ARG VITE_REVERB_APP_KEY
ARG REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG REVERB_HOST
ARG VITE_REVERB_PORT
ARG REVERB_PORT
ARG VITE_REVERB_SCHEME
ARG REVERB_SCHEME

# Pass them as environment variables during build
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY:-$REVERB_APP_KEY}
ENV VITE_REVERB_HOST=${VITE_REVERB_HOST:-$REVERB_HOST}
ENV VITE_REVERB_PORT=${VITE_REVERB_PORT:-$REVERB_PORT}
ENV VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME:-$REVERB_SCHEME}

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
RUN apt-get update && apt-get install -y --no-install-recommends \
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

# Copy startup script
COPY start.sh /start.sh
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
