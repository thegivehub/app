<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Auth.php';

class AdminCampaignController {
    private $collection;
    private $auth;

    public function __construct() {
        $db = new Database("givehub");
        $this->collection = $db->getCollection('campaigns');
        $this->auth = new Auth();
    }

    /**
     * Check if the current user has admin permissions
     * @return bool
     */
    private function isAdmin() {
        $currentUser = $this->auth->getCurrentUser();
        if (!$currentUser) {
            return false;
        }
        
        // Check if user has admin role
        $roles = isset($currentUser['roles']) ? $currentUser['roles'] : [];
        return in_array('admin', $roles);
    }

    /**
     * Get all campaigns or campaigns with a specific status
     * @param string $status Optional status filter
     * @param int $page Page number (default: 1)
     * @param int $limit Number of records per page (default: 20)
     * @return array
     */
    public function getCampaigns($status = null, $page = 1, $limit = 20) {
        if (!$this->isAdmin()) {
            http_response_code(403);
            return ['error' => 'Unauthorized access'];
        }

        try {
            $filter = [];
            if ($status && $status !== 'all') {
                $filter['status'] = $status;
            }

            $options = [
                'page' => (int)$page,
                'limit' => (int)$limit
            ];

            $campaigns = $this->collection->find($filter, $options);
            
            return $campaigns;
        } catch (Exception $e) {
            error_log("Error in getCampaigns: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to retrieve campaigns'];
        }
    }

    /**
     * Get details for a specific campaign
     * @param string $id Campaign ID
     * @return array
     */
    public function getCampaignDetails($id) {
        if (!$this->isAdmin()) {
            http_response_code(403);
            return ['error' => 'Unauthorized access'];
        }

        try {
            $campaign = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            
            if (!$campaign) {
                http_response_code(404);
                return ['error' => 'Campaign not found'];
            } else {
                if (isset($campaign['creatorId'])) {
                    $db = new Database();
                    $usersCollection = $db->getCollection('users');
                    
                    // Try to fetch user using ObjectId
                    try {
                        $creator = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaign['creatorId'])]);
                    } catch (Exception $e) {
                        // If ObjectId fails, try with string ID
                        $creator = $usersCollection->findOne(['_id' => $campaign['creatorId']]);
                    }
                    
                    if ($creator) {
                        // Create a proper creator name from available fields
                        $firstName = $creator['personalInfo']['firstName'] ?? '';
                        $lastName = $creator['personalInfo']['lastName'] ?? '';
                        $displayName = $creator['displayName'] ?? '';
                        $username = $creator['username'] ?? '';
                        
                        if ($firstName && $lastName) {
                            $campaign['creatorName'] = $firstName . ' ' . $lastName;
                        } else if ($displayName) {
                            $campaign['creatorName'] = $displayName;
                        } else if ($username) {
                            $campaign['creatorName'] = $username;
                        } else {
                            $campaign['creatorName'] = 'Unknown User';
                        }
                    }
                }

            }
            
            return $campaign;
        } catch (Exception $e) {
            error_log("Error in getCampaignDetails: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to retrieve campaign details'];
        }
    }

    /**
     * Update campaign status and review information
     * @param string $id Campaign ID
     * @param array $data Update data
     * @return array
     */
    public function updateCampaignStatus($id, $data) {
        if (!$this->isAdmin()) {
            http_response_code(403);
            return ['error' => 'Unauthorized access'];
        }

        try {
            // Validate the status
            $validStatuses = ['pending', 'active', 'rejected'];
            if (isset($data['status']) && !in_array($data['status'], $validStatuses)) {
                http_response_code(400);
                return ['error' => 'Invalid status value'];
            }
            
            // Add review metadata
            $data['reviewedAt'] = date('Y-m-d H:i:s');
            $data['reviewedBy'] = $this->auth->getCurrentUser()['_id'];
            
            // Update the campaign
            $result = $this->collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                ['$set' => $data]
            );
            
            if (!$result['success']) {
                http_response_code(500);
                return ['error' => 'Failed to update campaign'];
            }
            
            // Get the updated campaign
            $campaign = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            
            // If the campaign was approved, send a notification to the creator
            if (isset($data['status']) && $data['status'] === 'active') {
                $this->sendApprovalNotification($campaign);
            } 
            // If the campaign was rejected, send a rejection notification
            else if (isset($data['status']) && $data['status'] === 'rejected') {
                $this->sendRejectionNotification($campaign);
            }
            
            return $campaign;
        } catch (Exception $e) {
            error_log("Error in updateCampaignStatus: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to update campaign status'];
        }
    }

    /**
     * Handle admin dashboard endpoint requests
     */
    public function handleRequest() {
        header('Content-Type: application/json');
        
        // Get path parts from the URL
        $pathParts = [];
        if (isset($_SERVER['PATH_INFO'])) {
            $pathParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
            // Remove 'admin' and 'campaigns' from the beginning if they exist
            if (count($pathParts) > 0 && $pathParts[0] === 'admin') {
                array_shift($pathParts);
            }
            if (count($pathParts) > 0 && $pathParts[0] === 'campaigns') {
                array_shift($pathParts);
            }
        }
        
        $id = $_GET['id'] ?? null;
        $status = $_GET['status'] ?? null;
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Determine the action based on HTTP method and path parts
        if (empty($pathParts)) {
            // Handle base campaigns endpoint
            if ($method === 'GET') {
                // Get pagination parameters
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30;
                
                // List campaigns with pagination
                $result = $this->getCampaigns($status, $page, $limit);
                echo json_encode($result);
                return;
            }
        } else if ($pathParts[0] === 'details' && $id && $method === 'GET') {
            // Get campaign details
            $result = $this->getCampaignDetails($id);
            echo json_encode($result);
            return;
        } else if ($pathParts[0] === 'update' && $id && $method === 'PUT') {
            // Update campaign status
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid request data']);
                return;
            }
            
            $result = $this->updateCampaignStatus($id, $data);
            echo json_encode($result);
            return;
        }
        
        // Try to use the explicit action parameter if path-based routing didn't match
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'list':
                $result = $this->getCampaigns($status);
                break;
                
            case 'details':
                if (!$id) {
                    http_response_code(400);
                    $result = ['error' => 'Campaign ID is required'];
                    break;
                }
                $result = $this->getCampaignDetails($id);
                break;
                
            case 'update':
                if (!$id) {
                    http_response_code(400);
                    $result = ['error' => 'Campaign ID is required'];
                    break;
                }
                
                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data) {
                    http_response_code(400);
                    $result = ['error' => 'Invalid request data'];
                    break;
                }
                
                $result = $this->updateCampaignStatus($id, $data);
                break;
                
            default:
                // If no action is specified, default to listing campaigns
                $result = $this->getCampaigns($status);
        }
        
        // Return result as JSON
        echo json_encode($result);
    }

    /**
     * Send a notification to the campaign creator about approval
     * @param array $campaign Campaign data
     */
    private function sendApprovalNotification($campaign) {
        // In a real application, this would send an email or other notification
        // to the campaign creator informing them that their campaign was approved
        
        // Example implementation:
        try {
            $db = new Database("givehub");
            $notifications = $db->getCollection('notifications');
            
            $notification = [
                'userId' => $campaign['creatorId'],
                'type' => 'campaign_approved',
                'title' => 'Campaign Approved',
                'message' => "Your campaign '{$campaign['title']}' has been approved and is now live!",
                'campaignId' => $campaign['_id'],
                'createdAt' => date('Y-m-d H:i:s'),
                'read' => false
            ];
            
            $notifications->insertOne($notification);
            
            // In a real app, you would also send an email here
        } catch (Exception $e) {
            error_log("Error sending approval notification: " . $e->getMessage());
        }
    }

    /**
     * Send a notification to the campaign creator about rejection
     * @param array $campaign Campaign data
     */
    private function sendRejectionNotification($campaign) {
        // Similar to approval notification, but for rejection
        try {
            $db = new Database("givehub");
            $notifications = $db->getCollection('notifications');
            
            $feedback = isset($campaign['feedback']) ? $campaign['feedback'] : 'No specific feedback provided.';
            
            $notification = [
                'userId' => $campaign['creatorId'],
                'type' => 'campaign_rejected',
                'title' => 'Campaign Needs Revisions',
                'message' => "Your campaign '{$campaign['title']}' requires changes before it can be published. Admin feedback: {$feedback}",
                'campaignId' => $campaign['_id'],
                'createdAt' => date('Y-m-d H:i:s'),
                'read' => false
            ];
            
            $notifications->insertOne($notification);
            
            // In a real app, you would also send an email here
        } catch (Exception $e) {
            error_log("Error sending rejection notification: " . $e->getMessage());
        }
    }
}

// Handle the request if directly accessed
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    $controller = new AdminCampaignController();
    $controller->handleRequest();
}
