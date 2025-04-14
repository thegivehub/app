<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use MongoDB\Client;
use MongoDB\Database;

/**
 * Base TestCase class that provides common functionality for all tests
 */
class TestCase extends BaseTestCase
{
    protected static ?Database $db = null;
    protected static ?Client $client = null;
    
    /**
     * Set up test environment before each test class
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        
        // Initialize MongoDB connection if not already done
        if (self::$client === null) {
            $host = getenv("MONGODB_HOST") ?: "localhost";
            $port = getenv("MONGODB_PORT") ?: "27017";
            $username = getenv("MONGODB_USERNAME") ?: "";
            $password = getenv("MONGODB_PASSWORD") ?: "";
            
            $uri = "mongodb://";
            if ($username && $password) {
                $uri .= "{$username}:{$password}@";
            }
            $uri .= "{$host}:{$port}";
            
            self::$client = new Client($uri);
            self::$db = self::$client->selectDatabase(getenv("MONGODB_DATABASE") ?: "givehub_test");
        }
    }
    
    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clean up any test files
        $testPath = getenv("STORAGE_PATH") ?: "storage/test";
        if (is_dir($testPath)) {
            $files = glob($testPath . "/*");
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * Get MongoDB database instance
     *
     * @return Database
     */
    protected function getDb(): Database
    {
        return self::$db;
    }
    
    /**
     * Create a test document in a collection
     *
     * @param string $collection Collection name
     * @param array $data Document data
     * @return string Inserted document ID
     */
    protected function createTestDocument(string $collection, array $data): string
    {
        $result = $this->getDb()->$collection->insertOne($data);
        return (string)$result->getInsertedId();
    }
    
    /**
     * Clean up a collection by removing all documents
     *
     * @param string $collection Collection name
     */
    protected function cleanCollection(string $collection): void
    {
        $this->getDb()->$collection->deleteMany([]);
    }
    
    /**
     * Assert that a document exists in a collection with given criteria
     *
     * @param string $collection Collection name
     * @param array $criteria Search criteria
     */
    protected function assertDocumentExists(string $collection, array $criteria): void
    {
        $document = $this->getDb()->$collection->findOne($criteria);
        $this->assertNotNull($document, "Document not found in {$collection} with criteria: " . json_encode($criteria));
    }
    
    /**
     * Assert that a document does not exist in a collection with given criteria
     *
     * @param string $collection Collection name
     * @param array $criteria Search criteria
     */
    protected function assertDocumentNotExists(string $collection, array $criteria): void
    {
        $document = $this->getDb()->$collection->findOne($criteria);
        $this->assertNull($document, "Document found in {$collection} with criteria: " . json_encode($criteria));
    }
} 