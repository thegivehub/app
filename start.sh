#!/bin/bash

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
  echo "Please run as root (use sudo)"
  exit 1
fi

# Set proper Node.js environment
export NODE_ENV=production

# Use the system's Node.js
NODE_PATH=$(which node)

# Get the actual user who invoked sudo
ACTUAL_USER=$(who am i | awk '{print $1}')
ACTUAL_HOME=$(getent passwd "$ACTUAL_USER" | cut -d: -f6)

# Use the actual user's npm configuration
export NPM_CONFIG_PREFIX="$ACTUAL_HOME/.npm-global"
export PATH="$NPM_CONFIG_PREFIX/bin:$PATH"

# Run Vite
cd /home/cdr/domains/thegivehub.com/frontend
$NODE_PATH /home/cdr/domains/thegivehub.com/frontend/node_modules/vite/bin/vite.js

