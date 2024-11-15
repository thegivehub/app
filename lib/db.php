<?php
require 'vendor/autoload.php';

class Database {
    private $client;
    private $db;

    public function __construct($databaseName = 'givehub') {
        global $mongodb;
        $this->client = new MongoDB\Client("mongodb://localhost:27017");
        $mongodb = $this->db = $this->client->$databaseName;
    }

    public function getCollection($collectionName) {
        return $this->db->$collectionName;
    }
}
