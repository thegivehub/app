<?php
/**
 * AdminAuth Class
 * Handles administrator authentication and permissions
 */
class AdminAuth {
    private $db;
    private $adminToken;
    private $adminData;
    
    /**
     * Initialize AdminAuth object
     */
    public function __construct() {
        $this->db = new Database();
        $this->adminToken = $this->getTokenFromRequest();
    }
    
    /**
     * Get admin token from request headers or query parameter
     * 
     * @return string|null The admin token if found, null otherwise
     */
    private function getTokenFromRequest() {
        // Check Authorization header
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
                return $matches[1];
            }
        }
        
        // Check X-Admin-Token header
        if (isset($headers['X-Admin-Token'])) {
            return $headers['X-Admin-Token'];
        }
        
        // Check query parameter
        if (isset($_GET['admin_token'])) {
            return $_GET['admin_token'];
        }
        
        return null;
    }
    
    /**
     * Verify administrator token is valid
     * 
     * @return bool True if token is valid, false otherwise
     */
    public function verifyAdminToken() {
        if (!$this->adminToken) {
            $this->logSecurityEvent('missing_token');
            return false;
        }
        
        // Rate limiting check
        if ($this->isRateLimited()) {
            $this->logSecurityEvent('rate_limit_exceeded');
            return false;
        }
        
        try {
            // Find admin with this token
            $admin = $this->db->getCollection('administrators')->findOne([
                'tokens.token' => $this->adminToken,
                'active' => true
            ]);
            
            if (!$admin) {
                return false;
            }
            
            // Check token expiration
            foreach ($admin['tokens'] as $tokenData) {
                if ($tokenData['token'] === $this->adminToken) {
                    // Token found, check if expired
                    $expiresAt = $tokenData['expiresAt'] ?? null;
                    
                    if ($expiresAt) {
                        $expiryTimestamp = $expiresAt->toDateTime()->getTimestamp();
                        if ($expiryTimestamp < time()) {
                            // Token expired
                            return false;
                        }
                    }
                    
                    // Token is valid, store admin data
                    $this->adminData = $admin;
                    
                    // Update last used time
                    $this->db->getCollection('administrators')->updateOne(
                        ['_id' => $admin['_id'], 'tokens.token' => $this->adminToken],
                        [
                            '$set' => [
                                'tokens.$.lastUsed' => new MongoDB\BSON\UTCDateTime(),
                                'lastLogin' => new MongoDB\BSON\UTCDateTime()
                            ]
                        ]
                    );
                    
                    return true;
                }
            }
            
            // Token not found in admin's tokens
            return false;
        } catch (Exception $e) {
            error_log("Admin authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get the current admin's ID
     * 
     * @return string|null The admin ID if authenticated, null otherwise
     */
    public function getAdminId() {
        if (!$this->adminData) {
            $this->verifyAdminToken();
        }
        
        return isset($this->adminData['_id']) ? (string)$this->adminData['_id'] : null;
    }
    
    /**
     * Check if admin has specific permission
     * 
     * @param string $permission The permission to check
     * @return bool True if admin has permission, false otherwise
     */
    public function hasPermission($permission) {
        if (!$this->adminData) {
            $this->verifyAdminToken();
        }
        
        if (!$this->adminData) {
            return false;
        }
        
        // Super admins have all permissions
        if (isset($this->adminData['isSuperAdmin']) && $this->adminData['isSuperAdmin']) {
            return true;
        }
        
        // Check specific permission
        $permissions = $this->adminData['permissions'] ?? [];
        return in_array($permission, $permissions);
    }
    
    /**
     * Get current admin's data
     * 
     * @return array|null Admin data if authenticated, null otherwise
     */
    public function getAdminData() {
        if (!$this->adminData) {
            $this->verifyAdminToken();
        }
        
        return $this->adminData;
    }
} 
