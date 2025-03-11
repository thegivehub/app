#!/bin/bash
set -e

# Function to check MongoDB PHP extension
check_mongodb_extension() {
    if php -m | grep -q mongodb; then
        echo "✅ MongoDB extension is installed and enabled"
    else
        echo "❌ MongoDB extension is NOT installed. Attempting to install..."
        pecl install mongodb
        docker-php-ext-enable mongodb
        
        if php -m | grep -q mongodb; then
            echo "✅ MongoDB extension successfully installed"
        else
            echo "❌ Failed to install MongoDB extension"
            exit 1
        fi
    fi
}

# Function to check MongoDB PHP library
check_mongodb_library() {
    if composer show | grep -q "mongodb/mongodb"; then
        echo "✅ MongoDB PHP library is installed"
    else
        echo "❌ MongoDB PHP library is NOT installed. Installing..."
        composer require mongodb/mongodb:^1.15
        
        if composer show | grep -q "mongodb/mongodb"; then
            echo "✅ MongoDB PHP library successfully installed"
        else
            echo "❌ Failed to install MongoDB PHP library"
            exit 1
        fi
    fi
}

# Verify PHP extensions and libraries
echo "Checking MongoDB PHP extension..."
check_mongodb_extension

echo "Checking MongoDB PHP library..."
check_mongodb_library

echo "Running composer dump-autoload..."
composer dump-autoload --optimize

# Wait for MongoDB to be ready
echo "Waiting for MongoDB to be ready..."
timeout=60
while ! nc -z mongodb 27017; do
    if [ $timeout -le 0 ]; then
        echo "MongoDB connection timed out"
        exit 1
    fi
    timeout=$(($timeout - 1))
    sleep 1
done
echo "✅ MongoDB is reachable"

# Create necessary directories with proper permissions
mkdir -p /var/www/html/logs
mkdir -p /var/www/html/img/avatars
chown -R www-data:www-data /var/www/html/logs
chown -R www-data:www-data /var/www/html/img/avatars

# Test MongoDB connection
echo "Testing MongoDB connection..."
which php
php /var/www/html/test-mongodb.php > /var/www/html/logs/mongodb-test.log 2>&1
if [ $? -eq 0 ]; then
    echo "✅ MongoDB connection test successful"
else
    echo "❌ MongoDB connection test failed. Check logs for details."
    cat /var/www/html/logs/mongodb-test.log
fi

# Start Apache in foreground
echo "Starting Apache..."
apache2-foreground
