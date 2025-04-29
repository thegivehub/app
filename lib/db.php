<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/MongoCollection.php';

class Database {
    private $client;
    public $db;
    private static $instance = null;

    public function __construct($db = null) {
        try {
            // Use environment variables via config.php
            $dbName = $db ? $db : MONGODB_DATABASE;
            $host = MONGODB_HOST;
            $port = MONGODB_PORT;
            $username = MONGODB_USERNAME;
            $password = MONGODB_PASSWORD;

            // Build connection string
            $connectionString = "mongodb://";
            
            // Add authentication if provided
            if ($username && $password) {
                $connectionString .= $username . ":" . $password . "@";
            }
            
            // Add host and port
            $connectionString .= $host . ":" . $port;


            error_log("MongoDB connection string: '{$host}:{$port}/{$dbName}'");
            // Create MongoDB client with the connection string
            $this->client = new MongoDB\Client($connectionString);
            $this->db = $this->client->selectDatabase($dbName);
            
            if (APP_DEBUG) {
                error_log("MongoDB connection established to {$host}:{$port}/{$dbName}");
            }
        } catch (Exception $e) {
            error_log("MongoDB connection failed: " . $e->getMessage());
            throw new Exception("MongoDB connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getCollection($name) {
        return new MongoCollection($this->db->selectCollection($name));
    }
}
