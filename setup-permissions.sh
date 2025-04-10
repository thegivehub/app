#!/bin/bash
#
# This script sets up the necessary permissions and directories for testing
# the face comparison functionality in TheGiveHub.
#
# It performs the following tasks:
# 1. Makes the test-face-compare.php script executable
# 2. Creates necessary upload directories if they don't exist
# 3. Sets proper permissions on the upload directories

echo "Setting up permissions and directories for face comparison testing..."

# Check if test script exists
if [ -f "test-face-compare.php" ]; then
    echo "Making test-face-compare.php executable..."
    chmod +x test-face-compare.php
else
    echo "Error: test-face-compare.php not found in current directory!"
    echo "Make sure you're running this script from the same directory as test-face-compare.php"
    exit 1
fi

# Create uploads directory if it doesn't exist
echo "Creating uploads directory structure..."
if [ ! -d "uploads" ]; then
    mkdir -p uploads
    echo "Created uploads directory"
fi

# Create subdirectories for selfies and documents
if [ ! -d "uploads/selfies" ]; then
    mkdir -p uploads/selfies
    echo "Created uploads/selfies directory"
fi

if [ ! -d "uploads/documents" ]; then
    mkdir -p uploads/documents
    echo "Created uploads/documents directory"
fi

# Set permissions on uploads directory
echo "Setting permissions on uploads directory..."
chgrp www-data uploads
chmod 775 uploads

echo "Setting permissions on uploads/selfies directory..."
chgrp www-data uploads/selfies
chmod 775 uploads/selfies

echo "Setting permissions on uploads/documents directory..."
chgrp www-data uploads/documents
chmod 775 uploads/documents

echo "Setup complete!"
echo "You can now run the test script with: ./test-face-compare.php path/to/selfie.jpg path/to/document.jpg" 
