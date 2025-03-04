<?php
require_once 'db.php';

class Campaign {
    private $collection;

    public function __construct() {
        $db = new Database("givehub");
        $this->collection = $db->getCollection('campaigns');
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

    public function update($id, $data) {
        return $this->collection->updateOne(['_id' => new MongoDB\BSON\ObjectId($id)], ['$set' => $data]);
    }
    
    /**
     * Delete campaign object
     *
     * @return object The result from deleteOne
     */
    public function delete($id) {
        return $this->collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    }

    /**
     * Get campaigns created by the current user
     *
     * @return array The user's campaigns
     */
public function getMyCampaigns() {
    try {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Debug: Log all headers
        $allHeaders = getallheaders();
        error_log("All request headers: " . json_encode($allHeaders));
        
        // Debug: Log session data
        error_log("Session data: " . json_encode($_SESSION));
        
        // Get current user ID using multiple methods
        $userId = null;
        
        // Method 1: Try from session directly
        if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
            $userId = $_SESSION['user']['id'];
            error_log("User ID from session: $userId");
        } elseif (isset($_SESSION['user']) && isset($_SESSION['user']['_id'])) {
            $userId = $_SESSION['user']['_id'];
            error_log("User ID from session (_id): $userId");
        } elseif (isset($_SESSION['userId'])) {
            $userId = $_SESSION['userId'];
            error_log("User ID from session (userId): $userId");
        }
        
        // Method 2: Try from token
        $tokenUserId = $this->extractUserIdFromToken();
        if ($tokenUserId) {
            error_log("User ID from token: $tokenUserId");
            $userId = $tokenUserId;
        }
        
        // Method 3: Try from User class if available
        if (!$userId && class_exists('User')) {
            $user = new User();
            $profile = $user->me();
            if ($profile && isset($profile['_id'])) {
                $userId = $profile['_id'];
                error_log("User ID from User->me(): $userId");
            }
        }
        
        // Method 4: Last resort - check if there's a getUserId method
        if (!$userId && method_exists($this, 'getUserId')) {
            $userId = $this->getUserId();
            error_log("User ID from getUserId(): $userId");
        }
        
        // If still no user ID, return authentication error
        if (!$userId) {
            error_log("Failed to determine user ID using any method");
            return [
                'success' => false,
                'error' => 'Authentication required'
            ];
        }
        
        // Debug: What field do we use to find the user's campaigns?
        error_log("Using field criteria for user campaigns lookup");
        
        // Try multiple field names that might store the creator/owner ID
        $possibleFieldNames = ['creatorId', 'creator_id', 'userId', 'user_id', 'ownerId', 'owner_id', 'creator'];
        
        $campaigns = [];
        foreach ($possibleFieldNames as $fieldName) {
            error_log("Trying to find campaigns with $fieldName = $userId");
            
            // First try with ObjectId
            try {
                $objId = new MongoDB\BSON\ObjectId($userId);
                $result = $this->collection->find([$fieldName => $objId]);
                if (count($result) > 0) {
                    error_log("Found " . count($result) . " campaigns with $fieldName as ObjectId");
                    $campaigns = $result;
                    break;
                }
            } catch (Exception $e) {
                error_log("Error with ObjectId lookup on $fieldName: " . $e->getMessage());
            }
            
            // Then try with string
            try {
                $result = $this->collection->find([$fieldName => $userId]);
                if (count($result) > 0) {
                    error_log("Found " . count($result) . " campaigns with $fieldName as string");
                    $campaigns = $result;
                    break;
                }
            } catch (Exception $e) {
                error_log("Error with string lookup on $fieldName: " . $e->getMessage());
            }
        }
        
        // If no campaigns found using specific fields, try to dump a campaign to see its structure
        if (empty($campaigns)) {
            $sampleCampaign = $this->collection->findOne([]);
            if ($sampleCampaign) {
                error_log("Sample campaign structure: " . json_encode($sampleCampaign));
            } else {
                error_log("No campaigns found in the collection");
            }
            
            // Return empty array if no campaigns found
            return [];
        }
        
        return $campaigns;
    } catch (Exception $e) {
        error_log("Error getting user campaigns: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to retrieve campaigns: ' . $e->getMessage()
        ];
    }
}


    /**
     * Get user ID from authorization token
     *
     * @return string|null User ID or null if not authenticated
     */
    private function getUserIdFromToken() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }

        $token = $matches[1];
        try {
            // Use your JWT library to decode the token
            // This example assumes you have a JWT_SECRET defined
            // and are using Firebase JWT library
            $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
            return $decoded->sub; // The 'sub' claim typically contains the user ID
        } catch (Exception $e) {
            error_log("Token decode error: " . $e->getMessage());
            return null;
        }
    }
}

