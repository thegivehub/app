<?php
require_once 'db.php';

class Campaign {
    private $collection;

    public function __construct() {
        $db = new Database("givehub");
        $this->collection = $db->getCollection('campaigns');
    }

    public function create($data) {
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
        
        // Insert the document
        $result = $this->collection->insertOne($data);
        
        if ($result->getInsertedCount()) {
            // If insertion was successful, return the inserted document with its ID
            $insertedId = $result->getInsertedId();
            $campaign = $this->collection->findOne(['_id' => $insertedId]);
            
            return [
                'success' => true,
                'id' => (string) $insertedId,
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
            return $this->collection->find()->toArray();
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
                $campaigns = $this->collection->find(['creatorId' => $objId])->toArray();
                
                if (count($campaigns) > 0) {
                    return $campaigns;
                }
            } catch (Exception $e) {
                // If ObjectId conversion fails, continue to string search
            }
            
            // Try with string userId
            $campaigns = $this->collection->find(['creatorId' => $userId])->toArray();
            
            // If still no results, try alternate field names
            if (count($campaigns) === 0) {
                $fields = ['creator_id', 'userId', 'user_id', 'ownerId', 'owner_id', 'creator'];
                
                foreach ($fields as $field) {
                    // Try ObjectId first
                    try {
                        $objId = new MongoDB\BSON\ObjectId($userId);
                        $campaigns = $this->collection->find([$field => $objId])->toArray();
                        
                        if (count($campaigns) > 0) {
                            break;
                        }
                    } catch (Exception $e) {
                        // Continue to string search
                    }
                    
                    // Try string
                    $campaigns = $this->collection->find([$field => $userId])->toArray();
                    
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
                // Simple JWT parsing without verification (for demonstration)
                // In production, use a proper JWT library
                list($header, $payload, $signature) = explode('.', $token);
                $payload = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
                
                if (isset($payload['sub'])) {
                    return $payload['sub'];
                } elseif (isset($payload['userId'])) {
                    return $payload['userId'];
                } elseif (isset($payload['_id'])) {
                    return $payload['_id'];
                }
            } catch (Exception $e) {
                error_log("Token decode error: " . $e->getMessage());
            }
        }
        
        // Try to get from session as fallback
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
            return $_SESSION['user']['id'];
        } elseif (isset($_SESSION['user']) && isset($_SESSION['user']['_id'])) {
            return $_SESSION['user']['_id'];
        } elseif (isset($_SESSION['userId'])) {
            return $_SESSION['userId'];
        }
        
        return null;
    }
}
