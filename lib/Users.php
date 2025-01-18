<?php
require_once __DIR__ . '/Model.php';

class Users extends Model {
    public function me() {
        $userId = $this->getUserIdFromToken(); // We'll implement this
        if (!$userId) return null;
        
        return $this->collection->findOne([
            '_id' => new MongoDB\BSON\ObjectId($userId)
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

    public function findActive() {
        return $this->find(['status' => 'active']);
    }

    public function findByEmail($email) {
        return $this->find(['email' => $email]);
    }

    public function getPostCounts() {
        return $this->aggregate([
            [
                '$lookup' => [
                    'from' => 'posts',
                    'localField' => '_id',
                    'foreignField' => 'user_id',
                    'as' => 'posts'
                ]
            ],
            [
                '$project' => [
                    'name' => 1,
                    'postCount' => ['$size' => '$posts']
                ]
            ]
        ]);
    }
    public function getProfile() {
        $auth = new Auth();
        $user = $auth->getCurrentUser();
        if (!$user) {
            return ['error' => 'User not found'];
        }
        return $user;
    }
}

