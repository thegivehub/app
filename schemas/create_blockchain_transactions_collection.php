<?php
/**
 * Script to create the blockchain_transactions collection in MongoDB
 */
require_once __DIR__ . '/../lib/db.php';

try {
    // Get MongoDB connection
    $db = Database::getInstance();
    
    // Build connection string
    $connectionString = "mongodb://";
    
    // Add authentication if provided
    if (MONGODB_USERNAME && MONGODB_PASSWORD) {
        $connectionString .= MONGODB_USERNAME . ":" . MONGODB_PASSWORD . "@";
    }
    
    // Add host and port
    $connectionString .= MONGODB_HOST . ":" . MONGODB_PORT;
    
    $client = new MongoDB\Client($connectionString);
    $database = $client->selectDatabase(MONGODB_DATABASE);

    // Drop existing collection if it exists
    try {
        $database->dropCollection('blockchain_transactions');
        echo "Dropped existing blockchain_transactions collection\n";
    } catch (Exception $e) {
        echo "No existing blockchain_transactions collection to drop\n";
    }

    // Create the collection with validation
    $database->createCollection('blockchain_transactions', [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['txHash', 'status', 'createdAt', 'type'],
                'properties' => [
                    'txHash' => [
                        'bsonType' => 'string',
                        'description' => 'Blockchain transaction hash (required)'
                    ],
                    'sourceId' => [
                        'bsonType' => 'string',
                        'description' => 'ID of the source record (donation, milestone payment, etc.)'
                    ],
                    'sourceType' => [
                        'enum' => ['donation', 'milestone', 'escrow', 'withdrawal', 'other'],
                        'description' => 'Type of source record (required)'
                    ],
                    'userId' => [
                        'bsonType' => 'string',
                        'description' => 'User ID associated with the transaction'
                    ],
                    'campaignId' => [
                        'bsonType' => 'string',
                        'description' => 'Campaign ID associated with the transaction'
                    ],
                    'amount' => [
                        'bsonType' => 'object',
                        'required' => ['value', 'currency'],
                        'properties' => [
                            'value' => [
                                'bsonType' => 'double',
                                'description' => 'Transaction amount'
                            ],
                            'currency' => [
                                'bsonType' => 'string',
                                'description' => 'Currency code (e.g., XLM)'
                            ]
                        ]
                    ],
                    'status' => [
                        'enum' => ['pending', 'submitted', 'confirming', 'confirmed', 'failed', 'expired'],
                        'description' => 'Current status of the transaction (required)'
                    ],
                    'type' => [
                        'enum' => ['payment', 'account_creation', 'escrow_setup', 'milestone_release', 'other'],
                        'description' => 'Type of blockchain transaction (required)'
                    ],
                    'stellarDetails' => [
                        'bsonType' => 'object',
                        'description' => 'Details from the Stellar blockchain'
                    ],
                    'statusHistory' => [
                        'bsonType' => 'array',
                        'description' => 'History of status changes'
                    ],
                    'lastChecked' => [
                        'bsonType' => 'date',
                        'description' => 'When the transaction was last checked on the blockchain'
                    ],
                    'confirmations' => [
                        'bsonType' => 'int',
                        'description' => 'Number of confirmations (for blockchains that use this concept)'
                    ],
                    'createdAt' => [
                        'bsonType' => 'date',
                        'description' => 'When the transaction record was created'
                    ],
                    'updatedAt' => [
                        'bsonType' => 'date',
                        'description' => 'When the transaction record was last updated'
                    ],
                    'expiresAt' => [
                        'bsonType' => 'date',
                        'description' => 'When the transaction expires (if applicable)'
                    ],
                    'error' => [
                        'bsonType' => 'object',
                        'description' => 'Error details if the transaction failed'
                    ]
                ]
            ]
        ],
        'validationLevel' => 'strict',
        'validationAction' => 'error'
    ]);
    
    echo "Blockchain transactions collection created successfully\n";
    
    // Create indexes
    $collection = $database->selectCollection('blockchain_transactions');
    
    $collection->createIndex(['txHash' => 1], ['unique' => true]);
    $collection->createIndex(['sourceId' => 1]);
    $collection->createIndex(['userId' => 1]);
    $collection->createIndex(['campaignId' => 1]);
    $collection->createIndex(['status' => 1]);
    $collection->createIndex(['createdAt' => -1]);
    $collection->createIndex(['amount.currency' => 1]);
    $collection->createIndex(['lastChecked' => 1]);
    
    echo "Indexes created successfully\n";
    
    echo "Blockchain transactions collection setup completed successfully\n";
} catch (Exception $e) {
    echo "Error setting up blockchain_transactions collection: " . $e->getMessage() . "\n";
    exit(1);
} 