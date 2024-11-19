<?php
require_once 'db.php';

class User {
    private $collection;

    public function __construct() {
        $db = new Database();
        $this->collection = $db->getCollection('users');
    }

    public function create($data) {
        return $this->collection->insertOne($data);
    }
    
    public function read($id = null) {
        return $this->get($id);
    }

    public function findId($filterArr=null) {
        if ($filterArr) {
            // print_r($filterArr);
            $all = $this->collection->find($filterArr)->toArray();

            if ($all) {
                return $all[0]->_id;
            }
        } else {
            return [];
        }         
    }

    public function find($filterArr=null) {
        if ($filterArr) {
            return $this->collection->find($filterArr)->toArray();
        } else {
            return $this->collection->find()->toArray();
        }
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

