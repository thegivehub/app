<?php
/**
 * Setup script for blockchain transactions tracking
 * This script will set up the blockchain_transactions collection in MongoDB
 */

// Run the schema creation script
require_once __DIR__ . '/schemas/create_blockchain_transactions_collection.php';

echo "Blockchain transactions collection setup completed.\n";
echo "You can now use the blockchain-transaction-api.php endpoints to track transaction statuses.\n";
echo "Don't forget to set up a cron job to run the check_blockchain_transactions.php script periodically.\n";
echo "Recommended cron schedule: Every 5 minutes\n";
echo "Example cron entry: */5 * * * * php " . __DIR__ . "/cron/check_blockchain_transactions.php\n"; 