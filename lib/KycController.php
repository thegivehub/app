<?php
// lib/KycController.php
require_once __DIR__ . '/JumioService.php';
require_once __DIR__ . '/Auth.php';

class KycController {
    private $jumioService;
    private $auth;

    public function __construct() {
        $this->jumioService = new JumioService();
        $this->auth = new Auth();
    }

    /**
     * Initialize KYC verification for the current user
     * 
     * @return array Response with redirect URL
     */
    public function initiateVerification() {
        try {
            // Get user ID from token
            $userId = $this->getUserId();
            if (!$userId) {
                return $this->sendErrorResponse('Authentication required', 401);
            }

            // Gather user information for the verification
            $userInfo = [];
            $data = json_decode(file_get_contents('php://input'), true);
            if ($data && isset($data['userInfo'])) {
                $userInfo = $data['userInfo'];
            }

            $result = $this->jumioService->initiateVerification($userId, $userInfo);
            
            if ($result['success']) {
                return $this->sendJsonResponse($result);
            } else {
                return $this->sendErrorResponse($result['error'] ?? 'Failed to initiate verification');
            }
        } catch (Exception $e) {
            return $this->sendErrorResponse($e->getMessage());
        }
    }

    /**
     * Handle webhook callback from Jumio
     * 
     * @return array Processing result
     */
    public function handleWebhook() {
        try {
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!$payload) {
                return $this->sendErrorResponse('Invalid webhook payload', 400);
            }

            $result = $this->jumioService->processWebhook($payload);
            
            if ($result['success']) {
                return $this->sendJsonResponse($result);
            } else {
                return $this->sendErrorResponse($result['error'] ?? 'Failed to process webhook', 400);
            }
        } catch (Exception $e) {
            return $this->sendErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get verification status for the current user
     * 
     * @return array Status details
     */
    public function getVerificationStatus() {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return $this->sendErrorResponse('Authentication required', 401);
            }

            $result = $this->jumioService->getVerificationStatus($userId);
            
            if ($result['success']) {
                return $this->sendJsonResponse($result);
            } else {
                return $this->sendErrorResponse($result['error'] ?? 'Failed to get verification status');
            }
        } catch (Exception $e) {
            return $this->sendErrorResponse($e->getMessage());
        }
    }

    /**
     * Allow admin to override verification status
     * 
     * @return array Operation result
     */
    public function adminOverride() {
        try {
            // Check admin permissions
            $adminId = $this->getUserId();
            if (!$adminId || !$this->isAdmin($adminId)) {
                return $this->sendErrorResponse('Admin access required', 403);
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !isset($data['userId']) || !isset($data['status']) || !isset($data['reason'])) {
                return $this->sendErrorResponse('Missing required fields', 400);
            }

            $result = $this->jumioService->adminOverrideVerification(
                $data['userId'],
                $data['status'],
                $data['reason'],
                $adminId
            );
            
            if ($result['success']) {
                return $this->sendJsonResponse($result);
            } else {
                return $this->sendErrorResponse($result['error'] ?? 'Failed to override verification');
            }
        } catch (Exception $e) {
            return $this->sendErrorResponse($e->getMessage());
        }
    }

    /**
     * Generate a KYC verification report (admin only)
     * 
     * @return array Report data
     */
    public function generateReport() {
        try {
            // Check admin permissions
            $adminId = $this->getUserId();
            if (!$adminId || !$this->isAdmin($adminId)) {
                return $this->sendErrorResponse('Admin access required', 403);
            }

            $filters = [];
            if ($_GET) {
                if (isset($_GET['startDate'])) {
                    $filters['startDate'] = $_GET['startDate'];
                }
                if (isset($_GET['endDate'])) {
                    $filters['endDate'] = $_GET['endDate'];
                }
                if (isset($_GET['status'])) {
                    $filters['status'] = $_GET['status'];
                }
            }

            $result = $this->jumioService->generateKycReport($filters);
            
            if ($result['success']) {
                return $this->sendJsonResponse($result);
            } else {
                return $this->sendErrorResponse($result['error'] ?? 'Failed to generate report');
            }
        } catch (Exception $e) {
            return $this->sendErrorResponse($e->getMessage());
        }
    }

    /**
     * Check if a user is verified (internal API)
     * 
     * @param string $userId User ID to check
     * @return bool Whether user is verified
     */
    public function isUserVerified($userId) {
        return $this->jumioService->isUserVerified($userId);
    }

    /**
     * Get user ID from token
     * 
     * @return string|null User ID
     */
    private function getUserId() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }
        
        $token = $matches[1];
        $decoded = $this->auth->decodeToken($token);
        
        return $decoded ? $decoded->sub : null;
    }

    /**
     * Check if user is an admin
     * 
     * @param string $userId User ID
     * @return bool Whether user is admin
     */
    private function isAdmin($userId) {
        try {
            $user = $this->auth->db->users->findOne([
                '_id' => new MongoDB\BSON\ObjectId($userId)
            ]);
            
            return $user && isset($user['roles']) && in_array('admin', $user['roles']);
        } catch (Exception $e) {
            error_log('Error checking admin status: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send JSON response
     * 
     * @param array $data Response data
     * @param int $code HTTP status code
     * @return array Response array
     */
    private function sendJsonResponse($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        return $data;
    }

    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param int $code HTTP status code
     * @return array Error response
     */
    private function sendErrorResponse($message, $code = 400) {
        $response = [
            'success' => false,
            'error' => $message
        ];
        
        return $this->sendJsonResponse($response, $code);
    }
}
