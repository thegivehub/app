<?php
/**
 * Update Blockchain Transactions Schema Script
 *
 * This script updates the blockchain_transactions collection schema to add "donation"
 * to the allowed values for the "type" field.
 */

require_once __DIR__ . '/../lib/db.php';

try {
    // Initialize database connection
    $db = new Database();
    
    // Get the raw MongoDB database object
    $adminDb = $db->db->getManager()->selectDatabase('admin');
    
    // Create a command to run against the database
    $command = [
        'collMod' => 'blockchain_transactions',
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['txHash', 'status', 'createdAt', 'type'],
                'properties' => [
                    'txHash' => [
                        'bsonType' => 'string',
                        'description' => 'Blockchain transaction hash (required)'
                    ],
                    'status' => [
                        'enum' => ['pending', 'submitted', 'confirming', 'confirmed', 'failed', 'expired'],
                        'description' => 'Current status of the transaction (required)'
                    ],
                    'type' => [
                        'enum' => ['payment', 'account_creation', 'escrow_setup', 'milestone_release', 'other', 'donation'],
                        'description' => 'Type of blockchain transaction (required)'
                    ]
                ]
            ]
        ],
        'validationLevel' => 'moderate',  // Using moderate to avoid validation on existing documents
        'validationAction' => 'warn'      // Using warn instead of error to avoid failing on existing docs
    ];
    
    // Run the command on the database
    $result = $adminDb->command([
        'runCommand' => 'givehub',  // Use the actual database name
        'command' => $command
    ]);
    
    echo "Schema update command executed successfully.\n";
    print_r($result);
    
    echo "\nSchema update complete. Now you can run the test donations script.\n";
} catch (Exception $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
}