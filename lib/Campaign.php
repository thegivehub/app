<?php
require_once __DIR__ .'/db.php';
require_once __DIR__ .'/Auth.php';
require_once __DIR__ .'/DocumentUploader.php';

class Campaign {
    private $collection;
    private $auth;
    private $documentUploader;

    public function __construct() {
        $db = new Database("givehub");
        $this->collection = $db->getCollection('campaigns');
        $this->auth = new Auth();
        $this->documentUploader = new DocumentUploader($this->auth, null, 'campaign');
    }

    /**
     * Create a new campaign
     * 
     * @param array $data Campaign data
     * @return array Creation result with campaign ID and data
     */
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
        
        // Process any base64 images in the data
        if (isset($data['image']) && strpos($data['image'], 'data:image/') === 0) {
            $imageResult = $this->documentUploader->processBase64Image($data['image'], 'campaign');
            
            if ($imageResult['success']) {
                // Replace base64 data with the URL
                $data['imageUrl'] = $imageResult['url'];
                // Remove the base64 data to save space
                unset($data['image']);
            } else {
                error_log("Failed to process campaign image: " . ($imageResult['error'] ?? 'Unknown error'));
            }
        }
        
        // Process additional images if they exist (changed from gallery to images)
        if (isset($data['images']) && is_array($data['images'])) {
            $processedImages = [];
            
            foreach ($data['images'] as $index => $imageItem) {
                if (isset($imageItem['image']) && strpos($imageItem['image'], 'data:image/') === 0) {
                    $imageResult = $this->documentUploader->processBase64Image(
                        $imageItem['image'],
                        'campaign',
                        isset($data['_id']) ? $data['_id'] : null
                    );
                    
                    if ($imageResult['success']) {
                        $processedImages[] = [
                            'url' => $imageResult['url'],
                            'caption' => $imageItem['caption'] ?? '',
                            'uploadedAt' => new MongoDB\BSON\UTCDateTime()
                        ];
                    }
                } else if (isset($imageItem['url'])) {
                    // Keep existing URLs
                    $processedImages[] = $imageItem;
                }
            }
            
            // Replace images with processed URLs
            if (!empty($processedImages)) {
                $data['images'] = $processedImages;
            }
        }
        
        // Check for backward compatibility with gallery field
        if (isset($data['gallery']) && is_array($data['gallery']) && !isset($data['images'])) {
            error_log("Found legacy 'gallery' field, processing as 'images'");
            $processedImages = [];
            
            foreach ($data['gallery'] as $index => $galleryItem) {
                if (isset($galleryItem['image']) && strpos($galleryItem['image'], 'data:image/') === 0) {
                    $galleryResult = $this->documentUploader->processBase64Image(
                        $galleryItem['image'],
                        'campaign',
                        isset($data['_id']) ? $data['_id'] : null
                    );
                    
                    if ($galleryResult['success']) {
                        $processedImages[] = [
                            'url' => $galleryResult['url'],
                            'caption' => $galleryItem['caption'] ?? '',
                            'uploadedAt' => new MongoDB\BSON\UTCDateTime()
                        ];
                    }
                } else if (isset($galleryItem['url'])) {
                    // Keep existing URLs
                    $processedImages[] = $galleryItem;
                }
            }
            
            // Replace gallery with processed URLs
            if (!empty($processedImages)) {
                $data['images'] = $processedImages;
                // Keep gallery for backward compatibility
                $data['gallery'] = $processedImages;
            }
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

    public function read($id = null, $options = []) {
        return $this->get($id, $options);
    }

    public function get($id = null, $options = []) {
        if ($id) {
            return $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
        } else {
            // Default limit to 20 records if not specified
            if (!isset($options['limit'])) {
                $options['limit'] = 40;
            }
            // Default to page 1 if not specified
            if (!isset($options['page'])) {
                $options['page'] = 1;
            }
            
            // Handle sorting
            if (isset($options['sort'])) {
                $sortField = $options['sort'];
                $sortDirection = 1; // Default ascending
                
                // Check if it's a descending sort (prefixed with -)
                if (substr($sortField, 0, 1) === '-') {
                    $sortField = substr($sortField, 1);
                    $sortDirection = -1; // Descending
                }
                
                // Add the sort configuration to MongoDB options
                $options['sort'] = [$sortField => $sortDirection];
            } else {
                // Default sort by createdAt in descending order (newest first)
                $options['sort'] = ['createdAt' => -1];
            }
            
            $out = $this->collection->find([], $options);
            if (is_array($out)) {
                return $out;
            } else {
                return $out->toArray();
            }
        }
    }

    public function update($id, $data) {
        // Ensure the _id field is never modified during updates
        unset($data['_id']);

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

    /**
     * Upload a campaign image
     * 
     * @param array $file The uploaded file from $_FILES
     * @param string $campaignId Campaign ID
     * @param string $imageType Type of campaign image (main, gallery, etc.)
     * @return array Upload result
     */
    public function uploadCampaignImage($file, $campaignId, $imageType = 'main') {
        try {
            return $this->documentUploader->uploadCampaignImage($file, $campaignId, $imageType);
        } catch (Exception $e) {
            error_log('Campaign image upload error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

}
