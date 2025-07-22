<?php
// lib/KycController.php
require_once __DIR__ . '/JumioService.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Verification.php';

class KycController {
    private $jumioService;
    private $auth;
    private $verification;

    public function __construct() {
        $this->jumioService = new JumioService();
        $this->auth = new Auth();
        $this->verification = new Verification();
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
                $kycCollection = (new Database())->getCollection('kyc_verifications');
                $record = $kycCollection->findOne([
                    'userId' => new MongoDB\BSON\ObjectId($userId)
                ], ['sort' => ['createdAt' => -1]]);
                if ($record && isset($record['livenessResult'])) {
                    $result['livenessResult'] = $record['livenessResult'];
                }
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
     * Generate compliance report with risk statistics (admin only)
     *
     * @return array Report data
     */
    public function generateComplianceReport() {
        try {
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
            }

            $result = $this->jumioService->generateComplianceReport($filters);

            if ($result['success']) {
                return $this->sendJsonResponse($result);
            }

            return $this->sendErrorResponse($result['error'] ?? 'Failed to generate compliance report');
        } catch (Exception $e) {
            return $this->sendErrorResponse($e->getMessage());
        }
    }

    /**
     * Trigger secondary liveness check for the current user
     */
    public function performLivenessCheck() {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return $this->sendErrorResponse('Authentication required', 401);
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $verificationId = $data['verificationId'] ?? null;
            if (!$verificationId) {
                return $this->sendErrorResponse('Missing verificationId', 400);
            }

            $documents = new Documents();
            $result = $documents->runSecondaryLivenessCheck($verificationId);

            if ($result['success']) {
                return $this->sendJsonResponse($result);
            }

            return $this->sendErrorResponse($result['error'] ?? 'Liveness check failed');
        } catch (Exception $e) {
            return $this->sendErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Calculate and update the authenticated user's risk score
     *
     * @return array Result with score and level
     */
    public function updateRiskScore() {
        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return $this->sendErrorResponse('Authentication required', 401);
            }

            require_once __DIR__ . '/RiskScoringService.php';
            $service = new RiskScoringService();
            $result = $service->calculateRiskScore($userId);

            if ($result['success']) {
                return $this->sendJsonResponse($result);
            }

            return $this->sendErrorResponse($result['error'] ?? 'Unable to calculate risk score');
        } catch (Exception $e) {
            return $this->sendErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * List verifications pending admin review
     */
    public function listPending() {
        try {
            $adminId = $this->getUserId();
            if (!$adminId || !$this->isAdmin($adminId)) {
                return $this->sendErrorResponse('Admin access required', 403);
            }

            $cursor = $this->verification->getCollection()->find(
                ['status' => Verification::STATUS_PENDING_REVIEW],
                ['sort' => ['createdAt' => -1]]
            );

            $items = [];
            foreach ($cursor as $doc) {
                $doc = json_decode(json_encode($doc), true);
                $doc['_id'] = (string) $doc['_id'];
                if (isset($doc['userId']) && $doc['userId'] instanceof MongoDB\BSON\ObjectId) {
                    $doc['userId'] = (string) $doc['userId'];
                }
                $items[] = $doc;
            }

            return $this->sendJsonResponse(['success' => true, 'verifications' => $items]);
        } catch (Exception $e) {
            return $this->sendErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Approve or reject a verification
     */
    public function reviewVerification() {
        try {
            $adminId = $this->getUserId();
            if (!$adminId || !$this->isAdmin($adminId)) {
                return $this->sendErrorResponse('Admin access required', 403);
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !isset($data['verificationId']) || !isset($data['decision'])) {
                return $this->sendErrorResponse('Missing required fields', 400);
            }

            $decision = strtolower($data['decision']);
            if (!in_array($decision, [Verification::STATUS_APPROVED, Verification::STATUS_REJECTED])) {
                return $this->sendErrorResponse('Invalid decision', 400);
            }

            $update = [
                'status' => $decision,
                'reviewedAt' => new MongoDB\BSON\UTCDateTime(),
                'reviewedBy' => new MongoDB\BSON\ObjectId($adminId)
            ];

            $this->verification->getCollection()->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($data['verificationId'])],
                ['$set' => $update]
            );

            return $this->sendJsonResponse(['success' => true]);
        } catch (Exception $e) {
            return $this->sendErrorResponse($e->getMessage(), 500);
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
    public function getUserId() {
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
            $user = $this->auth->getUsersCollection()->findOne([
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
