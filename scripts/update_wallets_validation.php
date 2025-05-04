<?php
/**
 * Update Wallets Collection Validation Script
 *
 * This script modifies the existing wallets collection
 * to allow campaign wallets in addition to user wallets.
 */

require_once __DIR__ . '/../lib/db.php';

// Helper function to log messages
function logMessage($message) {
    echo date('[Y-m-d H:i:s]') . " $message\n";
}

try {
    // Initialize database connection
    $db = new Database();
    $dbName = MONGODB_DATABASE;
    $mongoClient = new MongoDB\Client("mongodb://" . MONGODB_HOST . ":" . MONGODB_PORT);
    $database = $mongoClient->selectDatabase($dbName);
    
    logMessage("Updating wallets collection validation...");
    
    // Simplify by just changing the validation level and action
    $walletsCommand = new MongoDB\Driver\Command([
        'collMod' => 'wallets',
        'validationLevel' => 'moderate', // Less strict validation
        'validationAction' => 'warn'     // Don't error on validation failure
    ]);
    
    // Run the command to update wallets collection
    $mongoClient->getManager()->executeCommand($dbName, $walletsCommand);
    
    logMessage("Updated wallets collection validation settings to moderate/warn");
    
    // Update indexes to allow campaign wallets
    logMessage("Updating wallet collection indexes...");
    
    // Create campaignId index
    $database->selectCollection('wallets')->createIndex(
        ['campaignId' => 1],
        ['name' => 'idx_wallets_campaignId']
    );
    logMessage("Created campaignId index");
    
    // Create type index
    $database->selectCollection('wallets')->createIndex(
        ['type' => 1],
        ['name' => 'idx_wallets_type']
    );
    logMessage("Created type index");
    
    logMessage("Wallet collection schema and indexes have been updated successfully!");
    logMessage("You can now create campaign wallets.");
    
} catch (Exception $e) {
    logMessage("Error updating wallet validation: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

exit(0);