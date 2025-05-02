<?php
/**
 * Blockchain Transaction Status Checker
 * 
 * This script checks the status of pending blockchain transactions and updates them.
 * It should be run periodically via cron job.
 * 
 * Recommended cron schedule: Every 5 minutes
 * Example cron entry: Run this script every 5 minutes
 */

// Set script execution time limit (0 = no limit)
set_time_limit(300); // 5 minutes

// Load required files
require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/../lib/BlockchainTransactionController.php';

// Initialize blockchain transaction controller
$useTestnet = defined('STELLAR_TESTNET') && STELLAR_TESTNET === true;
$txController = new BlockchainTransactionController($useTestnet);

// Get pending transactions to check
$maxAge = 3600; // 1 hour
$limit = 50; // Process up to 50 transactions at a time
$pendingTransactions = $txController->getPendingTransactions($maxAge, $limit);

logMessage("Starting blockchain transaction status check...");
logMessage("Found " . count($pendingTransactions) . " pending transactions to check.");

$successCount = 0;
$failureCount = 0;
$unchangedCount = 0;

// Process each pending transaction
foreach ($pendingTransactions as $transaction) {
    $txHash = $transaction['txHash'];
    $currentStatus = $transaction['status'];
    
    logMessage("Checking transaction {$txHash} (current status: {$currentStatus})...");
    
    // Check transaction status on blockchain
    $result = $txController->checkTransactionStatus($txHash);
    
    if (!$result['success']) {
        logMessage("Error checking transaction {$txHash}: " . ($result['error'] ?? 'Unknown error'));
        $failureCount++;
        continue;
    }
    
    // If status changed, log it
    if (isset($result['previousStatus']) && isset($result['newStatus']) && $result['previousStatus'] !== $result['newStatus']) {
        logMessage("Transaction {$txHash} status changed from {$result['previousStatus']} to {$result['newStatus']}");
        $successCount++;
    } else {
        logMessage("Transaction {$txHash} status unchanged: {$currentStatus}");
        $unchangedCount++;
    }
    
    // Add a small delay to avoid rate limiting
    usleep(200000); // 200ms
}

logMessage("Blockchain transaction status check completed.");
logMessage("Results: {$successCount} updated, {$unchangedCount} unchanged, {$failureCount} failed.");

// Exit with success code
exit(0); 
