<?php
require_once 'vendor/autoload.php';
require_once 'lib/config.php';

// Output PHP & MongoDB versions for debugging
echo "PHP Version: " . phpversion() . "\n";
echo "Loaded Extensions:\n";
print_r(get_loaded_extensions());

echo "\nChecking MongoDB PHP driver...\n";
if (!extension_loaded('mongodb')) {
    die("MongoDB extension is NOT loaded. Please check your PHP configuration.\n");
}
echo "MongoDB extension is loaded.\n";

echo "\nTesting MongoDB connection...\n";
try {
    // Get connection parameters from environment or config
    $host = getenv('MONGODB_HOST') ?: MONGODB_HOST;
    $port = getenv('MONGODB_PORT') ?: MONGODB_PORT;
    $database = getenv('MONGODB_DATABASE') ?: MONGODB_DATABASE;
    
    echo "Connecting to MongoDB at {$host}:{$port}/{$database}...\n";
    
    // Create client and connect
    $client = new MongoDB\Client("mongodb://{$host}:{$port}");
    echo "MongoDB client created successfully\n";
    
    // List databases to test connection
    echo "Available databases:\n";
    foreach ($client->listDatabases() as $dbInfo) {
        echo " - " . $dbInfo->getName() . "\n";
    }
    
    // Select our application database
    $db = $client->selectDatabase($database);
    echo "\nSelected database: {$database}\n";
    
    // List collections
    echo "Collections:\n";
    foreach ($db->listCollections() as $collection) {
        echo " - " . $collection->getName() . "\n";
    }
    
    // Test a simple MongoDB operation
    echo "\nInserting a test document...\n";
    $result = $db->test->insertOne(['name' => 'MongoDB Test', 'date' => new MongoDB\BSON\UTCDateTime()]);
    echo "Inserted document with ID: " . $result->getInsertedId() . "\n";
    
    echo "\nRetrieving the test document...\n";
    $document = $db->test->findOne(['_id' => $result->getInsertedId()]);
    echo "Retrieved document:\n";
    var_dump($document);
    
    echo "\nCleaning up test data...\n";
    $db->test->deleteOne(['_id' => $result->getInsertedId()]);
    
    echo "\nMongoDB connection and operations test completed successfully!\n";
    
} catch (Exception $e) {
    echo "MongoDB connection error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    die("MongoDB connection test failed.\n");
}
