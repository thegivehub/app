<?php
require_once __DIR__ .'/db.php';

class Campaign {
    private $collection;

    public function __construct() {
        $db = new Database("givehub");
        $this->collection = $db->getCollection('campaigns');
    }

    public function create($data) {
        // Log incoming data for debugging
        error_log("Creating campaign with data: " . print_r($data, true));
        
        // Set required default fields if not provided
        if (!isset($data['createdAt'])) {
            $data['createdAt'] = date('Y-m-d H:i:s');
        }
        
        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }
        
        if (!isset($data['raised'])) {
            $data['raised'] = 0;
        }
        
        // Ensure creator ID is set using all possible field names
        if (!isset($data['creatorId']) && !isset($data['creator_id']) && !isset($data['userId'])) {
            // Try to get from token if not in the data
            $userId = $this->getUserIdFromToken();
            if ($userId) {
                $data['creatorId'] = $userId;
                error_log("Set creatorId from token: $userId");
            } else {
                error_log("WARNING: No creator ID found in data or token");
            }
        }
        
        // Normalize creator ID field to creatorId
        if (isset($data['creator_id']) && !isset($data['creatorId'])) {
            $data['creatorId'] = $data['creator_id'];
        } else if (isset($data['userId']) && !isset($data['creatorId'])) {
            $data['creatorId'] = $data['userId'];
        }
        
        // Insert the document
        $result = $this->collection->insertOne($data);
        
        if ($result['success']) {
            // If insertion was successful, return the inserted document with its ID
            $insertedId = $result['id'];
            $campaign = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($insertedId)]);
            
            return [
                'success' => true,
                'id' => $insertedId,
                'campaign' => $campaign
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Failed to create campaign'
        ];
    }

    public function read($id = null) {
        return $this->get($id);
    }

    public function get($id = null) {
        if ($id) {
            return $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
        } else {
            $out = $this->collection->find();
            if (is_array($out)) {
                return $out;
            } else {
                return $out->toArray();
            }
        }
    }

    public function update($id, $data) {
        $result = $this->collection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($id)], 
            ['$set' => $data]
        );
        
        return [
            'success' => $result->getModifiedCount() > 0,
            'modifiedCount' => $result->getModifiedCount()
        ];
    }
    
    public function delete($id) {
        $result = $this->collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
        
        return [
            'success' => $result->getDeletedCount() > 0,
            'deletedCount' => $result->getDeletedCount()
        ];
    }

    /**
     * Get campaigns created by the current user
     *
     * @return array The user's campaigns
     */
    public function getMyCampaigns() {
        try {
            // Get the user ID from the token
            $userId = $this->getUserIdFromToken();
            
            if (!$userId) {
                return [
                    'success' => false,
                    'error' => 'Authentication required'
                ];
            }
            
            // Try to find campaigns using ObjectId
            try {
                $objId = new MongoDB\BSON\ObjectId($userId);
                $campaigns = $this->collection->find(['creatorId' => $objId]);
                
                if (count($campaigns) > 0) {
                    return $campaigns;
                }
            } catch (Exception $e) {
                // If ObjectId conversion fails, continue to string search
            }
            
            // Try with string userId
            $campaigns = $this->collection->find(['creatorId' => $userId]);
            
            // If still no results, try alternate field names
            if (count($campaigns) === 0) {
                $fields = ['creator_id', 'userId', 'user_id', 'ownerId', 'owner_id', 'creator'];
                
                foreach ($fields as $field) {
                    // Try ObjectId first
                    try {
                        $objId = new MongoDB\BSON\ObjectId($userId);
                        $campaigns = $this->collection->find([$field => $objId]);
                        
                        if (count($campaigns) > 0) {
                            break;
                        }
                    } catch (Exception $e) {
                        // Continue to string search
                    }
                    
                    // Try string
                    $campaigns = $this->collection->find([$field => $userId]);
                    
                    if (count($campaigns) > 0) {
                        break;
                    }
                }
            }
            
            return $campaigns;
        } catch (Exception $e) {
            error_log("Error in getMyCampaigns: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve campaigns: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extract user ID from authorization token
     *
     * @return string|null User ID or null if not authenticated
     */
    private function getUserIdFromToken() {
        // Try to get from Authorization header first
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            try {
                // Log token for debugging (remove in production!)
                error_log("Attempting to parse token: " . substr($token, 0, 20) . "...");
                
                // Simple JWT parsing without verification
                list($header, $payload, $signature) = explode('.', $token);
                $payload = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
                
                // Check all common field names
                if (isset($payload['sub'])) {
                    error_log("Found user ID in token 'sub' field: " . $payload['sub']);
                    return $payload['sub'];
                } elseif (isset($payload['userId'])) {
                    error_log("Found user ID in token 'userId' field: " . $payload['userId']);
                    return $payload['userId'];
                } elseif (isset($payload['_id'])) {
                    error_log("Found user ID in token '_id' field: " . $payload['_id']);
                    return $payload['_id'];
                } elseif (isset($payload['id'])) {
                    error_log("Found user ID in token 'id' field: " . $payload['id']);
                    return $payload['id'];
                }
            } catch (Exception $e) {
                error_log("Token decode error: " . $e->getMessage());
            }
        } else {
            error_log("No Bearer token found in Authorization header");
        }
        
        // Try to get from session as fallback
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
            error_log("Using user ID from session: " . $_SESSION['user']['id']);
            return $_SESSION['user']['id'];
        } elseif (isset($_SESSION['user']) && isset($_SESSION['user']['_id'])) {
            error_log("Using user ID from session: " . $_SESSION['user']['_id']);
            return $_SESSION['user']['_id'];
        } elseif (isset($_SESSION['userId'])) {
            error_log("Using userId from session: " . $_SESSION['userId']);
            return $_SESSION['userId'];
        }
        
        error_log("Could not find user ID in token or session");
        return null;
    }
}
