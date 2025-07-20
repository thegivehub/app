#!/bin/bash
set -e

# Update package list
apt-get update

# Install PHP 8.3 CLI and required extensions
apt-get install -y php8.3-cli php8.3-mongodb php8.3-curl php8.3-xml php8.3-mbstring curl tar

# Install composer dependencies using PHP 8.3
php8.3 $(which composer) install --no-interaction

# Download and start a local MongoDB server for testing
MONGO_VERSION=7.0.4
MONGO_URL="https://fastdl.mongodb.org/linux/mongodb-linux-x86_64-ubuntu2204-${MONGO_VERSION}.tgz"
TMP_DIR=/tmp/mongodb
mkdir -p "$TMP_DIR"
if [ ! -f "$TMP_DIR/mongod" ]; then
    curl -L "$MONGO_URL" -o /tmp/mongodb.tgz
    mkdir -p "$TMP_DIR/bin"
    tar -xzf /tmp/mongodb.tgz -C "$TMP_DIR" --strip-components=1
fi
mkdir -p /tmp/mongo-data
"$TMP_DIR/bin/mongod" --dbpath /tmp/mongo-data --logpath /tmp/mongodb.log --fork
