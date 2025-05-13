FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    git \
    libssl-dev \
    netcat-openbsd \
    libcurl4-openssl-dev \
    pkg-config \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
RUN docker-php-ext-install zip pcntl

# Install MongoDB extension via PECL and enable it
RUN pecl install mongodb-1.15.0 && \
    docker-php-ext-enable mongodb

# Verify MongoDB extension
RUN php -m | grep -q mongodb || (echo "MongoDB extension failed to install" && exit 1)

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy MongoDB test script
COPY test-mongodb.php /var/www/html/test-mongodb.php

# Copy composer files
COPY composer.json composer.lock* ./

# Install composer dependencies (no dev, no scripts, no autoloader)
RUN composer install --no-scripts --no-autoloader --no-dev

# Copy the rest of the app
COPY . /var/www/html/

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Apache & file permissions
RUN a2enmod rewrite
RUN chown -R www-data:www-data /var/www/html
RUN chmod +x /var/www/html/docker-entrypoint.sh
RUN git config --global --add safe.directory /var/www/html

# PHP info file for debugging (optional)
RUN echo "<?php phpinfo(); ?>" > /var/www/html/phpinfo.php

# Expose port
EXPOSE 80

# Entrypoint
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]

