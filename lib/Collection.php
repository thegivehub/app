<?php
require_once __DIR__ . '/db.php';

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

    public function read($id = null, $options = []) {
        return $this->get($id, $options);
    }

    public function get($id = null, $options = []) {
        if ($id && !is_null($id)) {
            return $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
        } else {
            // Default limit to 20 records if not specified
            if (!isset($options['limit'])) {
                $options['limit'] = 20;
            }
            // Default to page 1 if not specified
            if (!isset($options['page'])) {
                $options['page'] = 1;
            }
            return $this->collection->find([], $options);
        }
    }

    public function update($id, $data) {
        return $this->collection->updateOne(['_id' => new MongoDB\BSON\ObjectId($id)], ['$set' => $data]);
    }

    public function delete($id) {
        return $this->collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    }
    
    public function me() {
        $userId = $this->getUserIdFromToken(); // You could move this to Collection class
        if (!$userId) return null;

        return $this->collection->findOne([
            'userId' => new MongoDB\BSON\ObjectId($userId)
        ]);
    }

    private function getUserIdFromToken() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
            return $decoded->sub;
        } catch (Exception $e) {
            return null;
        }
    }
}

