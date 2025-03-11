# Deployment Guide: The Give Hub

This guide explains how to deploy The Give Hub application using Docker and Docker Compose.

## Prerequisites

- Docker installed on your server
- Docker Compose installed on your server
- Git (to clone the repository)

## Deployment Steps

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/thegivehub.git
cd thegivehub
```

### 2. Configure Environment Variables

Edit the `.env` file to configure your environment variables:

```bash
# For production deployment, update these settings
cp .env.example .env
nano .env
```

Make sure to:
- Set a strong `JWT_SECRET` value
- Configure MongoDB credentials if needed
- Set `APP_ENV=production` for production environments
- Set `APP_DEBUG=false` for production environments

### 3. Build and Start the Docker Containers

```bash
# Make the docker-entrypoint.sh script executable
chmod +x docker-entrypoint.sh

# Start all services in detached mode
docker-compose up -d
```

This will:
- Build the PHP application container
- Start the MongoDB container
- Set up the required network and volumes
- Initialize the database with the required collections

### 4. Verify the Deployment

```bash
# Check if containers are running
docker-compose ps

# Check application logs
docker-compose logs -f app

# Check MongoDB logs
docker-compose logs -f mongodb
```

The application should now be accessible at `http://your-server-ip:8080`

### 5. Managing the Application

```bash
# Stop the application
docker-compose stop

# Start the application
docker-compose start

# Restart the application
docker-compose restart

# Completely remove containers, networks, and volumes
docker-compose down -v
```

## Database Management

### MongoDB Shell Access

```bash
docker-compose exec mongodb mongosh
```

### Database Backup

```bash
# Create a backup
docker-compose exec -T mongodb mongodump --archive --db=givehub > givehub_backup_$(date +%Y%m%d).archive
```

### Database Restore

```bash
# Restore from a backup
docker-compose exec -T mongodb mongorestore --archive --db=givehub < givehub_backup_file.archive
```

## Troubleshooting

### Application Container Issues

If the application container fails to start:

```bash
# Check for PHP errors
docker-compose logs app

# Enter the container for debugging
docker-compose exec app bash
```

### MongoDB Connection Issues

If the application can't connect to MongoDB:

```bash
# Check MongoDB logs
docker-compose logs mongodb

# Verify network connectivity
docker-compose exec app ping mongodb

# Test MongoDB connectivity with the test script
docker-compose exec app php test-mongodb.php

# Check if MongoDB PHP extension is installed
docker-compose exec app php -m | grep mongodb

# Verify MongoDB client library is installed
docker-compose exec app composer show | grep mongodb
```

If you're getting errors about missing MongoDB\Client or MongoDB\Model\BSONDocument classes:

1. Make sure the MongoDB PHP extension is installed:
   ```bash
   docker-compose exec app php -m | grep mongodb
   ```

2. Ensure the MongoDB PHP library is properly installed:
   ```bash
   docker-compose exec app composer require mongodb/mongodb
   ```

3. Check for any autoloading issues:
   ```bash
   docker-compose exec app composer dump-autoload
   ```

4. Restart the application container:
   ```bash
   docker-compose restart app
   ```

### File Permission Issues

If you encounter file permission issues:

```bash
# Fix permissions from inside the container
docker-compose exec app chown -R www-data:www-data /var/www/html/logs /var/www/html/img/avatars
```

## Security Considerations

For production deployments:

1. Use strong passwords for MongoDB
2. Generate a unique JWT_SECRET
3. Configure a reverse proxy (like Nginx) with SSL
4. Limit exposure of ports to only what's necessary
5. Regularly backup your database
6. Use network isolation for your containers
7. Keep your Docker and containers updated

## Updates and Maintenance

To update the application:

```bash
# Pull the latest code
git pull

# Rebuild and restart containers
docker-compose down
docker-compose build
docker-compose up -d
```
