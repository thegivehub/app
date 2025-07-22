<?php
// lib/JumioService.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

class JumioService {
    private $db;
    private $kycCollection;
    private $apiToken;
    private $apiSecret;
    private $webhookSecret;
    private $baseUrl;
    private $callbackUrl;

    public function __construct() {
        $this->db = new Database();
        $this->kycCollection = $this->db->getCollection('kyc_verifications');
        
        // Jumio API credentials - should be set in environment variables
        $this->apiToken = getenv('JUMIO_API_TOKEN') ?: '';
        $this->apiSecret = getenv('JUMIO_API_SECRET') ?: '';
        $this->webhookSecret = getenv('JUMIO_WEBHOOK_SECRET') ?: '';
        
        // Environment-specific URLs
        $isProduction = (APP_ENV === 'production');
        $this->baseUrl = $isProduction 
            ? 'https://netverify.com/api/v4' 
            : 'https://sandbox-netverify.com/api/v4';
            
        $appDomain = $isProduction 
            ? 'https://app.thegivehub.com' 
            : 'https://dev.thegivehub.com';
            
        $this->callbackUrl = $appDomain . '/api/kyc/webhook';
    }

    /**
     * Initiate verification process for a user
     * 
     * @param string $userId The user ID
     * @param array $userInfo Optional additional user information
     * @return array Response with redirect URL or error
     */
    public function initiateVerification($userId, $userInfo = []) {
        // Check if user already has a verification in progress
        $existingVerification = $this->kycCollection->findOne([
            'userId' => new MongoDB\BSON\ObjectId($userId),
            'status' => ['$in' => ['PENDING', 'INITIATED']]
        ]);
        
        if ($existingVerification) {
            // If there's an existing verification, return its redirect URL
            return [
                'success' => true,
                'redirectUrl' => $existingVerification['redirectUrl'],
                'message' => 'Verification already initiated',
                'verificationId' => (string)$existingVerification['_id']
            ];
        }
        
        // Prepare the verification request data
        $requestData = [
            'customerInternalReference' => $userId,
            'userReference' => $userId,
            'callbackUrl' => $this->callbackUrl,
            'workflowId' => getenv('JUMIO_WORKFLOW_ID') ?: 100,
            'successUrl' => getenv('JUMIO_SUCCESS_URL') ?: 'https://app.thegivehub.com/verification/success',
            'errorUrl' => getenv('JUMIO_ERROR_URL') ?: 'https://app.thegivehub.com/verification/error'
        ];
        
        // Add user information if provided
        if (!empty($userInfo)) {
            if (isset($userInfo['firstName']) && isset($userInfo['lastName'])) {
                $requestData['firstName'] = $userInfo['firstName'];
                $requestData['lastName'] = $userInfo['lastName'];
            }
            
            if (isset($userInfo['email'])) {
                $requestData['email'] = $userInfo['email'];
            }
        }
        
        try {
            // Make API request to Jumio
            $response = $this->makeJumioRequest('/initiate', $requestData);
            
            if (isset($response['redirectUrl'])) {
                // Store verification details in database
                $verificationData = [
                    'userId' => new MongoDB\BSON\ObjectId($userId),
                    'jumioReference' => $response['transactionReference'],
                    'redirectUrl' => $response['redirectUrl'],
                    'status' => 'INITIATED',
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                    'requestData' => $requestData,
                    'responseData' => $response
                ];
                
                $insertResult = $this->kycCollection->insertOne($verificationData);
                
                return [
                    'success' => true,
                    'redirectUrl' => $response['redirectUrl'],
                    'verificationId' => (string)$insertResult['id']
                ];
            } else {
                error_log('Jumio initiation failed with response: ' . json_encode($response));
                return [
                    'success' => false,
                    'error' => 'Failed to initiate verification'
                ];
            }
        } catch (Exception $e) {
            error_log('Jumio initiation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process webhook callback from Jumio
     * 
     * @param array $payload Webhook payload
     * @return array Processing result
     */
    public function processWebhook($payload) {
        try {
            // Validate webhook signature if available
            if (isset($_SERVER['HTTP_X_JUMIO_SIGNATURE'])) {
                $signature = $_SERVER['HTTP_X_JUMIO_SIGNATURE'];
                $rawBody = file_get_contents('php://input');
                
                if (!$this->validateWebhookSignature($signature, $rawBody)) {
                    error_log('Invalid webhook signature');
                    return [
                        'success' => false,
                        'error' => 'Invalid signature'
                    ];
                }
            }
            
            // Extract transaction reference
            $transactionReference = $payload['transactionReference'] ?? null;
            if (!$transactionReference) {
                error_log('Missing transaction reference in webhook payload');
                return [
                    'success' => false,
                    'error' => 'Missing transaction reference'
                ];
            }
            
            // Find the verification record
            $verification = $this->kycCollection->findOne([
                'jumioReference' => $transactionReference
            ]);
            
            if (!$verification) {
                error_log('Verification not found for transaction reference: ' . $transactionReference);
                return [
                    'success' => false,
                    'error' => 'Verification not found'
                ];
            }
            
            // Update verification status
            $status = $payload['verificationStatus'] ?? 'UNKNOWN';
            $updateData = [
                'status' => $status,
                'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                'webhookData' => $payload
            ];
            
            // Add verification result details if available
            if (isset($payload['document'])) {
                $updateData['documentData'] = $payload['document'];
            }
            
            if (isset($payload['transaction'])) {
                $updateData['transactionData'] = $payload['transaction'];
            }
            
            // Add verification result
            $verificationResult = 'PENDING';
            if ($status === 'APPROVED_VERIFIED') {
                $verificationResult = 'APPROVED';
            } else if ($status === 'DENIED_FRAUD') {
                $verificationResult = 'REJECTED';
            } else if (strpos($status, 'ERROR') !== false) {
                $verificationResult = 'ERROR';
            } else if ($status === 'EXPIRED') {
                $verificationResult = 'EXPIRED';
            }
            
            $updateData['verificationResult'] = $verificationResult;
            
            // Update the record
            $updateResult = $this->kycCollection->updateOne(
                ['jumioReference' => $transactionReference],
                ['$set' => $updateData]
            );
            
            // Get the user ID for notification
            $userId = $verification['userId'];
            
            // Send notification to user about verification result
            $this->notifyUser($userId, $verificationResult, $payload);

            // Recalculate user's risk score after verification update
            require_once __DIR__ . '/RiskScoringService.php';
            $riskService = new RiskScoringService();
            $riskService->calculateRiskScore((string)$userId);
            
            return [
                'success' => true,
                'message' => 'Webhook processed successfully',
                'status' => $status,
                'result' => $verificationResult
            ];
        } catch (Exception $e) {
            error_log('Webhook processing error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get verification status for a user
     * 
     * @param string $userId User ID
     * @return array Verification status data
     */
    public function getVerificationStatus($userId) {
        try {
            // Find the most recent verification for the user
            $verification = $this->kycCollection->findOne(
                ['userId' => new MongoDB\BSON\ObjectId($userId)],
                ['sort' => ['createdAt' => -1]]
            );
            
            if (!$verification) {
                return [
                    'success' => true,
                    'status' => 'NOT_STARTED',
                    'verified' => false
                ];
            }
            
            $status = $verification['status'] ?? 'UNKNOWN';
            $result = $verification['verificationResult'] ?? 'PENDING';
            
            return [
                'success' => true,
                'status' => $status,
                'result' => $result,
                'verified' => ($result === 'APPROVED'),
                'redirectUrl' => $verification['redirectUrl'] ?? null,
                'lastUpdated' => $verification['updatedAt'] ?? null,
                'verificationId' => (string)$verification['_id']
            ];
        } catch (Exception $e) {
            error_log('Error getting verification status: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if a user is verified
     * 
     * @param string $userId User ID
     * @return bool Whether the user is verified
     */
    public function isUserVerified($userId) {
        try {
            $verification = $this->kycCollection->findOne([
                'userId' => new MongoDB\BSON\ObjectId($userId),
                '$or' => [
                    ['verificationResult' => 'APPROVED'],
                    ['status' => 'APPROVED_VERIFIED']
                ]
            ]);
            
            return $verification !== null;
        } catch (Exception $e) {
            error_log('Error checking user verification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Allow admin to override verification status
     * 
     * @param string $userId User ID to update
     * @param string $status New status to set
     * @param string $reason Reason for the override
     * @param string $adminId Admin user ID performing the action
     * @return array Operation result
     */
    public function adminOverrideVerification($userId, $status, $reason, $adminId) {
        try {
            // Validate status
            $allowedStatuses = ['APPROVED', 'REJECTED', 'PENDING'];
            if (!in_array($status, $allowedStatuses)) {
                return [
                    'success' => false,
                    'error' => 'Invalid status. Must be APPROVED, REJECTED, or PENDING'
                ];
            }
            
            // Find the most recent verification
            $verification = $this->kycCollection->findOne(
                ['userId' => new MongoDB\BSON\ObjectId($userId)],
                ['sort' => ['createdAt' => -1]]
            );
            
            // If no verification exists, create a new manual one
            if (!$verification) {
                $manualVerification = [
                    'userId' => new MongoDB\BSON\ObjectId($userId),
                    'status' => 'MANUAL_' . $status,
                    'verificationResult' => $status,
                    'manual' => true,
                    'reason' => $reason,
                    'adminId' => new MongoDB\BSON\ObjectId($adminId),
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ];
                
                $insertResult = $this->kycCollection->insertOne($manualVerification);
                
                return [
                    'success' => true,
                    'message' => 'Manual verification created',
                    'verificationId' => (string)$insertResult['id']
                ];
            }
            
            // Update existing verification
            $updateData = [
                'status' => 'MANUAL_' . $status,
                'verificationResult' => $status,
                'manual' => true,
                'reason' => $reason,
                'adminId' => new MongoDB\BSON\ObjectId($adminId),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $updateResult = $this->kycCollection->updateOne(
                ['_id' => $verification['_id']],
                ['$set' => $updateData]
            );
            
            return [
                'success' => true,
                'message' => 'Verification status overridden',
                'status' => $status
            ];
        } catch (Exception $e) {
            error_log('Admin override error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate a report of KYC verifications
     * 
     * @param array $filters Optional filters for the report
     * @return array Report data
     */
    public function generateKycReport($filters = []) {
        try {
            $query = [];
            
            // Apply filters
            if (isset($filters['startDate'])) {
                $startDate = new DateTime($filters['startDate']);
                $query['createdAt']['$gte'] = new MongoDB\BSON\UTCDateTime($startDate->getTimestamp() * 1000);
            }
            
            if (isset($filters['endDate'])) {
                $endDate = new DateTime($filters['endDate']);
                $endDate->setTime(23, 59, 59);
                $query['createdAt']['$lte'] = new MongoDB\BSON\UTCDateTime($endDate->getTimestamp() * 1000);
            }
            
            if (isset($filters['status'])) {
                $query['verificationResult'] = $filters['status'];
            }
            
            // Get verification records
            $verifications = $this->kycCollection->find($query, [
                'sort' => ['createdAt' => -1]
            ]);
            
            // Aggregate statistics
            $totalCount = count($verifications);
            $statusCounts = [
                'APPROVED' => 0,
                'REJECTED' => 0,
                'PENDING' => 0,
                'ERROR' => 0,
                'EXPIRED' => 0
            ];
            
            $reportData = [];
            
            foreach ($verifications as $verification) {
                $result = $verification['verificationResult'] ?? 'PENDING';
                if (isset($statusCounts[$result])) {
                    $statusCounts[$result]++;
                }
                
                // Get user information
                $userId = $verification['userId'];
                $user = $this->getUserInfo($userId);
                
                $reportEntry = [
                    'verificationId' => (string)$verification['_id'],
                    'userId' => (string)$userId,
                    'userEmail' => $user['email'] ?? 'Unknown',
                    'userName' => $user['displayName'] ?? 'Unknown',
                    'status' => $verification['status'] ?? 'UNKNOWN',
                    'result' => $result,
                    'createdAt' => $verification['createdAt'],
                    'updatedAt' => $verification['updatedAt'],
                    'isManual' => $verification['manual'] ?? false
                ];
                
                $reportData[] = $reportEntry;
            }
            
            // Calculate completion rate
            $completionRate = $totalCount > 0 
                ? round(($statusCounts['APPROVED'] + $statusCounts['REJECTED']) / $totalCount * 100, 2) 
                : 0;
            
            return [
                'success' => true,
                'totalCount' => $totalCount,
                'statusCounts' => $statusCounts,
                'completionRate' => $completionRate,
                'verifications' => $reportData
            ];
        } catch (Exception $e) {
            error_log('KYC report generation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate compliance report aggregating KYC and risk data
     *
     * @param array $filters Optional date range filters
     * @return array Report data
     */
    public function generateComplianceReport($filters = []) {
        try {
            $query = [];

            if (isset($filters['startDate'])) {
                $start = new DateTime($filters['startDate']);
                $query['createdAt']['$gte'] = new MongoDB\BSON\UTCDateTime($start->getTimestamp() * 1000);
            }

            if (isset($filters['endDate'])) {
                $end = new DateTime($filters['endDate']);
                $end->setTime(23, 59, 59);
                $query['createdAt']['$lte'] = new MongoDB\BSON\UTCDateTime($end->getTimestamp() * 1000);
            }

            // Fetch verification records within the window
            $verifications = $this->kycCollection->find($query);

            $statusCounts = [
                'APPROVED' => 0,
                'REJECTED' => 0,
                'PENDING' => 0,
                'ERROR' => 0,
                'EXPIRED' => 0
            ];

            foreach ($verifications as $verification) {
                $result = $verification['verificationResult'] ?? 'PENDING';
                if (isset($statusCounts[$result])) {
                    $statusCounts[$result]++;
                }
            }

            // Aggregate risk levels
            $users = $this->db->getCollection('users');
            $riskCounts = [
                'high' => $users->count(['riskLevel' => 'high']),
                'medium' => $users->count(['riskLevel' => 'medium']),
                'low' => $users->count(['riskLevel' => 'low'])
            ];

            $highRiskUsers = $users->find(
                ['riskLevel' => 'high'],
                ['projection' => ['email' => 1, 'displayName' => 1, 'riskScore' => 1]]
            );

            return [
                'success' => true,
                'statusCounts' => $statusCounts,
                'riskCounts' => $riskCounts,
                'highRiskUsers' => $highRiskUsers
            ];
        } catch (Exception $e) {
            error_log('Compliance report generation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Make an API request to Jumio
     * 
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     */
    private function makeJumioRequest($endpoint, $data) {
        $url = $this->baseUrl . $endpoint;
        $authHeader = base64_encode($this->apiToken . ':' . $this->apiSecret);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: TheGiveHub/1.0',
            'Authorization: Basic ' . $authHeader
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Jumio API request failed: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMessage = isset($responseData['error']) ? $responseData['error'] : 'Unknown error';
            throw new Exception('Jumio API error: ' . $errorMessage);
        }
        
        return $responseData;
    }

    /**
     * Validate webhook signature
     * 
     * @param string $signature Signature from header
     * @param string $payload Raw request body
     * @return bool Whether signature is valid
     */
    private function validateWebhookSignature($signature, $payload) {
        if (empty($this->webhookSecret)) {
            // If no webhook secret is configured, skip validation
            return true;
        }
        
        $calculatedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        return hash_equals($calculatedSignature, $signature);
    }

    /**
     * Get user information
     * 
     * @param MongoDB\BSON\ObjectId $userId User ID
     * @return array User data
     */
    private function getUserInfo($userId) {
        try {
            $userCollection = $this->db->getCollection('users');
            $user = $userCollection->findOne(['_id' => $userId]);
            return $user ?: [];
        } catch (Exception $e) {
            error_log('Error fetching user info: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Send notification to user about verification result
     * 
     * @param MongoDB\BSON\ObjectId $userId User ID
     * @param string $result Verification result
     * @param array $data Additional data about the verification
     * @return void
     */
    private function notifyUser($userId, $result, $data = []) {
        try {
            $user = $this->getUserInfo($userId);
            if (!$user || empty($user['email'])) {
                return;
            }
            
            // If you have a notification service, you can call it here
            // For now, let's just log the notification
            $message = "KYC verification {$result} for user {$user['email']}";
            error_log($message);
            
            // TODO: Implement actual notification logic (email, push notification, etc.)
        } catch (Exception $e) {
            error_log('Error sending notification: ' . $e->getMessage());
        }
    }
}
