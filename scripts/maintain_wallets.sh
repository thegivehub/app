#!/bin/bash
# Wallet Maintenance Script
# This script runs the wallet cleanup and balance update processes
# It can be added to cron for regular execution

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
APP_DIR="$(dirname "$SCRIPT_DIR")"
LOG_DIR="$APP_DIR/logs"

# Ensure log directory exists
mkdir -p "$LOG_DIR"

# Define log file
LOG_FILE="$LOG_DIR/wallet_maintenance.log"

# Log function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Start maintenance process
log "Starting wallet maintenance process"

# Run wallet cleanup (only on first day of month)
if [ "$(date '+%d')" = "01" ]; then
    log "Running wallet cleanup process (monthly task)"
    php "$SCRIPT_DIR/cleanup_and_update_wallets.php" >> "$LOG_FILE" 2>&1
    if [ $? -ne 0 ]; then
        log "ERROR: Wallet cleanup process failed"
    else
        log "Wallet cleanup process completed successfully"
    fi
fi

# Run wallet balance update (daily)
log "Running wallet balance update process"
php "$SCRIPT_DIR/update_wallet_balances.php" >> "$LOG_FILE" 2>&1
if [ $? -ne 0 ]; then
    log "ERROR: Wallet balance update process failed"
else
    log "Wallet balance update process completed successfully"
fi

log "Wallet maintenance process completed"