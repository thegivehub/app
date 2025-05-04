# Wallet Maintenance System

## Overview

The Give Hub platform integrates with the Stellar blockchain to manage cryptocurrency wallets for users and campaigns. To ensure proper synchronization between our database and the Stellar network, we've implemented an automated wallet maintenance system.

This document outlines the components and processes involved in wallet maintenance.

## Key Components

### 1. Wallet Cleanup Script

**Purpose**: Identifies and removes orphaned wallets (those without valid user or campaign associations) and updates balances from Stellar.

**File**: `/scripts/cleanup_and_update_wallets.php`

**Features**:
- Identifies wallets without valid user or campaign references
- Removes orphaned wallets that are no longer needed
- Updates wallet balances from the Stellar testnet for valid wallets
- Provides detailed logging of all operations
- Supports dry-run mode for testing without making changes

### 2. Wallet Balance Update Script

**Purpose**: Updates wallet balances from the Stellar network without removing any wallets.

**File**: `/scripts/update_wallet_balances.php`

**Features**:
- Queries the Stellar network for current balances of all wallets
- Updates balance information in the MongoDB database
- Sets balance to 0 for unactivated accounts
- Maintains a log of all balance updates

### 3. Maintenance Shell Script

**Purpose**: Provides a convenient wrapper for the PHP scripts to be used in cron jobs.

**File**: `/scripts/maintain_wallets.sh`

**Features**:
- Runs the wallet balance update process daily
- Runs the wallet cleanup process monthly (on the first day of the month)
- Maintains separate logs for each process
- Provides informative output and error handling

## Implementation Details

### Database Integration

The scripts interface with MongoDB through the following collections:
- `wallets`: Stores wallet information including publicKey, secretKey, and balance
- `users`: Linked to wallets through userId
- `campaigns`: Linked to wallets through campaignId

### Stellar Network Integration

The system interacts with the Stellar Horizon API to:
- Fetch account balances
- Check account activation status
- Retrieve transaction history when needed

By default, the system is configured to use the Stellar testnet, but can be adjusted to work with the public network.

### Logging

All maintenance operations are logged to:
- `/logs/wallet_maintenance.log`: Main maintenance log
- `/logs/wallet_updates.log`: Detailed balance update logs
- `/logs/cron_wallet.log`: Cron execution logs

### Error Handling

The system includes robust error handling:
- Graceful recovery from network failures
- Special handling for unactivated Stellar accounts
- Proper MongoDB exception handling
- Detailed error reporting in logs

## Usage

### Manual Execution

The scripts can be run manually as follows:

```bash
# Run wallet cleanup with dry-run (no changes)
php scripts/cleanup_and_update_wallets.php --dry-run

# Run wallet cleanup with actual changes
php scripts/cleanup_and_update_wallets.php

# Update wallet balances with verbose output
php scripts/update_wallet_balances.php --verbose

# Run the full maintenance process
./scripts/maintain_wallets.sh
```

### Scheduled Execution (Cron)

To automate the maintenance process, add the following cron job:

```
# Run wallet maintenance daily at 1:00 AM
0 1 * * * /home/cdr/domains/thegivehub.com/app/scripts/maintain_wallets.sh >> /home/cdr/domains/thegivehub.com/app/logs/cron_wallet.log 2>&1
```

## Best Practices

1. **Regular Monitoring**: Review the logs periodically to ensure proper functioning.
2. **Backup Before Cleanup**: Always create database backups before running the cleanup with actual changes.
3. **Test in Dry-Run Mode**: Use the `--dry-run` flag to test the cleanup process before actual execution.
4. **Performance Considerations**: Schedule maintenance during off-peak hours to minimize impact on users.
5. **Balance Verification**: Periodically verify wallet balances through the Stellar network explorer.

## Troubleshooting

### Common Issues

1. **Database Connection Failures**:
   - Check MongoDB connection settings in `lib/db.php`
   - Verify MongoDB service is running

2. **Stellar API Rate Limiting**:
   - If encountering rate limits, adjust the script to process wallets in smaller batches
   - Consider implementing exponential backoff for retries

3. **Missing Wallet Balances**:
   - Check the wallet's status on the Stellar network explorer
   - Verify that the wallet's public key is correctly stored in the database

### Support

For issues with the wallet maintenance system, contact the development team or create an issue in the project repository.

## Security Considerations

1. The wallet maintenance scripts handle sensitive information including wallet keys.
2. Access to the scripts should be restricted to authorized personnel only.
3. The scripts do not expose private keys in logs or error messages.
4. All operations are conducted over secured connections.

## Future Enhancements

1. Add support for notifications when orphaned wallets are detected
2. Implement more detailed transaction history synchronization
3. Add support for additional cryptocurrencies beyond Stellar
4. Develop a dashboard for manual wallet maintenance operations