<?php
// tests/bootstrap.php

/**
 * Bootstrap file for PHPUnit tests
 * Sets up environment variables and initializes test dependencies
 */

// Set error reporting to maximum for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load environment variables for testing
$envFile = __DIR__ . '/../.env.test';
if (file_exists($envFile)) {
    $envVars = parse_ini_file($envFile);
    foreach ($envVars as $key => $value) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

// Set test environment
putenv('APP_ENV=testing');

// Initialize MongoDB connection for tests
require_once __DIR__ . '/../config/database.php';

// Load test helpers
require_once __DIR__ . '/TestCase.php';

// Require composer autoloader
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

// Define base path constant
define('BASE_PATH', dirname(__DIR__));

// Add lib directory to include path
set_include_path(get_include_path() . PATH_SEPARATOR . BASE_PATH . '/lib');

// Include any required files that may not be autoloaded
require_once BASE_PATH . '/lib/db.php';
require_once BASE_PATH . '/lib/config.php';

// Set up helper functions for tests
require_once __DIR__ . '/helpers.php';

// Initialize testing environment
initTestEnvironment();

/**
 * Set up the testing environment
 */
function initTestEnvironment() {
    // Ensure MongoDB connection is to test database
    putenv("MONGODB_DATABASE=givehub_test");
    putenv("APP_ENV=testing");
    
    // Set a fixed JWT secret for testing purposes
    define('JWT_SECRET', 'test_jwt_secret_for_unit_tests');
    
    // Clear test database before running tests
    cleanTestDatabase();
}

/**
 * Clear the test database
 */
function cleanTestDatabase() {
    try {
        $db = new Database();
        $collections = [
            'users', 'campaigns', 'donations', 'escrows', 
            'wallets', 'transactions', 'notifications',
            'kyc_verifications', 'documents', 'preferences'
        ];
        
        foreach ($collections as $collection) {
            $db->getCollection($collection)->deleteMany([]);
        }
    } catch (Exception $e) {
        echo "Warning: Could not clean test database: " . $e->getMessage() . "\n";
    }
}

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Test Database Setup
 * This script initializes the test database and required collections
 */

class TestDatabaseSetup {
    private $client;
    private $db;

    public function __construct() {
        // Connect to MongoDB
        $host = $_ENV['MONGODB_HOST'] ?? 'localhost';
        $port = $_ENV['MONGODB_PORT'] ?? '27017';
        $dbName = $_ENV['MONGODB_DATABASE'] ?? 'givehub_test';

        $this->client = new MongoDB\Client("mongodb://{$host}:{$port}");
        $this->db = $this->client->selectDatabase($dbName);
    }

    public function setup() {
        // Drop existing database to ensure clean state
        $this->client->dropDatabase($_ENV['MONGODB_DATABASE']);

        // Create collections
        $this->createCollections();
        
        // Create indexes
        $this->createIndexes();
        
        // Add any required initial data
        $this->seedTestData();
    }

    private function createCollections() {
        $collections = [
            'users',
            'campaigns',
            'donations',
            'documents',
            'test_collection' // For base collection tests
        ];

        foreach ($collections as $collection) {
            $this->db->createCollection($collection);
        }
    }

    private function createIndexes() {
        // Users collection indexes
        $this->db->users->createIndex(['email' => 1], ['unique' => true]);
        $this->db->users->createIndex(['username' => 1], ['unique' => true]);
        $this->db->users->createIndex(['status' => 1]);

        // Campaigns collection indexes
        $this->db->campaigns->createIndex(['creatorId' => 1]);
        $this->db->campaigns->createIndex(['status' => 1]);
        $this->db->campaigns->createIndex(['category' => 1]);
        $this->db->campaigns->createIndex([
            'title' => 'text',
            'description' => 'text'
        ]);

        // Donations collection indexes
        $this->db->donations->createIndex(['campaignId' => 1]);
        $this->db->donations->createIndex(['userId' => 1]);
        $this->db->donations->createIndex(['status' => 1]);

        // Documents collection indexes
        $this->db->documents->createIndex(['userId' => 1]);
        $this->db->documents->createIndex(['type' => 1]);
    }

    private function seedTestData() {
        // Add any required initial data for tests
        // For example, admin user, test categories, etc.
        $this->db->users->insertOne([
            '_id' => new MongoDB\BSON\ObjectId(),
            'email' => 'admin@thegivehub.com',
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'type' => 'admin',
            'status' => 'active',
            'createdAt' => new MongoDB\BSON\UTCDateTime(),
            'updatedAt' => new MongoDB\BSON\UTCDateTime()
        ]);
    }
}

// Run database setup
$setup = new TestDatabaseSetup();
$setup->setup();

// Clean up test files directory if it exists
$testFilesDir = __DIR__ . '/../storage/test';
if (is_dir($testFilesDir)) {
    $files = glob($testFilesDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
} else {
    mkdir($testFilesDir, 0777, true);
}

// Create storage directories for test uploads
$directories = [
    'profile',
    'campaign',
    'document',
    'temp'
];

foreach ($directories as $dir) {
    $path = $testFilesDir . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

// Ensure all required environment variables are set
$requiredEnvVars = [
    "APP_ENV",
    "APP_DEBUG",
    "MONGODB_DATABASE",
    "MONGODB_HOST",
    "MONGODB_PORT",
    "JWT_SECRET",
    "STORAGE_PATH"
];

foreach ($requiredEnvVars as $var) {
    if (!getenv($var)) {
        throw new RuntimeException("Required environment variable {$var} is not set");
    }
}

// Create storage directory if it doesn't exist
$storagePath = getenv("STORAGE_PATH");
if (!is_dir($storagePath)) {
    if (!mkdir($storagePath, 0777, true)) {
        throw new RuntimeException("Failed to create storage directory: {$storagePath}");
    }
}

// Initialize any global test state here
date_default_timezone_set("UTC");

// Register error handler for tests
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
