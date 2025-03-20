<?php
// tests/bootstrap.php

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

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
