<?php
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Model.php';
require_once __DIR__ . '/DocumentUploader.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class User extends Model {
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
    
    public function register($data) {
        try {
            // Existing validation code...
            
            // Validate address if provided
            if (isset($data['address']) && is_array($data['address'])) {
                require_once __DIR__ . '/AddressValidator.php';
                $validator = new AddressValidator();
                
                $addressResult = $validator->validate($data['address']);
                
                if (!$addressResult['valid']) {
                    return [
                        'success' => false,
                        'error' => 'Invalid address: ' . implode(', ', array_values($addressResult['errors'] ?? ['Address validation failed']))
                    ];
                }
                
                // Use normalized address
                $data['address'] = $addressResult['normalized'];
            }
            
            // Continue with existing registration logic...
            // but make sure to include address in the user data structure

            // Example user data structure with address
            $userData = [
                'email' => $data['email'],
                'username' => $data['username'],
                'type' => $data['type'] ?? 'donor',
                'status' => 'pending',
                'personalInfo' => [
                    'firstName' => $data['firstName'] ?? '',
                    'lastName' => $data['lastName'] ?? '',
                    'email' => $data['email'],
                    'language' => $data['personalInfo']['language'] ?? 'en',
                    // Add address to personalInfo
                    'address' => $data['address'] ?? null
                ],
                'auth' => [
                    'passwordHash' => password_hash($data['password'], PASSWORD_DEFAULT),
                    'verificationCode' => $verificationCode,
                    'verificationExpires' => $verificationExpires,
                    'verified' => false,
                    'twoFactorEnabled' => false,
                    'lastLogin' => new MongoDB\BSON\UTCDateTime()
                ],
                'profile' => array_merge([
                    'avatar' => null,
                    'bio' => '',
                    'preferences' => [
                        'emailNotifications' => true,
                        'currency' => 'USD'
                    ]
                ], $data['profile'] ?? []),
                'roles' => ['user'],
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Continue with user creation and return success response...
            
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

