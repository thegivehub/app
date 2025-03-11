#!/bin/bash
set -e

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

# Create necessary directories with proper permissions
mkdir -p /var/www/html/logs
mkdir -p /var/www/html/img/avatars
chown -R www-data:www-data /var/www/html/logs
chown -R www-data:www-data /var/www/html/img/avatars

# Run database setup
echo "Running database setup..."
php /var/www/html/lib/setup.php

# Start Apache in foreground
echo "Starting Apache..."
apache2-foreground
