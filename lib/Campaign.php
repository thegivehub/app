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
        
        // Check all possible creator ID fields and log their presence/absence
        if (isset($data['creatorId'])) {
            error_log("Found creatorId in submitted data: " . $data['creatorId']);
        } else {
            error_log("No creatorId in submitted data");
        }
        
        if (isset($data['creator_id'])) {
            error_log("Found creator_id in submitted data: " . $data['creator_id']);
        } else {
            error_log("No creator_id in submitted data");
        }
        
        if (isset($data['userId'])) {
            error_log("Found userId in submitted data: " . $data['userId']);
        } else {
            error_log("No userId in submitted data");
        }
        
        // Combined check for all creator ID fields
        if (!isset($data['creatorId']) && !isset($data['creator_id']) && !isset($data['userId'])) {
            // Try to get from token if not in the data
            error_log("No creator ID found in any field, attempting to extract from token");
            $userId = $this->getUserIdFromToken();
            
            if ($userId) {
                $data['creatorId'] = $userId;
                error_log("Successfully set creatorId from token: " . $userId);
            } else {
                error_log("WARNING: Could not get creator ID from token or session");
            }
        }
        
        // Normalize creator ID field to creatorId
        if (isset($data['creator_id']) && !isset($data['creatorId'])) {
            $data['creatorId'] = $data['creator_id'];
            error_log("Normalized creator_id to creatorId: " . $data['creatorId']);
        } else if (isset($data['userId']) && !isset($data['creatorId'])) {
            $data['creatorId'] = $data['userId'];
            error_log("Normalized userId to creatorId: " . $data['creatorId']);
        }
        
        // Log final state before insertion
        if (isset($data['creatorId'])) {
            error_log("Final creatorId before database insertion: " . $data['creatorId']);
        } else {
            error_log("ERROR: No creatorId set before database insertion!");
        }
        
        // Check if creatorId is a valid MongoDB ObjectId
        if (isset($data['creatorId'])) {
            try {
                $objId = new MongoDB\BSON\ObjectId($data['creatorId']);
                error_log("creatorId is a valid ObjectId");
                // Convert string ID to ObjectId for MongoDB
                $data['creatorId'] = $objId;
            } catch (Exception $e) {
                error_log("creatorId is not a valid ObjectId, keeping as string: " . $e->getMessage());
                // Keep as string if it's not a valid ObjectId
            }
        }
        
        // Insert the document
        error_log("Inserting campaign document into database");
        $result = $this->collection->insertOne($data);
        
        if ($result['success']) {
            // If insertion was successful, return the inserted document with its ID
            $insertedId = $result['id'];
            error_log("Campaign created successfully with ID: " . $insertedId);
            
            $campaign = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($insertedId)]);
            
            // Log the creator ID in the retrieved document
            if ($campaign && isset($campaign['creatorId'])) {
                error_log("Retrieved campaign has creatorId: " . $campaign['creatorId']);
            } else {
                error_log("WARNING: Retrieved campaign does not have creatorId!");
            }
            
            return [
                'success' => true,
                'id' => $insertedId,
                'campaign' => $campaign
            ];
        }
        
        error_log("Failed to create campaign: " . ($result['error'] ?? 'Unknown error'));
        return [
            'success' => false,
            'error' => 'Failed to create campaign: ' . ($result['error'] ?? '')
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
            'success' => count($result) > 0,
            'modifiedCount' => count($result)
        ];
    }
    
    public function delete($id) {
        $result = $this->collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
        
        return [
            'success' => count($result) > 0,
            'deletedCount' => count($result)
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
    public function getUserIdFromToken() {
        // Try to get from Authorization header first
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        error_log("Authorization header: " . substr($authHeader, 0, 20) . "...");
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            
            error_log("JWT token found: " . substr($token, 0, 15) . "...");
            
            try {
                // Proper JWT validation using Firebase JWT library
                require_once __DIR__ . '/../vendor/autoload.php';
                
                // Get JWT secret from Auth class if possible
                $jwtSecret = null;
                try {
                    $auth = new Auth();
                    $jwtSecret = $auth->getJwtSecret();
                    error_log("JWT secret obtained from Auth class");
                } catch (Exception $e) {
                    error_log("Failed to get JWT secret from Auth class: " . $e->getMessage());
                    // Fallback to hardcoded secret (same as in Auth.php)
                    $jwtSecret = '6ABD1CF21B5743C99A283D9184AB6F1A15E8FC1F141C749E39B49B6FD3E9D705';
                    error_log("Using fallback JWT secret");
                }
                
                $decoded = \Firebase\JWT\JWT::decode(
                    $token, 
                    new \Firebase\JWT\Key($jwtSecret, 'HS256')
                );
                
                error_log("JWT decoded successfully: " . json_encode($decoded));
                
                // Check 'sub' field first (standard JWT subject claim)
                if (isset($decoded->sub)) {
                    error_log("Using user ID from token 'sub' field: " . $decoded->sub);
                    return $decoded->sub;
                }
                
                // Check other common field names
                if (isset($decoded->userId)) {
                    error_log("Using user ID from token 'userId' field: " . $decoded->userId);
                    return $decoded->userId;
                }
                
                if (isset($decoded->_id)) {
                    error_log("Using user ID from token '_id' field: " . $decoded->_id);
                    return $decoded->_id;
                }
                
                if (isset($decoded->id)) {
                    error_log("Using user ID from token 'id' field: " . $decoded->id);
                    return $decoded->id;
                }
                
                error_log("JWT decoded but no user ID found in the payload: " . json_encode($decoded));
                
            } catch (\Firebase\JWT\ExpiredException $e) {
                error_log("JWT token expired: " . $e->getMessage());
            } catch (\Firebase\JWT\SignatureInvalidException $e) {
                error_log("JWT signature invalid: " . $e->getMessage());
            } catch (\Firebase\JWT\BeforeValidException $e) {
                error_log("JWT not valid yet: " . $e->getMessage());
            } catch (\Firebase\JWT\UnexpectedValueException $e) {
                error_log("JWT unexpected value: " . $e->getMessage());
                
                // Fallback to manual token parsing for debugging
                try {
                    error_log("Attempting manual token parsing for debugging");
                    list($header, $payload, $signature) = explode('.', $token);
                    $decodedPayload = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
                    error_log("Manual token parsing result: " . json_encode($decodedPayload));
                    
                    // Check if manual parsing reveals user ID
                    if (isset($decodedPayload['sub'])) {
                        error_log("Manual parsing found user ID in 'sub': " . $decodedPayload['sub']);
                    } elseif (isset($decodedPayload['userId'])) {
                        error_log("Manual parsing found user ID in 'userId': " . $decodedPayload['userId']);
                    } elseif (isset($decodedPayload['_id'])) {
                        error_log("Manual parsing found user ID in '_id': " . $decodedPayload['_id']);
                    } elseif (isset($decodedPayload['id'])) {
                        error_log("Manual parsing found user ID in 'id': " . $decodedPayload['id']);
                    } else {
                        error_log("Manual parsing didn't find any user ID field");
                    }
                } catch (Exception $e) {
                    error_log("Manual token parsing failed: " . $e->getMessage());
                }
            } catch (Exception $e) {
                error_log("Token decode general error: " . $e->getMessage());
            }
        } else {
            error_log("No Bearer token found in Authorization header");
        }
        
        // Try to get from session as fallback
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
            error_log("Using user ID from session 'user.id': " . $_SESSION['user']['id']);
            return $_SESSION['user']['id'];
        } elseif (isset($_SESSION['user']) && isset($_SESSION['user']['_id'])) {
            error_log("Using user ID from session 'user._id': " . $_SESSION['user']['_id']);
            return $_SESSION['user']['_id'];
        } elseif (isset($_SESSION['userId'])) {
            error_log("Using userId from session: " . $_SESSION['userId']);
            return $_SESSION['userId'];
        }
        
        error_log("Could not find user ID in token or session");
        return null;
    }

}
