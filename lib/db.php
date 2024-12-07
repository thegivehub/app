<?php
require_once __DIR__ . '/MongoCollection.php';

class Database {
    private $client;
    private $db;
    private static $instance = null;

    public function __construct($db="givehub") {
        try {
            $this->client = new MongoDB\Client('mongodb://localhost:27017');
            $this->db = $this->client->selectDatabase($db);
        } catch (Exception $e) {
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


