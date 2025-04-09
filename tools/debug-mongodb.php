<?php
/**
 * MongoDB Debug Script
 * 
 * This script performs comprehensive diagnostics on the MongoDB connection
 */

// Output header
echo "=============================================\n";
echo "MongoDB Connection Diagnostics\n";
echo "=============================================\n\n";

// Check PHP version
echo "PHP Version: " . phpversion() . "\n\n";

// Check MongoDB extension
echo "Checking MongoDB extension...\n";
if (extension_loaded('mongodb')) {
    echo "✅ MongoDB extension is loaded\n";
    echo "MongoDB extension version: " . phpversion('mongodb') . "\n";
} else {
    echo "❌ MongoDB extension is NOT loaded\n";
    echo "Please install the MongoDB extension with:\n";
    echo "pecl install mongodb && docker-php-ext-enable mongodb\n\n";
    exit(1);
}

// Check for Composer autoloader
echo "\nChecking Composer autoloader...\n";
$autoloaderPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php'
];

$autoloaderLoaded = false;
foreach ($autoloaderPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        echo "✅ Composer autoloader found and loaded from: $path\n";
        $autoloaderLoaded = true;
        break;
    }
}

if (!$autoloaderLoaded) {
    echo "❌ Composer autoloader not found\n";
    echo "Please run 'composer install' in the project directory\n\n";
    exit(1);
}

// Check for MongoDB\Client class
echo "\nChecking MongoDB\Client class...\n";
if (class_exists('\MongoDB\Client')) {
    echo "✅ MongoDB\Client class is available\n";
} else {
    echo "❌ MongoDB\Client class is NOT available\n";
    echo "Please install the MongoDB PHP library with:\n";
    echo "composer require mongodb/mongodb\n\n";
    exit(1);
}

// List available MongoDB classes
echo "\nAvailable MongoDB classes:\n";
$classes = [
    '\MongoDB\Client',
    '\MongoDB\Database',
    '\MongoDB\Collection',
    '\MongoDB\BSON\ObjectId',
    '\MongoDB\BSON\UTCDateTime',
    '\MongoDB\BSON\Document'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✅ $class\n";
    } else {
        echo "❌ $class - NOT FOUND\n";
    }
}

// Check MongoDB connection parameters
echo "\nMongoDB connection parameters:\n";
$host = getenv('MONGODB_HOST') ?: 'mongodb';
$port = getenv('MONGODB_PORT') ?: '27017';
$database = getenv('MONGODB_DATABASE') ?: 'givehub';
$username = getenv('MONGODB_USERNAME') ?: '';
$password = getenv('MONGODB_PASSWORD') ?: '';

echo "Host: $host\n";
echo "Port: $port\n";
echo "Database: $database\n";
echo "Username: " . ($username ? "Set" : "Not set") . "\n";
echo "Password: " . ($password ? "Set" : "Not set") . "\n";

// Check network connectivity
echo "\nChecking network connectivity to MongoDB...\n";
$connection = @fsockopen($host, $port, $errno, $errstr, 5);
if ($connection) {
    echo "✅ Successfully connected to $host:$port\n";
    fclose($connection);
} else {
    echo "❌ Could not connect to $host:$port: $errstr ($errno)\n";
    echo "Please check if MongoDB is running and accessible\n\n";
}

// Try connecting with MongoDB\Client
echo "\nTrying to connect with MongoDB\Client...\n";
try {
    // Build connection string
    $connectionString = "mongodb://";
    if ($username && $password) {
        $connectionString .= $username . ":" . $password . "@";
    }
    $connectionString .= $host . ":" . $port;
    
    echo "Connection string: $connectionString\n";
    
    $client = new MongoDB\Client($connectionString);
    echo "✅ MongoDB client created successfully\n";
    
    // Test server connection
    $command = ['ping' => 1];
    $client->admin->command($command);
    echo "✅ Successfully pinged MongoDB server\n";
    
    // List databases
    echo "\nAvailable databases:\n";
    foreach ($client->listDatabases() as $dbInfo) {
        echo " - " . $dbInfo->getName() . "\n";
    }
    
    // Test database operations
    $db = $client->selectDatabase($database);
    echo "\nSelected database: $database\n";
    
    // Test insert
    $testDoc = ['name' => 'test', 'timestamp' => new MongoDB\BSON\UTCDateTime()];
    $result = $db->test->insertOne($testDoc);
    echo "✅ Inserted test document with ID: " . $result->getInsertedId() . "\n";
    
    // Test find
    $foundDoc = $db->test->findOne(['_id' => $result->getInsertedId()]);
    echo "✅ Found test document with name: " . $foundDoc['name'] . "\n";
    
    // Clean up
    $db->test->drop();
    echo "✅ Cleaned up test collection\n";
    
    echo "\n✅ ALL MONGODB TESTS PASSED SUCCESSFULLY\n";
    
} catch (Exception $e) {
    echo "❌ MongoDB error: " . $e->getMessage() . "\n";
    echo "Exception type: " . get_class($e) . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=============================================\n";
echo "MongoDB diagnostics completed\n";
echo "=============================================\n";
