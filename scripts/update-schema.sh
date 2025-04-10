#!/bin/bash

# MongoDB Schema Update Script
# This script runs the MongoDB schema update script

# Get the directory of this script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PARENT_DIR="$(dirname "$SCRIPT_DIR")"

echo "Running MongoDB schema update script..."

# Configuration - update these values for your environment
MONGO_HOST="localhost"
MONGO_PORT="27017"
MONGO_DB="thegivehub"
MONGO_USER=""
MONGO_PASS=""

# Build the connection string
CONNECTION_STRING="mongodb://$MONGO_HOST:$MONGO_PORT/$MONGO_DB"
if [ ! -z "$MONGO_USER" ] && [ ! -z "$MONGO_PASS" ]; then
  CONNECTION_STRING="mongodb://$MONGO_USER:$MONGO_PASS@$MONGO_HOST:$MONGO_PORT/$MONGO_DB"
fi

# Run the MongoDB script using mongo shell
echo "Connecting to MongoDB at $MONGO_HOST:$MONGO_PORT..."
mongosh "$CONNECTION_STRING" "$SCRIPT_DIR/update-documents-schema.js"

# Check the exit status
if [ $? -eq 0 ]; then
  echo "Schema update completed successfully!"
else
  echo "Error: Schema update failed with exit code $?"
  exit 1
fi

echo "Done!"
exit 0 
