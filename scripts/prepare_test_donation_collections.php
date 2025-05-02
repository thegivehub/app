<?php
/**
 * Prepare Test Donation Collections
 * 
 * This script prepares the MongoDB collections with relaxed validation for test donations.
 */

require_once __DIR__ . '/../lib/db.php';

// Helper function to log messages
function logMessage($message) {
    echo date('[Y-m-d H:i:s]') . " $message\n";
}

// Get database connection
$db = new Database();
$mongodb = $db->db;

// Drop test collections if they exist
logMessage("Setting up test collections...");

try {
    // Create test blockchain transactions collection
    $result = $mongodb->createCollection('test_blockchain_transactions', [
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
        'validationLevel' => 'moderate',
        'validationAction' => 'warn'
    ]);
    logMessage("Created test_blockchain_transactions collection");
    
    // Create test donations collection
    $result = $mongodb->createCollection('test_donations', [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['userId', 'campaignId', 'amount', 'status'],
                'properties' => [
                    'userId' => ['bsonType' => 'objectId'],
                    'campaignId' => ['bsonType' => 'objectId'],
                    'amount' => ['bsonType' => 'object'],
                    'status' => ['bsonType' => 'string']
                ]
            ]
        ],
        'validationLevel' => 'moderate',
        'validationAction' => 'warn'
    ]);
    logMessage("Created test_donations collection");
    
    logMessage("Test collections prepared successfully!");
} catch (Exception $e) {
    logMessage("Error preparing test collections: " . $e->getMessage());
    exit(1);
}

// Now, update our generate_test_donations_simple.php script to use these collections
$scriptPath = __DIR__ . '/generate_test_donations_simple.php';
if (file_exists($scriptPath)) {
    $scriptContent = file_get_contents($scriptPath);
    
    // Replace collection names
    $scriptContent = str_replace('$donationsCollection = $db->getCollection(\'donations\');', 
                               '$donationsCollection = $db->getCollection(\'test_donations\');', 
                               $scriptContent);
    
    $scriptContent = str_replace('$blockchainTxCollection = $db->getCollection(\'blockchain_transactions\');', 
                               '$blockchainTxCollection = $db->getCollection(\'test_blockchain_transactions\');', 
                               $scriptContent);
    
    // Write updated script
    file_put_contents($scriptPath, $scriptContent);
    logMessage("Updated generate_test_donations_simple.php script to use test collections");
}

logMessage("Setup complete. Run 'php scripts/generate_test_donations_simple.php' to generate test donations.");
exit(0);