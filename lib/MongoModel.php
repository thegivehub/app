<?php
// lib/Model.php
abstract class MongoModel {
    protected $collection;

    public function __construct() {
        $collection = strtolower(get_class($this));
        if (!preg_match("/s$/", $collection)) {
            $collection .= 's';
        }
        $this->collection = Database::getInstance()->getCollection($collection);
    }

    public function create($data) {
        return $this->collection->insertOne($data);
    }

    public function read($id = null) {
        if ($id) {
            return $this->collection->findOne(['_id' => $id]);
        }
        return $this->collection->find();
    }

    public function update($id, $data) {
        return $this->collection->updateOne(['_id' => $id], $data);
    }

    public function delete($id) {
        return $this->collection->deleteOne(['_id' => $id]);
    }

    public function find($filter = [], $options = []) {
        return $this->collection->find($filter, $options);
    }

    public function findOne($filter) {
        return $this->collection->findOne($filter);
    }

    public function aggregate($pipeline) {
        return $this->collection->aggregate($pipeline);
    }

    public function count($filter = []) {
        return $this->collection->count($filter);
    }
}
