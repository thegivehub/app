<?php
// migrations/create_kyc_collection.php
require_once __DIR__ . '/../lib/db.php';

/**
 * Migration script to create the kyc_verifications collection
 * Run this script once to set up the required collection and indexes
 */

// Create database connection
$db = new Database();

echo "Starting KYC verification collection setup...\n";

try {
    // Get or create the collection
    $kycCollection = $db->getCollection('kyc_verifications');
    
    echo "Collection exists or was created successfully.\n";
    
    // Create indexes for efficient queries
    echo "Creating indexes...\n";
    
    // Index for user lookup (most common query)
    $kycCollection->createIndex(['userId' => 1]);
    echo "Created index on userId\n";
    
    // Index for Jumio reference lookup (for webhooks)
    $kycCollection->createIndex(['jumioReference' => 1], ['unique' => true, 'sparse' => true]);
    echo "Created unique index on jumioReference\n";
    
    // Index for status-based queries
    $kycCollection->createIndex(['status' => 1]);
    echo "Created index on status\n";
    
    // Index for verification result queries
    $kycCollection->createIndex(['verificationResult' => 1]);
    echo "Created index on verificationResult\n";
    
    // Compound index for report generation (status + date range)
    $kycCollection->createIndex(['verificationResult' => 1, 'created' => -1]);
    echo "Created compound index on verificationResult and created\n";
    
    // TTL index for old records (automatically delete after 7 years)
    // Adjust retention period based on your compliance requirements
    $kycCollection->createIndex(
        ['created' => 1],
        ['expireAfterSeconds' => 220752000] // 7 years in seconds
    );
    echo "Created TTL index for automatic data retention\n";
    
    echo "Index creation complete.\n";
    
    echo "Setting up collection validation rules...\n";
    
    // Define schema validation for the collection
    // Get MongoDB connection directly
    $connectionString = "mongodb://";
    
    // Add authentication if provided
    if (MONGODB_USERNAME && MONGODB_PASSWORD) {
        $connectionString .= MONGODB_USERNAME . ":" . MONGODB_PASSWORD . "@";
    }
    
    // Add host and port
    $connectionString .= MONGODB_HOST . ":" . MONGODB_PORT;
    
    $client = new MongoDB\Client($connectionString);
    $database = $client->selectDatabase(MONGODB_DATABASE);
    
    $database->command([
        'collMod' => 'kyc_verifications',
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['userId', 'status', 'created', 'updated'],
                'properties' => [
                    'userId' => [
                        'bsonType' => 'objectId',
                        'description' => 'User ID must be an ObjectId and is required'
                    ],
                    'jumioReference' => [
                        'bsonType' => 'string',
                        'description' => 'Jumio transaction reference'
                    ],
                    'redirectUrl' => [
                        'bsonType' => 'string',
                        'description' => 'URL for Jumio verification flow'
                    ],
                    'status' => [
                        'bsonType' => 'string',
                        'description' => 'Current verification status'
                    ],
                    'verificationResult' => [
                        'bsonType' => 'string',
                        'description' => 'Final verification result'
                    ],
                    'created' => [
                        'bsonType' => 'date',
                        'description' => 'Creation timestamp (required)'
                    ],
                    'updated' => [
                        'bsonType' => 'date',
                        'description' => 'Last update timestamp (required)'
                    ],
                    'manual' => [
                        'bsonType' => 'bool',
                        'description' => 'Whether this is a manual verification'
                    ],
                    'reason' => [
                        'bsonType' => 'string',
                        'description' => 'Reason for manual verification'
                    ],
                    'adminId' => [
                        'bsonType' => 'objectId',
                        'description' => 'Admin who performed manual verification'
                    ],
                    // Additional fields for storing Jumio data
                    'requestData' => [
                        'bsonType' => 'object',
                        'description' => 'Original request data sent to Jumio'
                    ],
                    'responseData' => [
                        'bsonType' => 'object',
                        'description' => 'Response data from Jumio initiation'
                    ],
                    'webhookData' => [
                        'bsonType' => 'object',
                        'description' => 'Data received from Jumio webhook'
                    ],
                    'documentData' => [
                        'bsonType' => 'object',
                        'description' => 'Document verification data from Jumio'
                    ],
                    'transactionData' => [
                        'bsonType' => 'object',
                        'description' => 'Transaction data from Jumio'
                    ]
                ]
            ]
        ],
        'validationLevel' => 'moderate' // Use moderate to allow existing docs
    ]);
    
    echo "Collection validation rules set up successfully.\n";
    
    echo "KYC verification collection setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error setting up KYC collection: " . $e->getMessage() . "\n";
    exit(1);
}
