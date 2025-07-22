<?php
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Collection.php';
require_once __DIR__ . '/DocumentUploader.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * User Collection
 * Handles user-related database operations
 */
class User extends Collection {
    protected $auth;
    protected $documentUploader;

    public function __construct() {
        parent::__construct(); // This must come first to initialize $collection
        $this->auth = new Auth();
        $this->documentUploader = new DocumentUploader($this->auth, null, 'profile');
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
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];

            // Handle base64 profile image if present
            if (isset($data['profile']['avatar']) && strpos($data['profile']['avatar'], 'data:image/') === 0) {
                // Process base64 image and get URL
                $imageResult = $this->documentUploader->processBase64Image(
                    $data['profile']['avatar'],
                    'profile',
                    $userId
                );
                
                if ($imageResult['success']) {
                    // Update avatar with the new URL
                    $updateData['profile']['avatar'] = $imageResult['url'];
                } else {
                    error_log("Failed to process profile image: " . ($imageResult['error'] ?? 'Unknown error'));
                }
            }

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

    /**
     * Upload a profile image for the user
     * 
     * @param array $file The uploaded file from $_FILES
     * @return array Response with upload status and URL
     */
    public function uploadProfileImage($file) {
        try {
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }
            
            // Use DocumentUploader to handle the file upload
            $result = $this->documentUploader->uploadProfileImage($file);
            
            if ($result['success']) {
                return $result;
            } else {
                throw new Exception($result['error'] ?? 'Failed to upload profile image');
            }
        } catch (Exception $e) {
            error_log("Profile image upload error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
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
    
    /**
     * API: Get user by ID
     * Endpoint: /api/user/getUserById
     * @param array $params Request parameters with userId
     * @return array User details or error
     */
    public function getUserById($params) {
        try {
            // Validate required parameters
            if (!isset($params['userId'])) {
                return [
                    'success' => false,
                    'error' => 'User ID is required'
                ];
            }
            
            $userId = $params['userId'];
            
            // Convert string ID to MongoDB ObjectId if needed
            if (is_string($userId)) {
                $userId = new MongoDB\BSON\ObjectId($userId);
            }
            
            // Get user from database
            $user = $this->collection->findOne(['_id' => $userId]);
            
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'User not found'
                ];
            }
            
            // Format user data for response
            $formattedUser = [
                'id' => (string)$user['_id'],
                'email' => $user['email'] ?? 'N/A',
                'name' => isset($user['personalInfo']) ? 
                    ($user['personalInfo']['firstName'] . ' ' . $user['personalInfo']['lastName']) : 
                    ($user['displayName'] ?? 'N/A'),
                'username' => $user['username'] ?? 'N/A',
                'status' => $user['status'] ?? 'N/A',
                'roles' => $user['roles'] ?? ['user'],
                'createdAt' => isset($user['createdAt']) && is_object($user['createdAt']) ? 
                    $user['createdAt']->toDateTime()->format('c') : null
            ];
            
            return [
                'success' => true,
                'user' => $formattedUser
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function register($data) {
        try {
            $result = $this->auth->register($data);
            if (!$result['success']) {
                return $result;
            }

            $userId = $result['userId'];
            $user = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);

            return [
                'success' => true,
                'id' => $userId,
                'user' => $user
            ];
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Add this method to update a user's address
    public function updateAddress($userId, $address) {
        try {
            if (!$userId) {
                throw new Exception('Invalid user ID');
            }
            
            // Validate the address
            require_once __DIR__ . '/AddressValidator.php';
            $validator = new AddressValidator();
            
            $result = $validator->validate($address);
            
            if (!$result['valid']) {
                return [
                    'success' => false,
                    'errors' => $result['errors'] ?? ['Invalid address'],
                    'suggestions' => $result['suggestions'] ?? []
                ];
            }
            
            // Update the user document with the normalized address
            $objectId = new MongoDB\BSON\ObjectId($userId);
            
            $updateResult = $this->collection->updateOne(
                ['_id' => $objectId],
                ['$set' => [
                    'personalInfo.address' => $result['normalized'],
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            
            if (!$updateResult['success']) {
                throw new Exception('Failed to update address');
            }
            
            return [
                'success' => true,
                'message' => 'Address updated successfully',
                'address' => $result['normalized']
            ];
            
        } catch (Exception $e) {
            error_log("Error updating address: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

