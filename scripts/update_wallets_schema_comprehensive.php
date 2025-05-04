<?php
/**
 * Comprehensive Update to Wallets Collection Schema
 *
 * This script modifies the wallets collection schema to:
 * 1. Make userId optional instead of required
 * 2. Allow campaignId as an alternative identifier
 * 3. Drop the unique index on userId
 * 4. Create new indexes for campaignId
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
    
    logMessage("Starting comprehensive update to wallets collection schema...");
    
    // First, set validation level to 'off' temporarily to allow schema modifications
    logMessage("Setting validation level to 'off' temporarily...");
    $command = new MongoDB\Driver\Command([
        'collMod' => 'wallets',
        'validationLevel' => 'off'
    ]);
    
    $mongoClient->getManager()->executeCommand($dbName, $command);
    
    // Try to drop the unique userId index
    logMessage("Attempting to drop the unique userId index...");
    try {
        $database->selectCollection('wallets')->dropIndex('idx_wallets_userId');
        logMessage("Successfully dropped the unique userId index");
    } catch (Exception $e) {
        logMessage("Warning: Could not drop index: " . $e->getMessage());
    }
    
    // Create a new non-unique userId index
    logMessage("Creating new non-unique userId index...");
    $database->selectCollection('wallets')->createIndex(
        ['userId' => 1],
        ['name' => 'idx_wallets_userId', 'unique' => false]
    );
    
    // Make sure campaignId index exists and is non-unique
    logMessage("Creating campaignId index...");
    $database->selectCollection('wallets')->createIndex(
        ['campaignId' => 1],
        ['name' => 'idx_wallets_campaignId', 'unique' => false]
    );
    
    // Update the validation schema to make userId optional and add campaignId
    logMessage("Updating validation schema...");
    $command = new MongoDB\Driver\Command([
        'collMod' => 'wallets',
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['publicKey', 'secretKey', 'network', 'status', 'createdAt'],
                'properties' => [
                    'userId' => [
                        'bsonType' => 'objectId',
                        'description' => 'User ID associated with the wallet (optional)'
                    ],
                    'campaignId' => [
                        'bsonType' => 'objectId',
                        'description' => 'Campaign ID associated with the wallet (optional)'
                    ],
                    'publicKey' => [
                        'bsonType' => 'string',
                        'description' => 'Stellar public key (required)'
                    ],
                    'secretKey' => [
                        'bsonType' => 'string',
                        'description' => 'Encrypted Stellar secret key (required)'
                    ],
                    'network' => [
                        'enum' => ['testnet', 'public'],
                        'description' => 'Network type (required)'
                    ],
                    'status' => [
                        'enum' => ['active', 'inactive', 'locked'],
                        'description' => 'Wallet status (required)'
                    ],
                    'type' => [
                        'enum' => ['user', 'campaign', 'platform'],
                        'description' => 'Type of wallet'
                    ],
                    'createdAt' => [
                        'bsonType' => 'date',
                        'description' => 'Timestamp when the wallet was created (required)'
                    ]
                ]
            ]
        ],
        // Set to moderate validation level and warn (instead of error) to allow for transition
        'validationLevel' => 'moderate',
        'validationAction' => 'warn'
    ]);
    
    $mongoClient->getManager()->executeCommand($dbName, $command);
    logMessage("Updated validation schema successfully");
    
    // Create a unique index on publicKey to ensure no duplicates
    logMessage("Creating unique publicKey index...");
    $database->selectCollection('wallets')->createIndex(
        ['publicKey' => 1],
        ['name' => 'idx_wallets_publicKey', 'unique' => true]
    );
    
    logMessage("Wallet collection schema updated successfully!");
    logMessage("You can now create both user and campaign wallets without conflicts.");
    
} catch (Exception $e) {
    logMessage("Error updating wallet schema: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

exit(0);