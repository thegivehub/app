<?php
/**
 * MongoDB Setup and Test Script
 * 
 * This script tests MongoDB connectivity and sets up required collections
 */

// Check for MongoDB extension
if (!extension_loaded('mongodb')) {
    die("MongoDB extension is not loaded. Please install it first.\n");
}

echo "MongoDB extension is loaded.\n";

// Include Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    echo "Composer autoloader found and loaded.\n";
} else {
    die("Composer autoloader not found. Run 'composer install' first.\n");
}

// Check if MongoDB\Client class is available
if (!class_exists('\MongoDB\Client')) {
    die("MongoDB\Client class not found. Make sure mongodb/mongodb package is installed.\n");
}

echo "MongoDB\Client class is available.\n";

// Connect to MongoDB
try {
    $host = getenv('MONGODB_HOST') ?: 'mongodb';
    $port = getenv('MONGODB_PORT') ?: '27017';
    
    echo "Connecting to MongoDB at $host:$port...\n";
    
    $client = new MongoDB\Client("mongodb://$host:$port");
    echo "Connected to MongoDB successfully.\n";
    
    // List databases
    echo "Available databases:\n";
    foreach ($client->listDatabases() as $databaseInfo) {
        echo " - " . $databaseInfo->getName() . "\n";
    }
    
    // Select database
    $dbName = getenv('MONGODB_DATABASE') ?: 'givehub';
    $db = $client->selectDatabase($dbName);
    echo "Selected database: $dbName\n";
    
    // Create collections
    $collections = [
        'users', 'campaigns', 'donations', 'donors', 
        'impactmetrics', 'updates', 'notifications', 'preferences',
        'verifications'
    ];
    
    foreach ($collections as $collection) {
        if (!in_array($collection, iterator_to_array($db->listCollectionNames()))) {
            $db->createCollection($collection);
            echo "Created collection: $collection\n";
        } else {
            echo "Collection already exists: $collection\n";
        }
    }
    
    // Create indexes
    echo "Creating indexes...\n";
    
    // Users collection indexes
    $db->users->createIndex(['email' => 1], ['unique' => true]);
    $db->users->createIndex(['username' => 1], ['unique' => true]);
    $db->users->createIndex(['status' => 1]);
    echo "Created indexes for users collection.\n";
    
    // Campaigns collection indexes
    $db->campaigns->createIndex(['creator_id' => 1]);
    $db->campaigns->createIndex(['status' => 1]);
    $db->campaigns->createIndex(['created' => -1]);
    echo "Created indexes for campaigns collection.\n";
    
    // Donations collection indexes
    $db->donations->createIndex(['campaign_id' => 1]);
    $db->donations->createIndex(['user_id' => 1]);
    echo "Created indexes for donations collection.\n";
    
    // Verifications collection indexes
    $db->verifications->createIndex(['user_id' => 1]);
    $db->verifications->createIndex(['status' => 1]);
    $db->verifications->createIndex(['timestamp' => -1]);
    $db->verifications->createIndex(['reviewedAt' => -1]);
    echo "Created indexes for verifications collection.\n";
    
    // Test insert
    $testDoc = ['name' => 'test', 'timestamp' => new MongoDB\BSON\UTCDateTime()];
    $result = $db->test->insertOne($testDoc);
    echo "Inserted test document with ID: " . $result->getInsertedId() . "\n";
    
    // Test find
    $foundDoc = $db->test->findOne(['_id' => $result->getInsertedId()]);
    echo "Found test document: " . json_encode($foundDoc) . "\n";
    
    // Clean up test data
    $db->test->drop();
    echo "Cleaned up test collection.\n";
    
    echo "MongoDB setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    die("MongoDB setup failed.\n");
}
