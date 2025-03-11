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
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install zip

# Install MongoDB extension (more verbose to catch errors)
RUN pecl channel-update pecl.php.net && \
    pecl install mongodb && \
    docker-php-ext-enable mongodb

# Verify MongoDB extension is installed
RUN php -m | grep -q mongodb || (echo "MongoDB extension failed to install" && exit 1)

# Display PHP info for debugging
RUN php -i | grep mongo

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY composer.json composer.lock* ./

# Install composer dependencies
RUN composer install --no-scripts --no-autoloader --no-dev

# Copy application files
COPY . /var/www/html/

# Generate optimized autoloader
RUN composer dump-autoload --optimize

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod +x /var/www/html/docker-entrypoint.sh

# Configure Apache
RUN a2enmod rewrite
RUN sed -i 's!/var/www/html!/var/www/html/!g' /etc/apache2/sites-available/000-default.conf

# Create PHP info file for troubleshooting
RUN echo "<?php phpinfo(); ?>" > /var/www/html/phpinfo.php

# Expose port 80
EXPOSE 80

# Set the entrypoint script
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
