<?php
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Model.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class User extends Model {
    protected $auth;

    public function __construct() {
        parent::__construct(); // This must come first to initialize $collection
        $this->auth = new Auth();
    }

    public function updateProfile($userId, $data) {
        try {
            error_log("Updating profile for user $userId with data: " . json_encode($data));
            
            if (!$userId) {
                throw new Exception('Invalid user ID');
            }
            
            // Validate the user ID
            $objectId = new MongoDB\BSON\ObjectId($userId);
            
            // Structure the update data
            $updateData = [
                'displayName' => $data['displayName'],
                'personalInfo' => $data['personalInfo'],
                'profile' => $data['profile'],
                'email' => $data['email'],
                'updated' => new MongoDB\BSON\UTCDateTime()
            ];

            error_log("Structured update data: " . json_encode($updateData));

            // Update the user document
            $result = $this->collection->updateOne(
                ['_id' => $objectId],
                ['$set' => $updateData]
            );
            
            error_log("Result: " . json_encode($result));

            /* if ($result->getModifiedCount() === 0 && $result->getMatchedCount() === 0) {
                throw new Exception('User not found');
            } */

            // Return the updated user document
            $updated = $this->collection->findOne(['_id' => $objectId]);
            error_log("Updated user document: " . json_encode($updated));
            return $updated;

        } catch (Exception $e) {
            error_log("Error updating user profile: " . $e->getMessage());
            throw $e;
        }
    }

    public function me() {
        $userId = $this->getUserIdFromToken();
        if (!$userId) {
            throw new Exception('No authentication token provided');
        }
        
        $user = $this->collection->findOne([
            '_id' => new MongoDB\BSON\ObjectId($userId)
        ]);

        if (!$user) {
            throw new Exception('User not found');
        }

        return $user;
    }

    public function getUserIdFromToken() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }
        
        $token = $matches[1];
        try {
            $decoded = JWT::decode($token, new Key($this->auth->config['jwt_secret'], 'HS256'));
            return $decoded->sub;
        } catch (Exception $e) {
            error_log("Token decode error: " . $e->getMessage());
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

