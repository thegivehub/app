#!/usr/bin/env php
<?php
/**
 * Database Upgrade Script
 * Safely adds new collections and indexes without affecting existing data
 */

// Check for MongoDB extension
if (!extension_loaded('mongodb')) {
    die("MongoDB extension is not loaded. Please install it first.\n");
}

echo "Starting database upgrade process...\n";

// Include Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "Composer autoloader loaded.\n";
} else {
    die("Composer autoloader not found. Run 'composer install' first.\n");
}

try {
    // Connect to MongoDB
    $host = getenv('MONGODB_HOST') ?: 'mongodb';
    $port = getenv('MONGODB_PORT') ?: '27017';
    $dbName = getenv('MONGODB_DATABASE') ?: 'givehub';
    
    echo "Connecting to MongoDB at $host:$port...\n";
    
    $client = new MongoDB\Client("mongodb://$host:$port");
    $db = $client->selectDatabase($dbName);
    
    echo "Connected to database: $dbName\n";
    
    // Check if verifications collection exists
    $collections = iterator_to_array($db->listCollectionNames());
    
    if (!in_array('verifications', $collections)) {
        echo "Creating verifications collection...\n";
        $db->createCollection('verifications');
        echo "Created verifications collection.\n";
    } else {
        echo "Verifications collection already exists.\n";
    }
    
    // Create or update indexes for verifications collection
    echo "Creating/updating indexes for verifications collection...\n";
    
    // Get existing indexes
    $existingIndexes = [];
    foreach ($db->verifications->listIndexes() as $index) {
        $existingIndexes[] = json_encode($index->getKey());
    }
    
    // Define required indexes
    $requiredIndexes = [
        ['user_id' => 1],
        ['status' => 1],
        ['timestamp' => -1],
        ['reviewedAt' => -1]
    ];
    
    // Create missing indexes
    foreach ($requiredIndexes as $index) {
        $indexJson = json_encode($index);
        if (!in_array($indexJson, $existingIndexes)) {
            echo "Creating index: $indexJson\n";
            $db->verifications->createIndex($index);
            echo "Index created successfully.\n";
        } else {
            echo "Index already exists: $indexJson\n";
        }
    }

    // Check if we should insert sample data
    $verificationCount = $db->verifications->countDocuments([]);
    if ($verificationCount === 0) {
        echo "\nNo verification records found. Adding sample data...\n";

        // Sample user data
        $sampleUsers = [
            [
                'user_id' => new MongoDB\BSON\ObjectId(),
                'userName' => 'John Smith',
                'email' => 'john.smith@example.com',
                'dateOfBirth' => new MongoDB\BSON\UTCDateTime(strtotime('1985-06-15') * 1000),
                'address' => '123 Main Street',
                'city' => 'New York',
                'state' => 'NY',
                'postalCode' => '10001',
                'country' => 'United States',
                'documentType' => 'DRIVERS_LICENSE',
                'documentNumber' => 'DL123456789',
                'documentExpiry' => new MongoDB\BSON\UTCDateTime(strtotime('2025-06-15') * 1000),
                'documentImageUrl' => 'https://storage.thegivehub.com/verifications/sample-dl-1.jpg',
                'selfieImageUrl' => 'https://storage.thegivehub.com/verifications/sample-selfie-1.jpg',
                'status' => 'APPROVED',
                'timestamp' => new MongoDB\BSON\UTCDateTime(strtotime('-5 days') * 1000),
                'reviewedAt' => new MongoDB\BSON\UTCDateTime(strtotime('-4 days') * 1000),
                'reviewNotes' => 'All documents verified successfully. Face match confirmed.',
                'similarityScore' => 0.92,
                'metadata' => [
                    'documentQualityScore' => 0.95,
                    'faceDetectionScore' => 0.98,
                    'livenessScore' => 0.96
                ]
            ],
            [
                'user_id' => new MongoDB\BSON\ObjectId(),
                'userName' => 'Sarah Johnson',
                'email' => 'sarah.j@example.com',
                'dateOfBirth' => new MongoDB\BSON\UTCDateTime(strtotime('1990-03-22') * 1000),
                'address' => '456 Oak Avenue',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'postalCode' => '90001',
                'country' => 'United States',
                'documentType' => 'PASSPORT',
                'documentNumber' => 'P987654321',
                'documentExpiry' => new MongoDB\BSON\UTCDateTime(strtotime('2028-03-22') * 1000),
                'documentImageUrl' => 'https://storage.thegivehub.com/verifications/sample-passport-1.jpg',
                'selfieImageUrl' => 'https://storage.thegivehub.com/verifications/sample-selfie-2.jpg',
                'status' => 'PENDING',
                'timestamp' => new MongoDB\BSON\UTCDateTime(strtotime('-1 day') * 1000),
                'reviewedAt' => null,
                'reviewNotes' => null,
                'similarityScore' => 0.88,
                'metadata' => [
                    'documentQualityScore' => 0.92,
                    'faceDetectionScore' => 0.94,
                    'livenessScore' => 0.91
                ]
            ],
            [
                'user_id' => new MongoDB\BSON\ObjectId(),
                'userName' => 'Maria Garcia',
                'email' => 'maria.g@example.com',
                'dateOfBirth' => new MongoDB\BSON\UTCDateTime(strtotime('1988-11-30') * 1000),
                'address' => '789 Pine Street',
                'city' => 'Miami',
                'state' => 'FL',
                'postalCode' => '33101',
                'country' => 'United States',
                'documentType' => 'NATIONAL_ID',
                'documentNumber' => 'ID456789012',
                'documentExpiry' => new MongoDB\BSON\UTCDateTime(strtotime('2024-11-30') * 1000),
                'documentImageUrl' => 'https://storage.thegivehub.com/verifications/sample-id-1.jpg',
                'selfieImageUrl' => 'https://storage.thegivehub.com/verifications/sample-selfie-3.jpg',
                'status' => 'REJECTED',
                'timestamp' => new MongoDB\BSON\UTCDateTime(strtotime('-3 days') * 1000),
                'reviewedAt' => new MongoDB\BSON\UTCDateTime(strtotime('-2 days') * 1000),
                'reviewNotes' => 'Document appears to be expired. Please submit a valid document.',
                'similarityScore' => 0.75,
                'metadata' => [
                    'documentQualityScore' => 0.65,
                    'faceDetectionScore' => 0.88,
                    'livenessScore' => 0.90
                ]
            ],
            [
                'user_id' => new MongoDB\BSON\ObjectId(),
                'userName' => 'David Wilson',
                'email' => 'david.w@example.com',
                'dateOfBirth' => new MongoDB\BSON\UTCDateTime(strtotime('1992-08-05') * 1000),
                'address' => '321 Elm Street',
                'city' => 'Chicago',
                'state' => 'IL',
                'postalCode' => '60601',
                'country' => 'United States',
                'documentType' => 'DRIVERS_LICENSE',
                'documentNumber' => 'DL987123456',
                'documentExpiry' => new MongoDB\BSON\UTCDateTime(strtotime('2026-08-05') * 1000),
                'documentImageUrl' => 'https://storage.thegivehub.com/verifications/sample-dl-2.jpg',
                'selfieImageUrl' => 'https://storage.thegivehub.com/verifications/sample-selfie-4.jpg',
                'status' => 'PENDING',
                'timestamp' => new MongoDB\BSON\UTCDateTime(strtotime('-6 hours') * 1000),
                'reviewedAt' => null,
                'reviewNotes' => null,
                'similarityScore' => 0.89,
                'metadata' => [
                    'documentQualityScore' => 0.94,
                    'faceDetectionScore' => 0.96,
                    'livenessScore' => 0.93
                ]
            ]
        ];

        // Insert sample data
        foreach ($sampleUsers as $user) {
            $result = $db->verifications->insertOne($user);
            echo "Inserted verification record for: {$user['userName']}\n";
        }

        echo "Sample data insertion completed.\n";
    } else {
        echo "\nVerification records already exist. Skipping sample data insertion.\n";
    }
    
    // Verify collection and indexes
    echo "\nVerifying setup...\n";
    
    // Verify collection exists
    $collections = iterator_to_array($db->listCollectionNames());
    if (in_array('verifications', $collections)) {
        echo "✓ Verifications collection exists\n";
    } else {
        echo "✗ Error: Verifications collection not found\n";
        exit(1);
    }
    
    // Verify indexes
    $foundIndexes = [];
    foreach ($db->verifications->listIndexes() as $index) {
        $foundIndexes[] = json_encode($index->getKey());
    }
    
    $allIndexesFound = true;
    foreach ($requiredIndexes as $index) {
        $indexJson = json_encode($index);
        if (in_array($indexJson, $foundIndexes)) {
            echo "✓ Index exists: $indexJson\n";
        } else {
            echo "✗ Missing index: $indexJson\n";
            $allIndexesFound = false;
        }
    }
    
    if ($allIndexesFound) {
        echo "\nDatabase upgrade completed successfully!\n";
        echo "Total verification records: " . $db->verifications->countDocuments([]) . "\n";
    } else {
        echo "\nWarning: Some indexes are missing. Please check the output above.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "Error during upgrade: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} 