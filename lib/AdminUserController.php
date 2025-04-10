<?php
// lib/AdminUserController.php
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/db.php';

class AdminUserController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = new Database("givehub");
        $this->auth = new Auth();
    }

    /**
     * Handle user management requests
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Check admin authentication first
        $this->verifyAdminAccess();
        
        $pathParts = [];
        if (isset($_SERVER['PATH_INFO'])) {
            $pathParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
        }
        
        $operation = $pathParts[2] ?? null;
        $id = $_GET['id'] ?? null;
        $posted = json_decode(file_get_contents('php://input'), true);
        
        // Process request based on method and path
        switch ($operation) {
            case 'details':
                if ($method === 'GET' && $id) {
                    $this->getUserDetails($id);
                } else {
                    $this->sendError(400, "Invalid request for user details");
                }
                break;
                
            case 'reset-password':
                if ($method === 'POST' && $id) {
                    $this->resetUserPassword($id, $posted);
                } else {
                    $this->sendError(400, "Invalid request for password reset");
                }
                break;
                
            case 'activity':
                if ($method === 'GET' && $id) {
                    $this->getUserActivity($id);
                } else {
                    $this->sendError(400, "Invalid request for user activity");
                }
                break;
                
            default:
                // Handle standard CRUD operations
                switch ($method) {
                    case 'GET':
                        if ($id) {
                            $this->getUser($id);
                        } else {
                            $this->getAllUsers();
                        }
                        break;
                        
                    case 'POST':
                        $this->createUser($posted);
                        break;
                        
                    case 'PUT':
                        if ($id) {
                            $this->updateUser($id, $posted);
                        } else {
                            $this->sendError(400, "User ID required for update");
                        }
                        break;
                        
                    case 'DELETE':
                        if ($id) {
                            $this->deleteUser($id);
                        } else {
                            $this->sendError(400, "User ID required for deletion");
                        }
                        break;
                        
                    default:
                        $this->sendError(405, "Method not allowed");
                        break;
                }
                break;
        }
    }

    /**
     * Verify the request is from an authenticated admin
     */
    private function verifyAdminAccess() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $this->sendError(401, "Authorization token required");
        }
        
        $token = $matches[1];
        $decoded = $this->auth->decodeToken($token);
        
        if (!$decoded) {
            $this->sendError(401, "Invalid authorization token");
        }
        
        // Get user and check for admin role
        $userId = $decoded->sub;
        $user = $this->db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
        
        if (!$user || !isset($user['roles']) || !in_array('admin', $user['roles'])) {
            $this->sendError(403, "Insufficient permissions");
        }
    }

    /**
     * Get list of all users with optional filtering
     */
    private function getAllUsers() {
        // Set up filter options
        $filter = [];
        
        // Apply status filter if provided
        if (isset($_GET['status']) && $_GET['status'] !== 'all') {
            $filter['status'] = $_GET['status'];
        }
        
        // Apply role filter if provided
        if (isset($_GET['role']) && $_GET['role'] === 'admin') {
            $filter['roles'] = 'admin';
        }
        
        // Apply search query if provided
        if (isset($_GET['q']) && !empty($_GET['q'])) {
            $searchQuery = $_GET['q'];
            $filter['$or'] = [
                ['username' => ['$regex' => $searchQuery, '$options' => 'i']],
                ['email' => ['$regex' => $searchQuery, '$options' => 'i']],
                ['displayName' => ['$regex' => $searchQuery, '$options' => 'i']]
            ];
        }
        
        // Set pagination options
        $options = [];
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
        $options['skip'] = ($page - 1) * $limit;
        $options['limit'] = $limit;
        
        // Set sort options
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'createdAt';
        $order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 1 : -1;
        $options['sort'] = [$sort => $order];
        
        // Get users from database
        $users = $this->db->getCollection('users')->find($filter, $options);
        $total = $this->db->getCollection('users')->count($filter);
        
        // Calculate statistics
        $stats = [
            'active' => $this->db->getCollection('users')->count(['status' => 'active']),
            'pending' => $this->db->getCollection('users')->count(['status' => 'pending']),
            'suspended' => $this->db->getCollection('users')->count(['status' => 'suspended']),
            'total' => $this->db->getCollection('users')->count([])
        ];
        
        // Send response
        $this->sendResponse([
            'users' => $users,
            'stats' => $stats,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Get a specific user by ID
     */
    private function getUser($id) {
        try {
            $user = $this->db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            
            if (!$user) {
                $this->sendError(404, "User not found");
            }
            
            $this->sendResponse($user);
        } catch (Exception $e) {
            $this->sendError(500, "Error retrieving user: " . $e->getMessage());
        }
    }

    /**
     * Get detailed information about a user
     */
    private function getUserDetails($id) {
        try {
            $user = $this->db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            
            if (!$user) {
                $this->sendError(404, "User not found");
            }
            
            // Add any additional user details as needed
            // For example, recent activity, campaigns, donations, etc.
            
            // Get user's campaigns
            $campaigns = $this->db->getCollection('campaigns')->find(['creatorId' => $id], ['limit' => 5, 'sort' => ['createdAt' => -1]]);
            
            // Add campaigns to user details
            $user['campaigns'] = $campaigns;
            
            $this->sendResponse($user);
        } catch (Exception $e) {
            $this->sendError(500, "Error retrieving user details: " . $e->getMessage());
        }
    }

    /**
     * Create a new user
     */
    private function createUser($data) {
        try {
            // Validate required fields
            $requiredFields = ['email', 'username', 'password', 'firstName', 'lastName'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $this->sendError(400, "Missing required field: $field");
                }
            }
            
            // Check if user already exists
            $existingUser = $this->db->getCollection('users')->findOne([
                '$or' => [
                    ['email' => $data['email']],
                    ['username' => $data['username']]
                ]
            ]);
            
            if ($existingUser) {
                $this->sendError(409, "User with this email or username already exists");
            }
            
            // Get current admin ID if available
            $adminId = null;
            try {
                $auth = new Auth();
                $adminId = $auth->getUserIdFromToken();
            } catch (Exception $e) {
                error_log("Failed to get admin ID: " . $e->getMessage());
            }

            // Prepare user data
            $userData = [
                'email' => $data['email'],
                'username' => $data['username'],
                'personalInfo' => [
                    'firstName' => $data['firstName'] ?? '',
                    'lastName' => $data['lastName'] ?? '',
                    'email' => $data['email'],
                    'language' => $data['language'] ?? 'en',
                    'country' => $data['country'] ?? '',
                    'phone' => $data['phone'] ?? '',
                ],
                'auth' => [
                    'passwordHash' => password_hash($data['password'], PASSWORD_DEFAULT),
                    'verified' => true,
                    'twoFactorEnabled' => false,
                    'lastLogin' => new MongoDB\BSON\UTCDateTime()
                ],
                'profile' => [
                    'avatar' => null,
                    'bio' => $data['bio'] ?? '',
                    'preferences' => [
                        'emailNotifications' => true,
                        'currency' => 'USD',
                    ]
                ],
                'roles' => $data['roles'] ?? ['user'],
                'status' => 'active',
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                'createdBy' => $adminId
            ];
            
            // Insert user
            $result = $this->db->getCollection('users')->insertOne($userData);
            
            if (!$result['success']) {
                $this->sendError(500, "Failed to create user");
            }
            
            // Get the created user
            $user = $this->db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($result['id'])]);
            
            $this->sendResponse($user, 201);
        } catch (Exception $e) {
            $this->sendError(500, "Error creating user: " . $e->getMessage());
        }
    }

    /**
     * Update an existing user
     */
    private function updateUser($id, $data) {
        try {
            // Get current user
            $user = $this->db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            
            if (!$user) {
                $this->sendError(404, "User not found");
            }
            
            // Prepare update data
            $updateData = [];
            
            // Update basic fields
            if (isset($data['email'])) {
                $updateData['email'] = $data['email'];
                $updateData['personalInfo.email'] = $data['email'];
            }
            
            if (isset($data['username'])) {
                $updateData['username'] = $data['username'];
            }
            
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            
            // Update personal info
            if (isset($data['firstName'])) {
                $updateData['personalInfo.firstName'] = $data['firstName'];
            }
            
            if (isset($data['lastName'])) {
                $updateData['personalInfo.lastName'] = $data['lastName'];
            }
            
            if (isset($data['phone'])) {
                $updateData['personalInfo.phone'] = $data['phone'];
            }
            
            // Update display name if first or last name changed
            if (isset($data['firstName']) || isset($data['lastName'])) {
                $firstName = $data['firstName'] ?? $user['personalInfo']['firstName'] ?? '';
                $lastName = $data['lastName'] ?? $user['personalInfo']['lastName'] ?? '';
                $updateData['displayName'] = $firstName . ' ' . $lastName;
            }
            
            // Update password if provided
            if (isset($data['password']) && !empty($data['password'])) {
                $updateData['auth.passwordHash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            // Update roles if provided
            if (isset($data['roles'])) {
                $updateData['roles'] = is_array($data['roles']) ? $data['roles'] : [$data['roles']];
                
                // Ensure 'user' role is always present
                if (!in_array('user', $updateData['roles'])) {
                    $updateData['roles'][] = 'user';
                }
            }
            
            // Set update timestamp
            $updateData['updatedAt'] = new MongoDB\BSON\UTCDateTime();
            
            // Update user
            $result = $this->db->getCollection('users')->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                ['$set' => $updateData]
            );
            
            if (!$result['success']) {
                $this->sendError(500, "Failed to update user");
            }
            
            // Get updated user
            $updatedUser = $this->db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            
            $this->sendResponse($updatedUser);
        } catch (Exception $e) {
            $this->sendError(500, "Error updating user: " . $e->getMessage());
        }
    }

    /**
     * Delete a user
     */
    private function deleteUser($id) {
        try {
            // Check if user exists
            $user = $this->db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            
            if (!$user) {
                $this->sendError(404, "User not found");
            }
            
            // Delete user
            $result = $this->db->getCollection('users')->deleteOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            
            if (!$result['success']) {
                $this->sendError(500, "Failed to delete user");
            }
            
            $this->sendResponse(["message" => "User deleted successfully"]);
        } catch (Exception $e) {
            $this->sendError(500, "Error deleting user: " . $e->getMessage());
        }
    }

    /**
     * Reset a user's password
     */
    private function resetUserPassword($id, $data) {
        try {
            // Check if user exists
            $user = $this->db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            
            if (!$user) {
                $this->sendError(404, "User not found");
            }
            
            // Generate a temporary password if not provided
            $password = $data['tempPassword'] ?? $this->generateTempPassword();
            
            // Update user's password
            $result = $this->db->getCollection('users')->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                ['$set' => [
                    'auth.passwordHash' => password_hash($password, PASSWORD_DEFAULT),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            
            if (!$result['success']) {
                $this->sendError(500, "Failed to reset password");
            }
            
            $this->sendResponse([
                "message" => "Password reset successfully",
                "tempPassword" => $password
            ]);
        } catch (Exception $e) {
            $this->sendError(500, "Error resetting password: " . $e->getMessage());
        }
    }

    /**
     * Get user's recent activity
     */
    private function getUserActivity($id) {
        try {
            // Check if user exists
            $user = $this->db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
            
            if (!$user) {
                $this->sendError(404, "User not found");
            }
            
            // Get user's campaigns
            $campaigns = $this->db->getCollection('campaigns')->find(
                ['creatorId' => $id],
                ['limit' => 5, 'sort' => ['createdAt' => -1]]
            );
            
            // Get user's donations (if applicable)
            $donations = $this->db->getCollection('donations')->find(
                ['userId' => $id],
                ['limit' => 5, 'sort' => ['createdAt' => -1]]
            );
            
            // Get user's login history
            $loginHistory = isset($user['auth']['loginHistory']) ? $user['auth']['loginHistory'] : [];
            
            // Combine all activity
            $activity = [
                'campaigns' => $campaigns,
                'donations' => $donations,
                'logins' => $loginHistory
            ];
            
            $this->sendResponse($activity);
        } catch (Exception $e) {
            $this->sendError(500, "Error retrieving user activity: " . $e->getMessage());
        }
    }

    /**
     * Generate a random temporary password
     */
    private function generateTempPassword($length = 10) {
        $charset = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomIndex = random_int(0, strlen($charset) - 1);
            $password .= $charset[$randomIndex];
        }
        
        return $password;
    }

    /**
     * Send JSON response
     */
    private function sendResponse($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Send error response
     */
    private function sendError($code, $message) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(["error" => $message]);
        exit;
    }
}
