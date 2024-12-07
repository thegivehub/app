<?php
require_once __DIR__ . '/db.php';

class Model {
    private $collection;

    public function __construct() {
        $db = new Database();
        $collection = strtolower(get_class($this));
        
        if (!preg_match("/s$/", $collection)) {
            $collection .= 's';
        }
        $this->collection = $db->getCollection($collection);
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
    }

    public function findOne($obj) {
        if ($obj) {
            return $this->collection->findOne($obj);            
        } 
    }

    public function update($id, $data) {
        return $this->collection->updateOne(['_id' => new MongoDB\BSON\ObjectId($id)], ['$set' => $data]);
    }

    public function delete($id) {
        return $this->collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    }
}

