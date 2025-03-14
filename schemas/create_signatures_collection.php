<?php
/**
 * Script to create the signatures collection in MongoDB
 */
require_once __DIR__ . '/../lib/db.php';

try {
    // Get MongoDB connection
    $db = Database::getInstance();
    $client = new MongoDB\Client(MONGODB_CONNECTION_STRING);
    $database = $client->selectDatabase(MONGODB_DATABASE);

    // Drop existing collection if it exists
    try {
        $database->dropCollection('signatures');
        echo "Dropped existing signatures collection\n";
    } catch (Exception $e) {
        echo "No existing signatures collection to drop\n";
    }

    // Create the collection with validation
    $database->createCollection('signatures', [
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['userId', 'signatureData', 'createdAt', 'type'],
                'properties' => [
                    'userId' => [
                        'bsonType' => 'string',
                        'description' => 'User ID associated with the signature (required)'
                    ],
                    'documentId' => [
                        'bsonType' => ['string', 'null'],
                        'description' => 'Optional ID of the document being signed'
                    ],
                    'signatureData' => [
                        'bsonType' => 'string',
                        'description' => 'Base64 encoded signature data (required)'
                    ],
                    'type' => [
                        'enum' => ['consent', 'agreement', 'document', 'verification', 'other'],
                        'description' => 'Type of signature (required)'
                    ],
                    'metadata' => [
                        'bsonType' => 'object',
                        'description' => 'Additional metadata about the signature'
                    ],
                    'description' => [
                        'bsonType' => 'string',
                        'description' => 'Description of what was signed'
                    ],
                    'createdAt' => [
                        'bsonType' => 'date',
                        'description' => 'Timestamp when the signature was created (required)'
                    ],
                    'updatedAt' => [
                        'bsonType' => 'date',
                        'description' => 'Timestamp when the signature was last updated'
                    ]
                ]
            ]
        ],
        'validationLevel' => 'strict',
        'validationAction' => 'error'
    ]);
    
    echo "Signatures collection created successfully\n";
    
    // Create indexes
    $collection = $database->selectCollection('signatures');
    
    $collection->createIndex(['userId' => 1], ['name' => 'idx_signatures_userId']);
    $collection->createIndex(['documentId' => 1], ['name' => 'idx_signatures_documentId']);
    $collection->createIndex(['createdAt' => -1], ['name' => 'idx_signatures_createdAt']);
    $collection->createIndex(['type' => 1], ['name' => 'idx_signatures_type']);
    
    echo "Indexes created successfully\n";
    
    echo "Signatures collection setup completed successfully\n";
} catch (Exception $e) {
    echo "Error setting up signatures collection: " . $e->getMessage() . "\n";
    exit(1);
} 