<?php
require_once __DIR__ . '/lib/db.php';

class Collection {
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
            return $this->collection->find()->toArray();
        }
    }

    public function update($id, $data) {
        return $this->collection->updateOne(['_id' => new MongoDB\BSON\ObjectId($id)], ['$set' => $data]);
    }

    public function delete($id) {
        return $this->collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    }
}

