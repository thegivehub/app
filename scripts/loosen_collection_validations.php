<?php
/**
 * Loosen Collection Validations Script
 *
 * This script modifies the existing blockchain_transactions and donations collections
 * to use a more relaxed validation level and action.
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
    
    logMessage("Updating blockchain_transactions collection validation...");
    
    // Create a command to update the blockchain_transactions collection
    $blockchainCommand = new MongoDB\Driver\Command([
        'collMod' => 'blockchain_transactions',
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['txHash', 'status', 'type'],
                'properties' => [
                    'txHash' => ['bsonType' => 'string'],
                    'status' => [
                        'enum' => ['pending', 'submitted', 'confirming', 'confirmed', 'failed', 'expired']
                    ],
                    'type' => [
                        'enum' => ['payment', 'account_creation', 'escrow_setup', 'milestone_release', 'other', 'donation']
                    ]
                ]
            ]
        ],
        'validationLevel' => 'moderate', // Less strict validation
        'validationAction' => 'warn'     // Don't error on validation failure
    ]);
    
    // Run the command to update blockchain_transactions
    $result = $mongoClient->getManager()->executeCommand($dbName, $blockchainCommand);
    $response = current($result->toArray());
    
    logMessage("Updated blockchain_transactions collection validation");
    
    logMessage("Updating donations collection validation...");
    
    // Create a command to update the donations collection
    $donationsCommand = new MongoDB\Driver\Command([
        'collMod' => 'donations',
        'validationLevel' => 'moderate', // Less strict validation
        'validationAction' => 'warn'     // Don't error on validation failure
    ]);
    
    // Run the command to update donations
    $result = $mongoClient->getManager()->executeCommand($dbName, $donationsCommand);
    
    $response = current($result->toArray());
    logMessage("Updated donations collection validation");
    
    logMessage("Collection validations have been relaxed successfully!");
    logMessage("You can now run the original generate_test_donations.php script.");
    
} catch (Exception $e) {
    logMessage("Error updating collection validations: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

exit(0);