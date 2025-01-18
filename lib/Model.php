<?php
require_once __DIR__ . '/db.php';

abstract class Model {
    protected $collection;
    protected $db;

    public function __construct() {
        $this->db = new Database();
        $collection = strtolower(get_class($this));
        
        // Remove 's' if it exists and add it back to ensure consistent plural form
        $collection = rtrim($collection, 's') . 's';
        
        $this->collection = $this->db->getCollection($collection);
        
        if (!$this->collection) {
            throw new Exception("Failed to initialize collection for " . get_class($this));
        }
    }

    public function create($data) {
        return $this->collection->insertOne($data);
    }

    public function read($id = null) {
        return $this->get($id);
    }

    public function get($id = null) {
        if ($id) {
            return $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
        } else {
            return $this->collection->find();
        }
    }
    
    public function find($obj) {
        if ($obj) {
            return $this->collection->find($obj);
        } 
        return [];
    }

    public function findOne($obj) {
        if ($obj) {
            return $this->collection->findOne($obj);            
        } 
        return null;
    }

    public function update($id, $data) {
        return $this->collection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($id)], 
            ['$set' => $data]
        );
    }

    public function delete($id) {
        return $this->collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    }
}
