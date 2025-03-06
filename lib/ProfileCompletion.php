<?php
/**
 * ProfileCompletion - A class to calculate and provide user profile completion data
 */
class ProfileCompletion {
    private $db;

    public function __construct() {
        // Initialize database connection
        $this->db = new Database();
    }

    /**
     * Calculate profile completion percentage for a user
     * 
     * @param string $userId The user ID
     * @return array Profile completion data including percentage and incomplete fields
     */
    public function getCompletionData($userId) {
        try {
            // Get user data from database
            $user = $this->db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
            
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Define required fields and their paths in the user object
            $requiredFields = [
                ['name' => 'Display Name', 'path' => 'displayName'],
                ['name' => 'Email', 'path' => 'email'],
                ['name' => 'First Name', 'path' => 'personalInfo.firstName'],
                ['name' => 'Last Name', 'path' => 'personalInfo.lastName'],
                ['name' => 'Phone Number', 'path' => 'personalInfo.phone'],
                ['name' => 'Location', 'path' => 'personalInfo.location'],
                ['name' => 'Bio', 'path' => 'profile.bio'],
                ['name' => 'Profile Picture', 'path' => 'profile.avatar']
            ];
            
            // Track completion status
            $completedFields = [];
            $incompleteFields = [];
            
            foreach ($requiredFields as $field) {
                $isComplete = $this->checkFieldCompletion($user, $field['path']);
                
                if ($isComplete) {
                    $completedFields[] = $field['name'];
                } else {
                    $incompleteFields[] = $field['name'];
                }
            }
            
            // Calculate percentage
            $totalFields = count($requiredFields);
            $completedCount = count($completedFields);
            $percentage = ($totalFields > 0) ? round(($completedCount / $totalFields) * 100) : 0;
            
            return [
                'percentage' => $percentage,
                'completedFields' => $completedFields,
                'incompleteFields' => $incompleteFields,
                'totalFields' => $totalFields,
                'completedCount' => $completedCount
            ];
        } catch (Exception $e) {
            error_log("Profile completion calculation error: " . $e->getMessage());
            return [
                'percentage' => 0,
                'completedFields' => [],
                'incompleteFields' => ['All fields'],
                'totalFields' => 1,
                'completedCount' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if a specific field is completed
     * 
     * @param array $user User data
     * @param string $path Dot notation path to the field
     * @return bool True if field is completed, false otherwise
     */
    private function checkFieldCompletion($user, $path) {
        $parts = explode('.', $path);
        $current = $user;
        
        foreach ($parts as $part) {
            if (!isset($current[$part]) || empty($current[$part])) {
                return false;
            }
            $current = $current[$part];
        }
        
        return true;
    }
    
    /**
     * Get API endpoint for profile completion
     * 
     * @return array Profile completion data for API response
     */
    public function getProfileCompletionEndpoint() {
        try {
            // Get user ID from token
            $auth = new Auth();
            $userId = $this->getUserIdFromToken();
            
            if (!$userId) {
                throw new Exception('Unauthorized');
            }
            
            $completionData = $this->getCompletionData($userId);
            
            // Format response for API
            return [
                'success' => true,
                'data' => $completionData
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Extract user ID from JWT token
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
            $auth = new Auth();
            $decoded = $auth->decodeToken($token);
            return $decoded->sub;
        } catch (Exception $e) {
            error_log("Token decode error: " . $e->getMessage());
            return null;
        }
    }
}
