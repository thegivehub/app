<?php
/**
 * AdminDashboardController.php
 * 
 * This file should be placed in your /lib directory to provide dashboard API endpoints
 * for the admin dashboard.
 */

class AdminDashboardController {
    private $db;
    private $usersCollection;
    private $campaignsCollection;

    public function __construct() {
        $this->db = new Database("givehub");
        $this->usersCollection = $this->db->getCollection('users');
        $this->campaignsCollection = $this->db->getCollection('campaigns');
    }

    /**
     * Handle incoming dashboard API requests
     */
    public function handleRequest() {
        // Check if user is authenticated as admin
        if (!$this->isAdminAuthenticated()) {
            $this->sendResponse(401, ['error' => 'Unauthorized access']);
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['PATH_INFO'] ?? '';
        $pathParts = explode('/', trim($path, '/'));
        
        // Remove 'admin' and 'dashboard' from path parts if present
        if (count($pathParts) > 0 && $pathParts[0] === 'admin') {
            array_shift($pathParts);
        }
        if (count($pathParts) > 0 && $pathParts[0] === 'dashboard') {
            array_shift($pathParts);
        }

        $action = $pathParts[0] ?? 'summary';

        switch ($action) {
            case 'summary':
                $this->getSummaryData();
                break;
            case 'users':
                $this->getUsersData();
                break;
            case 'campaigns':
                $this->getCampaignsData();
                break;
            case 'activity':
                $this->getActivityData();
                break;
            default:
                $this->sendResponse(404, ['error' => 'Endpoint not found']);
                break;
        }
    }

    /**
     * Get summary data for the dashboard
     */
    private function getSummaryData() {
        try {
            // Get summary data about users
            $usersStats = $this->getUsersStats();
            
            // Get summary data about campaigns
            $campaignsStats = $this->getCampaignsStats();
            
            // Combine data for response
            $response = [
                'users' => $usersStats,
                'campaigns' => $campaignsStats,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $this->sendResponse(200, $response);
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => 'Failed to load summary data: ' . $e->getMessage()]);
        }
    }

    /**
     * Get users data for the dashboard
     */
    private function getUsersData() {
        try {
            // Get user statistics
            $stats = $this->getUsersStats();
            
            // Get list of recent users (limit to 10)
            $recentUsers = $this->usersCollection->find(
                [], 
                [
                    'sort' => ['created' => -1], 
                    'limit' => 10,
                    'projection' => [
                        '_id' => 1,
                        'username' => 1,
                        'email' => 1,
                        'displayName' => 1,
                        'status' => 1,
                        'created' => 1,
                        'personalInfo' => 1
                    ]
                ]
            );
            
            // Format user data for response
            $formattedUsers = [];
            foreach ($recentUsers as $user) {
                // Set default values for missing fields
                $status = $user['status'] ?? 'pending';
                $created = $user['created'] ?? '';
                $displayName = $user['displayName'] ?? ($user['username'] ?? 'Unnamed User');
                
                // Extract email from personalInfo if not directly in user object
                $email = $user['email'] ?? ($user['personalInfo']['email'] ?? '');
                
                $formattedUsers[] = [
                    '_id' => (string)$user['_id'],
                    'displayName' => $displayName,
                    'username' => $user['username'] ?? '',
                    'email' => $email,
                    'status' => $status,
                    'created' => $created
                ];
            }
            
            // Prepare response
            $response = [
                'stats' => $stats,
                'users' => $formattedUsers
            ];
            
            $this->sendResponse(200, $response);
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => 'Failed to load users data: ' . $e->getMessage()]);
        }
    }

    /**
     * Get campaigns data for the dashboard
     */
    private function getCampaignsData() {
        try {
            // Get campaign statistics
            $stats = $this->getCampaignsStats();
            
            // Get list of recent campaigns (limit to 10)
            $recentCampaigns = $this->campaignsCollection->find(
                [], 
                [
                    'sort' => ['createdAt' => -1], 
                    'limit' => 10
                ]
            );
            
            // Format campaign data for response
            $formattedCampaigns = [];
            foreach ($recentCampaigns as $campaign) {
                // Set default values for missing fields
                $title = $campaign['title'] ?? 'Untitled Campaign';
                $status = $campaign['status'] ?? 'pending';
                $createdAt = $campaign['createdAt'] ?? '';
                $fundingGoal = $campaign['fundingGoal'] ?? 0;
                $creatorName = $campaign['creatorName'] ?? '';
                
                // If creator name is missing, try to get it
                if (empty($creatorName) && isset($campaign['creatorId'])) {
                    $creatorId = $campaign['creatorId'];
                    $creator = $this->usersCollection->findOne(['_id' => $creatorId]);
                    if ($creator) {
                        $creatorName = $creator['displayName'] ?? ($creator['username'] ?? 'Unknown Creator');
                    }
                }
                
                $formattedCampaigns[] = [
                    '_id' => (string)$campaign['_id'],
                    'title' => $title,
                    'creatorName' => $creatorName,
                    'status' => $status,
                    'createdAt' => $createdAt,
                    'fundingGoal' => $fundingGoal,
                    'raised' => $campaign['raised'] ?? 0
                ];
            }
            
            // Calculate monthly data for charting
            $monthlyData = $this->calculateMonthlyCampaignStats();
            
            // Prepare response
            $response = [
                'stats' => $stats,
                'campaigns' => $formattedCampaigns,
                'monthlyData' => $monthlyData
            ];
            
            $this->sendResponse(200, $response);
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => 'Failed to load campaigns data: ' . $e->getMessage()]);
        }
    }

    /**
     * Get activity data for the dashboard
     */
    private function getActivityData() {
        try {
            // Get recent activity
            $activities = [];
            
            // Get recent user registrations
            $recentUsers = $this->usersCollection->find(
                [], 
                [
                    'sort' => ['created' => -1], 
                    'limit' => 6,
                    'projection' => [
                        '_id' => 1,
                        'username' => 1,
                        'displayName' => 1,
                        'created' => 1
                    ]
                ]
            );
            
            foreach ($recentUsers as $user) {
                $displayName = $user['displayName'] ?? ($user['username'] ?? 'New User');
                $activities[] = [
                    'type' => 'user_joined',
                    'title' => 'New user registered',
                    'details' => $displayName,
                    'timestamp' => $user['created'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            // Get recent campaign creations
            $recentCampaigns = $this->campaignsCollection->find(
                [], 
                [
                    'sort' => ['createdAt' => -1],
                    'limit' => 6,
                    'projection' => [
                        '_id' => 1,
                        'title' => 1,
                        'creatorName' => 1,
                        'creatorId' => 1,
                        'createdAt' => 1
                    ]
                ]
            );
            
            foreach ($recentCampaigns as $campaign) {
                $title = $campaign['title'] ?? 'Untitled Campaign';
                $creatorName = $campaign['creatorName'] ?? '';
                
                // If creator name is missing, try to get it
                if (empty($creatorName) && isset($campaign['creatorId'])) {
                    $creatorId = $campaign['creatorId'];
                    $creator = $this->usersCollection->findOne(['_id' => $creatorId]);
                    if ($creator) {
                        $creatorName = $creator['displayName'] ?? ($creator['username'] ?? 'Unknown Creator');
                    }
                }
                
                $activities[] = [
                    'type' => 'campaign_created',
                    'title' => 'New campaign created',
                    'details' => $title,
                    'user' => $creatorName,
                    'timestamp' => $campaign['createdAt'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            // Get recent campaign status changes
            $recentReviews = $this->campaignsCollection->find(
                ['reviewedAt' => ['$exists' => true]],
                [
                    'sort' => ['reviewedAt' => -1],
                    'limit' => 6,
                    'projection' => [
                        '_id' => 1,
                        'title' => 1,
                        'status' => 1,
                        'reviewedAt' => 1,
                        'reviewedBy' => 1
                    ]
                ]
            );
            
            foreach ($recentReviews as $campaign) {
                $title = $campaign['title'] ?? 'Untitled Campaign';
                $status = $campaign['status'] ?? 'pending';
                $reviewedBy = $campaign['reviewedBy'] ?? 'Admin User';
                
                $activityType = $status === 'active' ? 'campaign_approved' : 'campaign_rejected';
                $activityTitle = $status === 'active' ? 'Campaign approved' : 'Campaign rejected';
                
                $activities[] = [
                    'type' => $activityType,
                    'title' => $activityTitle,
                    'details' => $title,
                    'user' => $reviewedBy,
                    'timestamp' => $campaign['reviewedAt'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            // Sort activities by timestamp (newest first)
            usort($activities, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            
            // Take only the most recent activities (limit to 10)
            $activities = array_slice($activities, 0, 10);
            
            $this->sendResponse(200, $activities);
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => 'Failed to load activity data: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Get user statistics
     * 
     * @return array User statistics
     */
    private function getUsersStats() {
        // Count total users
        $totalUsers = $this->usersCollection->count();
        
        // Count active users
        $activeUsers = $this->usersCollection->count(['status' => 'active']);
        
        // Count pending users
        $pendingUsers = $this->usersCollection->count(['status' => 'pending']);
        
        // Count suspended users
        $suspendedUsers = $this->usersCollection->count(['status' => 'suspended']);
        
        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'pending' => $pendingUsers,
            'suspended' => $suspendedUsers
        ];
    }
    
    /**
     * Get campaign statistics
     * 
     * @return array Campaign statistics
     */
    private function getCampaignsStats() {
        // Count total campaigns
        $totalCampaigns = $this->campaignsCollection->count();
        
        // Count active campaigns
        $activeCampaigns = $this->campaignsCollection->count(['status' => 'active']);
        
        // Count pending campaigns
        $pendingCampaigns = $this->campaignsCollection->count(['status' => 'pending']);
        
        // Count rejected campaigns
        $rejectedCampaigns = $this->campaignsCollection->count(['status' => 'rejected']);
        
        // Calculate total funding
        $totalFunding = 0;
        $campaigns = $this->campaignsCollection->find();
        
        foreach ($campaigns as $campaign) {
            // Try different field names that might hold the funding amount
            $raised = isset($campaign['raised']) ? floatval($campaign['raised']) : 0;
            $totalFunding += $raised;
        }
        
        return [
            'total' => $totalCampaigns,
            'active' => $activeCampaigns,
            'pending' => $pendingCampaigns,
            'rejected' => $rejectedCampaigns,
            'totalFunding' => $totalFunding
        ];
    }
    
    /**
     * Calculate monthly campaign statistics for the last 6 months
     * 
     * @return array Monthly campaign statistics
     */
    private function calculateMonthlyCampaignStats() {
        $months = [];
        $monthlyData = [];
        
        // Get the last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $date = new DateTime();
            $date->modify("-$i month");
            $month = $date->format('M'); // Short month name (Jan, Feb, etc.)
            $months[] = $month;
            
            $monthStart = $date->format('Y-m-01 00:00:00');
            $monthEnd = $date->format('Y-m-t 23:59:59');
            
            // Count campaigns created in this month
            $totalInMonth = $this->campaignsCollection->count([
                'createdAt' => [
                    '$gte' => $monthStart,
                    '$lte' => $monthEnd
                ]
            ]);
            
            // Count active campaigns created in this month
            $activeInMonth = $this->campaignsCollection->count([
                'createdAt' => [
                    '$gte' => $monthStart,
                    '$lte' => $monthEnd
                ],
                'status' => 'active'
            ]);
            
            // Count pending campaigns created in this month
            $pendingInMonth = $this->campaignsCollection->count([
                'createdAt' => [
                    '$gte' => $monthStart,
                    '$lte' => $monthEnd
                ],
                'status' => 'pending'
            ]);
            
            // Count rejected campaigns created in this month
            $rejectedInMonth = $this->campaignsCollection->count([
                'createdAt' => [
                    '$gte' => $monthStart,
                    '$lte' => $monthEnd
                ],
                'status' => 'rejected'
            ]);
            
            // Add to monthly data
            $monthlyData[] = [
                'month' => $month,
                'total' => $totalInMonth,
                'active' => $activeInMonth,
                'pending' => $pendingInMonth,
                'rejected' => $rejectedInMonth
            ];
        }
        
        return $monthlyData;
    }
    
    /**
     * Check if the current user is authenticated as an admin
     * 
     * @return bool True if user is authenticated as admin, false otherwise
     */
    private function isAdminAuthenticated() {
        // Get authorization header
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return false;
        }
        
        $token = $matches[1];
        
        // In a real implementation, you would verify the token
        // and check if the user has admin privileges
        
        try {
            // Simple token validation - in production use proper JWT validation
            if (empty($token)) {
                return false;
            }
            
            // Parse the token payload
            list($header, $payload, $signature) = explode('.', $token);
            $payloadJson = base64_decode(strtr($payload, '-_', '+/'));
            $payloadData = json_decode($payloadJson, true);
            
            // Check for admin role in the payload
            if (isset($payloadData['roles']) && is_array($payloadData['roles'])) {
                return in_array('admin', $payloadData['roles']);
            }
            
            // If roles aren't in the token, try to look up the user
            if (isset($payloadData['sub']) || isset($payloadData['userId']) || isset($payloadData['_id'])) {
                $userId = $payloadData['sub'] ?? ($payloadData['userId'] ?? $payloadData['_id']);
                
                $user = $this->usersCollection->findOne(['_id' => $userId]);
                
                if ($user && isset($user['roles']) && is_array($user['roles'])) {
                    return in_array('admin', $user['roles']);
                }
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Error validating admin token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send JSON response and exit
     * 
     * @param int $code HTTP response code
     * @param mixed $data Data to be JSON encoded
     */
    private function sendResponse($code, $data) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
