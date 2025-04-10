#!/bin/bash
# Script to migrate base64 images from MongoDB to filesystem
#
# Usage: ./migrate_images.sh [options]
#
# Options:
#   --dry-run   Run in test mode without saving files or updating database
#   --debug     Enable additional debug output
#   --admin     Run as admin (bypasses token authentication)
#   --help      Show this help message

# Parse command line arguments
DRY_RUN=0
DEBUG=0
ADMIN=1  # Default to admin mode for migration

show_help() {
    echo "Usage: $0 [options]"
    echo
    echo "Options:"
    echo "  --dry-run   Run in test mode without saving files or updating database"
    echo "  --debug     Enable additional debug output"
    echo "  --admin     Run as admin (bypasses token authentication)"
    echo "  --help      Show this help message"
    exit 0
}

for arg in "$@"; do
    case $arg in
        --dry-run)
            DRY_RUN=1
            shift
            ;;
        --debug)
            DEBUG=1
            shift
            ;;
        --admin)
            ADMIN=1
            shift
            ;;
        --help)
            show_help
            ;;
        *)
            echo "Unknown option: $arg"
            show_help
            ;;
    esac
done

# Set variables
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
BASE_DIR="$(dirname "$SCRIPT_DIR")"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
LOG_FILE="$BASE_DIR/logs/image_migration_${TIMESTAMP}.log"
SCRIPT_PATH="$SCRIPT_DIR/migrate_images.php"

# Build command with options
PHP_CMD="php $SCRIPT_PATH"
if [ $DRY_RUN -eq 1 ]; then
    PHP_CMD="$PHP_CMD --dry-run"
fi
if [ $DEBUG -eq 1 ]; then
    PHP_CMD="$PHP_CMD --debug"
fi
if [ $ADMIN -eq 1 ]; then
    PHP_CMD="$PHP_CMD --admin"
fi

# Create logs directory if it doesn't exist
mkdir -p "$BASE_DIR/logs"

# Display start message
echo "===== Starting Image Migration ====="
if [ $DRY_RUN -eq 1 ]; then
    echo "*** DRY RUN MODE - No files will be saved and no database changes will be made ***"
fi
if [ $DEBUG -eq 1 ]; then
    echo "*** DEBUG MODE ENABLED ***"
fi
if [ $ADMIN -eq 1 ]; then
    echo "*** ADMIN MODE ENABLED - Token authentication bypassed ***"
fi
echo "Log file: $LOG_FILE"
echo "$(date): Starting migration" > "$LOG_FILE"
echo "Command: $PHP_CMD" >> "$LOG_FILE"

# Run the PHP script
cd "$BASE_DIR"
eval "$PHP_CMD" | tee -a "$LOG_FILE"

# Display completion message
echo "===== Migration Completed ====="
echo "Migration log saved to: $LOG_FILE"
echo "$(date): Migration completed" >> "$LOG_FILE" 