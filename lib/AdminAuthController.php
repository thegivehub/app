<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Auth.php';

class AdminAuthController {
    private $collection;
    private $auth;

    public function __construct() {
        $db = new Database("givehub");
        $this->collection = $db->getCollection('users');
        $this->auth = new Auth();
    }

    /**
     * Authenticate admin user
     * @param string $username
     * @param string $password
     * @return array Response with token or error
     */
    public function login($username, $password) {
        try {
            // Use existing Auth login method
            $loginResult = $this->auth->login([
                'username' => $username,
                'password' => $password
            ]);
            
            if (!$loginResult['success']) {
                return ['success' => false, 'error' => 'Invalid username or password'];
            }
            
            // Check if user has admin role
            $user = $loginResult['user'];
            $roles = isset($user['roles']) ? $user['roles'] : [];
            
            if (!in_array('admin', $roles)) {
                return ['success' => false, 'error' => 'Unauthorized access'];
            }
            
            // Log the successful admin login
            $this->logAdminAction($user['_id'], 'admin_login', 'Admin login successful');
            
            return [
                'success' => true,
                'token' => $loginResult['tokens']['accessToken'],
                'user' => [
                    'id' => (string)$user['_id'],
                    'username' => $user['username'] ?? '',
                    'displayName' => $user['displayName'] ?? $user['username'] ?? 'Admin'
                ]
            ];
        } catch (Exception $e) {
            error_log("Error in admin login: " . $e->getMessage());
            return ['success' => false, 'error' => 'An error occurred during authentication'];
        }
    }

    /**
     * Validate admin token and get current admin user
     * @return array|null User data or null if invalid
     */
    public function validateToken() {
        try {
            // Use existing Auth getCurrentUser method
            $user = $this->auth->getCurrentUser();
            
            if (!$user) {
                return null;
            }
            
            // Verify user has admin role
            $roles = isset($user['roles']) ? $user['roles'] : [];
            if (!in_array('admin', $roles)) {
                return null;
            }
            
            return $user;
        } catch (Exception $e) {
            error_log("Error validating admin token: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Log admin actions for audit purposes
     * @param string $userId Admin user ID
     * @param string $action Action performed
     * @param string $description Additional details
     * @param array $metadata Optional extra data
     */
    public function logAdminAction($userId, $action, $description, $metadata = []) {
        try {
            $db = new Database("givehub");
            $adminLogs = $db->getCollection('admin_logs');
            
            $log = [
                'userId' => $userId,
                'action' => $action,
                'description' => $description,
                'metadata' => $metadata,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $adminLogs->insertOne($log);
        } catch (Exception $e) {
            error_log("Error logging admin action: " . $e->getMessage());
        }
    }

    /**
     * Handle admin login request
     */
    public function handleLogin() {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $data['username'] = filter_var($data['username'] ?? '', FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_NO_ENCODE_QUOTES);
        $data['password'] = filter_var($data['password'] ?? '', FILTER_UNSAFE_RAW, FILTER_FLAG_NO_ENCODE_QUOTES);
        
        if (!isset($data['username']) || !isset($data['password'])) {
            http_response_code(400); // Bad Request
            echo json_encode(['error' => 'Username and password are required']);
            return;
        }
        
        $result = $this->login($data['username'], $data['password']);
        echo json_encode($result);
    }
    
    /**
     * Handle token verification request
     */
    public function handleVerify() {
        header('Content-Type: application/json');
        
        $user = $this->validateToken();
        
        if (!$user) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Invalid or expired token']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => (string)$user['_id'],
                'username' => $user['username'] ?? '',
                'displayName' => $user['displayName'] ?? $user['username'] ?? 'Admin'
            ]
        ]);
    }
}
