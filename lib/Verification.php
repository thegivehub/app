<?php

require_once __DIR__ . '/Collection.php';
require_once __DIR__ . '/Auth.php';

/**
 * Verification Collection
 * Handles verification-related database operations
 */
class Verification extends Collection {
    protected $collectionName = 'verifications';
    protected $auth;
    
    // Workflow states
    const STATE_INITIAL = 'initial';
    const STATE_DOCUMENT_UPLOAD = 'document_upload';
    const STATE_SELFIE_UPLOAD = 'selfie_upload';
    const STATE_REVIEW = 'review';
    const STATE_COMPLETE = 'complete';
    
    // Approval steps
    const STEP_DOCUMENT_VERIFICATION = 'document_verification';
    const STEP_FACE_MATCH = 'face_match';
    const STEP_LIVENESS_CHECK = 'liveness_check';
    const STEP_ADMIN_REVIEW = 'admin_review';
    
    public function __construct() {
        parent::__construct();
        $this->auth = new Auth();
    }
    
    /**
     * Get MongoDB collection object
     * @return MongoDB\Collection
     */
    public function getCollection() {
        return $this->collection;
    }

    /**
     * Count documents matching filter
     * 
     * @param array $filter Query filter
     * @return int Count
     */
    public function count($filter = []) {
        return parent::count($filter);
    }

    /**
     * Get list of verifications with optional filtering
     * 
     * @param array $options Query options
     * @return array List of verifications
     */
    public function list($options = []) {
        try {
            // Build filter criteria based on query parameters
            $filter = [];
            
            // Add status filter if provided
            if (isset($_GET['status']) && !empty($_GET['status'])) {
                $filter['status'] = strtoupper($_GET['status']);
            }
            
            // Add date range filters if provided - compatible with older MongoDB
            $dateFilter = [];
            
            if (isset($_GET['dateFrom']) && !empty($_GET['dateFrom'])) {
                $startDate = new MongoDB\BSON\UTCDateTime(strtotime($_GET['dateFrom']) * 1000);
                $dateFilter['$gte'] = $startDate;
            }
            
            if (isset($_GET['dateTo']) && !empty($_GET['dateTo'])) {
                $endDate = new MongoDB\BSON\UTCDateTime(strtotime($_GET['dateTo'] . ' 23:59:59') * 1000);
                $dateFilter['$lte'] = $endDate;
            }
            
            // Only add the createdAt filter if we have date filters
            if (!empty($dateFilter)) {
                $filter['createdAt'] = $dateFilter;
            }
            
            // Add search filter for name or email if provided
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = $_GET['search'];
                $filter['$or'] = [
                    ['personalInfo.firstName' => ['$regex' => $search, '$options' => 'i']],
                    ['personalInfo.lastName' => ['$regex' => $search, '$options' => 'i']],
                    ['personalInfo.email' => ['$regex' => $search, '$options' => 'i']]
                ];
            }
            
            // Handle pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $skip = ($page - 1) * $limit;
            
            // Set query options - compatibility with older MongoDB drivers
            // Some older drivers might not accept the options in the same format
            $queryOptions = [];
            if ($limit > 0) {
                $queryOptions['limit'] = $limit;
            }
            if ($skip > 0) {
                $queryOptions['skip'] = $skip;
            }
            $queryOptions['sort'] = ['createdAt' => -1]; // Sort by newest first
            
            // Get total count for pagination
            // Use count() method for compatibility with older MongoDB drivers
            $total = $this->collection->count($filter);
            
            // Get the verifications - handle MongoDB driver compatibility
            $cursor = $this->collection->find($filter, $queryOptions);
            $verifications = [];
            // Manually iterate to convert cursor to array for older MongoDB drivers
            foreach ($cursor as $doc) {
                $verifications[] = $doc;
            }
            
            // Format the results
            $formattedVerifications = [];
            foreach ($verifications as $verification) {
                // Get user details from users collection if needed
                $userId = $verification['userId'] ?? null;
                $user = null;
                if ($userId) {
                    $usersCollection = $this->db->getCollection('users');
                    try {
                        $user = $usersCollection->findOne(['_id' => $userId]);
                    } catch (Exception $e) {
                        error_log("Error fetching user for verification: " . $e->getMessage());
                    }
                }
                
                // Format the verification for the response
                $formattedVerification = [
                    '_id' => (string)$verification['_id'],
                    'userName' => $user ? ($user['personalInfo']['firstName'] . ' ' . $user['personalInfo']['lastName']) : 
                                  ($verification['personalInfo']['firstName'] . ' ' . $verification['personalInfo']['lastName']),
                    'userEmail' => $user ? $user['email'] : ($verification['personalInfo']['email'] ?? 'Unknown'),
                    'timestamp' => $this->formatMongoDate($verification['createdAt'] ?? null),
                    'status' => $verification['status'] ?? 'PENDING',
                    'documentUrl' => isset($verification['documents']['primaryId']) ? 
                                    '/api/documents/' . $verification['documents']['primaryId'] . '/file' : 
                                    null,
                    'selfieUrl' => isset($verification['documents']['selfie']) ? 
                                  '/api/documents/' . $verification['documents']['selfie'] . '/file' : 
                                  null,
                ];
                
                $formattedVerifications[] = $formattedVerification;
            }
            
            // Prepare and send response
            $response = [
                'success' => true,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / $limit),
                'verifications' => $formattedVerifications
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } catch (Exception $e) {
            // Log detailed error for debugging
            error_log("Error listing verifications: " . $e->getMessage());
            error_log("Error type: " . get_class($e));
            error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
            error_log("Trace: " . $e->getTraceAsString());
            
            // Return a safe error to the client
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to retrieve verifications. Please check server logs.'
            ]);
            exit;
        }
    }

    /**
     * Get verification details by ID
     * 
     * @param string $id Verification ID
     * @return array|null Verification document or null
     */
    public function details($id) {
        try {
            // Get the verification document
            $verification = parent::read($id);
            
            if (!$verification) {
                header('Content-Type: application/json');
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Verification not found'
                ]);
                exit;
            }
            
            // Get user details if available
            $userId = $verification['userId'] ?? null;
            $user = null;
            if ($userId) {
                $usersCollection = $this->db->getCollection('users');
                try {
                    $user = $usersCollection->findOne(['_id' => $userId]);
                } catch (Exception $e) {
                    error_log("Error fetching user for verification details: " . $e->getMessage());
                }
            }
            
            // Format the response
            $response = [
                'success' => true,
                '_id' => (string)$verification['_id'],
                'userName' => $user ? ($user['personalInfo']['firstName'] . ' ' . $user['personalInfo']['lastName']) : 
                              ($verification['personalInfo']['firstName'] . ' ' . $verification['personalInfo']['lastName']),
                'userEmail' => $user ? $user['email'] : ($verification['personalInfo']['email'] ?? 'Unknown'),
                'timestamp' => $this->formatMongoDate($verification['createdAt'] ?? null),
                'status' => $verification['status'] ?? 'PENDING',
                'documentImage' => isset($verification['documents']['primaryId']) ? 
                                 '/api/documents/' . $verification['documents']['primaryId'] . '/file' : 
                                 null,
                'selfieImage' => isset($verification['documents']['selfie']) ? 
                               '/api/documents/' . $verification['documents']['selfie'] . '/file' : 
                               null,
                'personalInfo' => $verification['personalInfo'] ?? [],
                'documentInfo' => [
                    'documentType' => $verification['documentType'] ?? 'Unknown',
                    'documentNumber' => $verification['documentNumber'] ?? 'Unknown',
                    'documentExpiry' => $this->formatMongoDate($verification['documentExpiry'] ?? null, 'Y-m-d')
                ],
                'verificationResults' => $verification['verificationResults'] ?? [],
                'reviewNotes' => $verification['reviewNotes'] ?? '',
                'reviewedAt' => isset($verification['reviewedAt']) ? 
                              $this->formatMongoDate($verification['reviewedAt']) : 
                              null,
                'reviewedBy' => $verification['reviewedBy'] ?? null
            ];
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } catch (Exception $e) {
            error_log("Error fetching verification details: " . $e->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to retrieve verification details: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Review a verification
     * @param string $id Verification ID
     * @param array $data Review data (decision, notes)
     * @return array Result of review operation
     */
    /**
     * Get the current workflow state for a verification
     */
    public function getWorkflowState($verificationId) {
        try {
            $verification = $this->read($verificationId);
            if (!$verification) {
                throw new Exception('Verification not found');
            }
            
            return [
                'success' => true,
                'state' => $verification['workflow']['state'] ?? self::STATE_INITIAL,
                'currentStep' => $verification['workflow']['currentStep'] ?? null,
                'completedSteps' => $verification['workflow']['completedSteps'] ?? [],
                'nextSteps' => $this->getNextSteps($verification)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Determine next possible steps based on current state
     */
    private function getNextSteps($verification) {
        $state = $verification['workflow']['state'] ?? self::STATE_INITIAL;
        $completed = $verification['workflow']['completedSteps'] ?? [];
        
        $nextSteps = [];
        
        switch ($state) {
            case self::STATE_INITIAL:
                $nextSteps[] = self::STEP_DOCUMENT_VERIFICATION;
                break;
                
            case self::STATE_DOCUMENT_UPLOAD:
                if (in_array(self::STEP_DOCUMENT_VERIFICATION, $completed)) {
                    $nextSteps[] = self::STEP_FACE_MATCH;
                    $nextSteps[] = self::STEP_LIVENESS_CHECK;
                }
                break;
                
            case self::STATE_SELFIE_UPLOAD:
                if (in_array(self::STEP_FACE_MATCH, $completed) && 
                    in_array(self::STEP_LIVENESS_CHECK, $completed)) {
                    $nextSteps[] = self::STEP_ADMIN_REVIEW;
                }
                break;
                
            case self::STATE_REVIEW:
                // No next steps - waiting for admin action
                break;
                
            case self::STATE_COMPLETE:
                // Verification complete
                break;
        }
        
        return $nextSteps;
    }
    
    /**
     * Advance the workflow to the next state
     */
    public function advanceWorkflow($verificationId, $step) {
        try {
            $verification = $this->read($verificationId);
            if (!$verification) {
                throw new Exception('Verification not found');
            }
            
            $currentState = $verification['workflow']['state'] ?? self::STATE_INITIAL;
            $completedSteps = $verification['workflow']['completedSteps'] ?? [];
            
            // Validate the step can be taken
            $nextSteps = $this->getNextSteps($verification);
            if (!in_array($step, $nextSteps)) {
                throw new Exception('Invalid workflow step');
            }
            
            // Mark step as completed
            $completedSteps[] = $step;
            
            // Determine new state
            $newState = $currentState;
            switch ($step) {
                case self::STEP_DOCUMENT_VERIFICATION:
                    $newState = self::STATE_DOCUMENT_UPLOAD;
                    break;
                    
                case self::STEP_FACE_MATCH:
                case self::STEP_LIVENESS_CHECK:
                    if (in_array(self::STEP_FACE_MATCH, $completedSteps) && 
                        in_array(self::STEP_LIVENESS_CHECK, $completedSteps)) {
                        $newState = self::STATE_REVIEW;
                    } else {
                        $newState = self::STATE_SELFIE_UPLOAD;
                    }
                    break;
                    
                case self::STEP_ADMIN_REVIEW:
                    $newState = self::STATE_COMPLETE;
                    break;
            }
            
            // Update verification
            $update = [
                'workflow' => [
                    'state' => $newState,
                    'currentStep' => $step,
                    'completedSteps' => $completedSteps,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]
            ];
            
            $result = $this->update($verificationId, ['$set' => $update]);
            
            return [
                'success' => true,
                'newState' => $newState,
                'completedSteps' => $completedSteps
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function review($id, $data) {
        try {
            // Track notification state
            $notifications = [];
            
            // Check input data format from admin interface
            if (!isset($data['decision']) && !isset($data['notes'])) {
                // Try different format from admin-campaign-review.js
                if (isset($data['action']) && in_array($data['action'], ['APPROVED', 'REJECTED'])) {
                    $decision = $data['action'];
                    $notes = $data['notes'] ?? '';
                } else {
                    throw new Exception('Invalid review data: missing decision and notes');
                }
            } else {
                // Format from verification-admin.html
                $decision = strtoupper($data['decision']);
                $notes = $data['notes'] ?? '';
                
                // Validate decision
                if (!in_array($decision, ['APPROVED', 'REJECTED'])) {
                    throw new Exception('Invalid decision: must be APPROVED or REJECTED');
                }
            }
            
            // Get current admin user ID
            $adminId = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
            if (!$adminId) {
                // Try to use JWT token for admin validation
                try {
                    if (class_exists('AdminAuth')) {
                        $adminAuth = new AdminAuth();
                        $isAdmin = $adminAuth->verifyAdminToken();
                        if ($isAdmin) {
                            $adminId = $adminAuth->getAdminId();
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error verifying admin token: " . $e->getMessage());
                }
                
                if (!$adminId) {
                    throw new Exception('Administrator authentication required');
                }
            }
            
            // Prepare update data
            $update = [
                'status' => $decision,
                'reviewedAt' => new MongoDB\BSON\UTCDateTime(),
                'reviewedBy' => $adminId,
                'reviewNotes' => $notes
            ];
            
            // Update the verification record
            $filter = ['_id' => new MongoDB\BSON\ObjectId($id)];
            $updateDoc = ['$set' => $update];
            
            // Use the collection's updateOne method directly
            $updateResult = $this->collection->updateOne($filter, $updateDoc);
            
            // Check if we got a MongoDB result object or an array
            if (is_object($updateResult) && method_exists($updateResult, 'getMatchedCount')) {
                $result = [
                    'success' => $updateResult->getModifiedCount() > 0 || $updateResult->getMatchedCount() > 0,
                    'matchedCount' => $updateResult->getMatchedCount(),
                    'modifiedCount' => $updateResult->getModifiedCount()
                ];
            } else {
                // It's probably already an array with success/error info
                $result = is_array($updateResult) ? $updateResult : ['success' => false, 'error' => 'Unknown update error'];
            }
            
            if (!isset($result['success']) || !$result['success']) {
                throw new Exception('Failed to update verification: ' . json_encode($result));
            }
            
            // Add audit log
            try {
                $auditLog = [
                    'action' => 'VERIFICATION_REVIEW',
                    'verificationId' => new MongoDB\BSON\ObjectId($id),
                    'adminId' => $adminId,
                    'status' => $decision,
                    'notes' => $notes,
                    'timestamp' => new MongoDB\BSON\UTCDateTime()
                ];
                
                $db = $this->getDb();
                $db->audit_logs->insertOne($auditLog);
            } catch (Exception $e) {
                // Log but don't fail if audit log fails
                error_log("Failed to create audit log: " . $e->getMessage());
            }
            
            // Add notification for the admin
            $notifications[] = [
                'type' => 'admin',
                'message' => "Verification {$decision}: " . ($notes ? substr($notes, 0, 100) : 'No notes'),
                'userId' => $adminId,
                'verificationId' => $id,
                'timestamp' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Add notification for the user if approved/rejected
            if (in_array($decision, ['APPROVED', 'REJECTED'])) {
                $notifications[] = [
                    'type' => 'user',
                    'message' => "Your verification was {$decision}" . 
                                ($notes ? ": " . substr($notes, 0, 100) : ''),
                    'userId' => $verification['userId'],
                    'verificationId' => $id,
                    'timestamp' => new MongoDB\BSON\UTCDateTime()
                ];
            }
            
            // Save notifications if any
            if (!empty($notifications)) {
                $this->db->notifications->insertMany($notifications);
            }
            
            // Complete the workflow if approved/rejected
            if (in_array($decision, ['APPROVED', 'REJECTED'])) {
                $this->advanceWorkflow($id, self::STEP_ADMIN_REVIEW);
            }
            
            return [
                'success' => true,
                'message' => 'Verification review processed successfully',
                'status' => $decision,
                'notifications' => count($notifications),
                'workflowCompleted' => $decision === 'APPROVED'
            ];
        } catch (Exception $e) {
            error_log("Error reviewing verification: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get verification statistics
     * 
     * @return array Statistics by status
     */
    public function stats() {
        try {
            $pipeline = [
                [
                    '$group' => [
                        '_id' => '$status',
                        'count' => ['$sum' => 1]
                    ]
                ]
            ];

            $results = $this->aggregate($pipeline);
            
            // Initialize default stats
            $stats = [
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0
            ];

            // Update stats with actual counts
            foreach ($results as $result) {
                $status = strtolower($result['_id']);
                if (isset($stats[$status])) {
                    $stats[$status] = (int)$result['count'];
                }
            }

            return $stats;
        } catch (Exception $e) {
            error_log("Error getting verification stats: " . $e->getMessage());
            return [
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0
            ];
        }
    }

    /**
     * Create a new verification record
     * @param array $data Personal information data
     * @return array Response with creation status
     */
    public function create($formData) {
        try {
            // Log the incoming data
            error_log("Creating verification with form data: " . json_encode($formData));
            
            // Check authentication
            try {
                $userId = $this->auth->getUserIdFromToken();
                if (!$userId) {
                    throw new Exception("Authentication required");
                }
            } catch (Exception $authError) {
                error_log("Authentication error in verification create: " . $authError->getMessage());
                return [
                    'success' => false,
                    'error' => 'Authentication failed: ' . $authError->getMessage()
                ];
            }

            // Create verification document matching the schema you provided
            $verification = [
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'status' => 'INITIATED',
                'workflow' => [
                    'state' => self::STATE_INITIAL,
                    'currentStep' => null,
                    'completedSteps' => [],
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ],
                
                // Prepare personalInfo as a sub-object
                'personalInfo' => [
                    'firstName' => $formData['firstName'] ?? 'Unknown',
                    'lastName' => $formData['lastName'] ?? 'Unknown',
                    'dateOfBirth' => new MongoDB\BSON\UTCDateTime(strtotime($formData['dateOfBirth'] ?? '1970-01-01') * 1000),
                    'address' => $formData['address'] ?? 'Unknown',
                    'city' => $formData['city'] ?? 'Unknown',
                    'state' => $formData['state'] ?? 'Unknown',
                    'postalCode' => $formData['postalCode'] ?? 'Unknown',
                    'country' => $formData['country'] ?? 'Unknown'
                ],
                
                // Some fields need to be at top level as well for backwards compatibility
                'firstName' => $formData['firstName'] ?? 'Unknown',
                'lastName' => $formData['lastName'] ?? 'Unknown',
                'address' => $formData['address'] ?? 'Unknown',
                'city' => $formData['city'] ?? 'Unknown',
                'state' => $formData['state'] ?? 'Unknown',
                'postalCode' => $formData['postalCode'] ?? 'Unknown',
                'country' => $formData['country'] ?? 'Unknown',
                'email' => $formData['email'] ?? '',
                
                // Document info fields
                'documentType' => 'drivers_license', // Default value
                'documentNumber' => 'Unknown', // Default value
                'documentExpiry' => new MongoDB\BSON\UTCDateTime(strtotime('+5 years')),
                'documentImageUrl' => null,
                'selfieImageUrl' => null,
                
                // Document references sub-object
                'documents' => [
                    'primaryId' => null,
                    'selfie' => null
                ],
                
                // Verification results sub-object
                'verificationResults' => [
                    'similarity' => null,
                    'confidence' => null,
                    'liveness' => null
                ],
                
                // Metadata sub-object matching schema
                'metadata' => [
                    'attempts' => 0,
                    'documentQualityScore' => null,
                    'faceDetectionScore' => null,
                    'livenessScore' => null,
                    'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                    'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ],
                
                // Add similarity score at top level
                'similarityScore' => null,
                
                // Add other required fields
                'type' => 'ID_VERIFICATION',
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Log the verification object we're going to insert
            error_log("Inserting verification document: " . json_encode($verification));

            // Use insertOne directly to avoid recursive calls
            try {
                // Instead of relying on collection methods, use direct MongoDB calls
                $mongoDb = $this->db->db;
                $verificationCollection = $mongoDb->verifications;
                
                // Log what we're about to do
                error_log("About to insert document into MongoDB collection 'verifications'");
                
                // Insert the document
                $insertResult = $verificationCollection->insertOne($verification);
                error_log("MongoDB insert completed");
                
                // Get the ID of the inserted document
                $insertedId = $insertResult->getInsertedId();
                $insertedIdStr = (string)$insertedId;
                
                error_log("Document inserted with ID: " . $insertedIdStr);
                
                return [
                    'success' => true,
                    'verificationId' => $insertedIdStr,
                    'message' => 'Verification process initiated'
                ];
            } catch (Exception $insertError) {
                // Detailed error logging
                error_log("MongoDB insert error: " . $insertError->getMessage());
                error_log("Error type: " . get_class($insertError));
                error_log("Error trace: " . $insertError->getTraceAsString());
                
                // Create a safe fallback response
                return [
                    'success' => false,
                    'error' => 'Database error creating verification',
                    'details' => $insertError->getMessage()
                ];
            }
        } catch (Exception $e) {
            error_log("Error creating verification: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update document reference in verification record
     * @param string $verificationId Verification ID
     * @param string $documentId Document ID
     * @param string $type Document type (primaryId or selfie)
     * @return array Update result
     */
    public function updateDocumentReference($verificationId, $documentId, $type) {
        try {
            if (!in_array($type, ['primaryId', 'selfie'])) {
                throw new Exception("Invalid document type: $type");
            }
            
            error_log("Updating verification $verificationId with document $documentId of type $type");
            
            // Make sure documents object exists (create if it doesn't)
            try {
                $filter = ['_id' => new MongoDB\BSON\ObjectId($verificationId)];
                $update = ['$set' => ['documents' => (object)[]]];
                $options = ['upsert' => true];
                
                // Use the collection's updateOne method directly
                $updateResult = $this->collection->updateOne($filter, $update, $options);
                
                // Check if updateResult is a MongoDB result object or an array
                if (is_object($updateResult) && method_exists($updateResult, 'getMatchedCount')) {
                    error_log("Documents object initialization result: " . json_encode([
                        'matchedCount' => $updateResult->getMatchedCount(), 
                        'modifiedCount' => $updateResult->getModifiedCount()
                    ]));
                } else {
                    // It's probably an array with error information
                    error_log("Documents object initialization result: " . json_encode($updateResult));
                }
            } catch (Exception $e) {
                error_log("Error initializing documents object: " . $e->getMessage());
                // Continue anyway as the next update might succeed
            }
            
            // Set document reference
            $update = [
                "documents.$type" => $documentId,
                "updatedAt" => new MongoDB\BSON\UTCDateTime()
            ];

            try {
                $filter = ['_id' => new MongoDB\BSON\ObjectId($verificationId)];
                $updateDoc = ['$set' => $update];
                
                // Use the collection's updateOne method directly
                $updateResult = $this->collection->updateOne($filter, $updateDoc);
                
                // Check if we got a MongoDB result object or an array
                if (is_object($updateResult) && method_exists($updateResult, 'getMatchedCount')) {
                    $result = [
                        'success' => $updateResult->getModifiedCount() > 0 || $updateResult->getMatchedCount() > 0,
                        'matchedCount' => $updateResult->getMatchedCount(),
                        'modifiedCount' => $updateResult->getModifiedCount()
                    ];
                } else {
                    // It's probably already an array with success/error info
                    $result = is_array($updateResult) ? $updateResult : ['success' => false, 'error' => 'Unknown update error'];
                }
                
                error_log("Document reference update result: " . json_encode($result));
                
                if (!isset($result['success']) || !$result['success']) {
                    throw new Exception("Failed to update document reference: " . json_encode($result));
                }
                
                // Double-check that the reference was properly set
                $verification = $this->read($verificationId);
                if (!$verification || !isset($verification['documents']) || !isset($verification['documents'][$type])) {
                    error_log("Document reference not set properly! Verification data: " . json_encode($verification));
                } else {
                    error_log("Document reference successfully set: " . $verification['documents'][$type]);
                }
                
                return [
                    'success' => true,
                    'message' => "Document reference updated"
                ];
            } catch (Exception $e) {
                error_log("Error in document reference update: " . $e->getMessage());
                throw $e;
            }
        } catch (Exception $e) {
            error_log("Error updating document reference: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update multiple document references in verification record
     * Used when we want to update both primaryId and selfie in a single operation
     * or preserve one reference while updating the other
     * 
     * @param string $verificationId Verification ID
     * @param array $documents Associative array with 'primaryId' and/or 'selfie' keys
     * @return array Update result
     */
    public function updateMultipleDocumentReferences($verificationId, $documents) {
        try {
            error_log("Updating verification $verificationId with multiple documents: " . json_encode($documents));
            
            // Validate document structure
            if (!is_array($documents)) {
                throw new Exception("Documents parameter must be an array");
            }
            
            // Validate document types
            foreach (array_keys($documents) as $type) {
                if (!in_array($type, ['primaryId', 'selfie'])) {
                    throw new Exception("Invalid document type: $type");
                }
            }
            
            // Make sure documents object exists (create if it doesn't)
            try {
                $filter = ['_id' => new MongoDB\BSON\ObjectId($verificationId)];
                $update = ['$set' => ['documents' => (object)[]]];
                $options = ['upsert' => true];
                
                // Use the collection's updateOne method directly
                $updateResult = $this->collection->updateOne($filter, $update, $options);
                
                // Check if updateResult is a MongoDB result object or an array
                if (is_object($updateResult) && method_exists($updateResult, 'getMatchedCount')) {
                    error_log("Documents object initialization result: " . json_encode([
                        'matchedCount' => $updateResult->getMatchedCount(), 
                        'modifiedCount' => $updateResult->getModifiedCount()
                    ]));
                } else {
                    // It's probably an array with error information
                    error_log("Documents object initialization result: " . json_encode($updateResult));
                }
            } catch (Exception $e) {
                error_log("Error initializing documents object: " . $e->getMessage());
                // Continue anyway as the next update might succeed
            }
            
            // Prepare the update fields
            $update = [
                "updatedAt" => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Add document references to the update
            foreach ($documents as $type => $documentId) {
                $update["documents.$type"] = $documentId;
            }

            try {
                $filter = ['_id' => new MongoDB\BSON\ObjectId($verificationId)];
                $updateDoc = ['$set' => $update];
                
                // Use the collection's updateOne method directly
                $updateResult = $this->collection->updateOne($filter, $updateDoc);
                
                // Check if we got a MongoDB result object or an array
                if (is_object($updateResult) && method_exists($updateResult, 'getMatchedCount')) {
                    $result = [
                        'success' => $updateResult->getModifiedCount() > 0 || $updateResult->getMatchedCount() > 0,
                        'matchedCount' => $updateResult->getMatchedCount(),
                        'modifiedCount' => $updateResult->getModifiedCount()
                    ];
                } else {
                    // It's probably already an array with success/error info
                    $result = is_array($updateResult) ? $updateResult : ['success' => false, 'error' => 'Unknown update error'];
                }
                
                error_log("Multiple document references update result: " . json_encode($result));
                
                if (!isset($result['success']) || !$result['success']) {
                    throw new Exception("Failed to update document references: " . json_encode($result));
                }
                
                // Double-check that the references were properly set
                $verification = $this->read($verificationId);
                if (!$verification || !isset($verification['documents'])) {
                    error_log("Document references not set properly! Verification data: " . json_encode($verification));
                } else {
                    // Log each reference that was set
                    foreach ($documents as $type => $documentId) {
                        if (isset($verification['documents'][$type])) {
                            error_log("Document reference for $type successfully set: " . $verification['documents'][$type]);
                        } else {
                            error_log("Document reference for $type was not set properly!");
                        }
                    }
                }
                
                return [
                    'success' => true,
                    'message' => "Document references updated"
                ];
            } catch (Exception $e) {
                error_log("Error in document references update: " . $e->getMessage());
                throw $e;
            }
        } catch (Exception $e) {
            error_log("Error updating document references: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if user has a verified identity
     * 
     * @return array Verification status
     */
    public function checkUserVerification() {
        try {
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception("Authentication required");
            }
            
            $verification = $this->collection->findOne([
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'status' => 'APPROVED'
            ]);
            
            return [
                'success' => true,
                'verified' => $verification !== null,
                'verification' => $verification
            ];
        } catch (Exception $e) {
            error_log("Error checking verification status: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get the status of a verification by ID
     * 
     * @param string $id Verification ID
     * @return array Verification status
     */
    public function getStatus($id) {
        try {
            $verification = $this->read($id);
            
            if (!$verification) {
                throw new Exception("Verification not found");
            }
            
            return [
                'success' => true,
                'status' => $verification['status'],
                'documents' => $verification['documents'] ?? null,
                'verificationResults' => $verification['verificationResults'] ?? null
            ];
        } catch (Exception $e) {
            error_log("Error getting verification status: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get the user's verification documents
     */
    public function getUserDocuments() {
        try {
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception("Authentication required");
            }
            
            $verification = $this->collection->findOne([
                'userId' => new MongoDB\BSON\ObjectId($userId)
            ]);
            
            if (!$verification) {
                return [
                    'success' => true,
                    'documents' => []
                ];
            }
            
            return [
                'success' => true,
                'documents' => $verification['documents'] ?? []
            ];
        } catch (Exception $e) {
            error_log("Error getting user documents: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get user's verification status and determine which step they should start from
     * 
     * @return array Status information including the step to start from
     */
    public function getUserVerificationStatus() {
        try {
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception("Authentication required");
            }
            
            // Find the most recent verification for this user
            $verification = $this->collection->findOne(
                ['userId' => new MongoDB\BSON\ObjectId($userId)],
                ['sort' => ['createdAt' => -1]] // Get the most recent first
            );
            
            if (!$verification) {
                // No verification found, start from step 1
                return [
                    'success' => true,
                    'hasVerification' => false,
                    'startAtStep' => 1,
                    'message' => 'No verification found, please start the process'
                ];
            }
            
            // Check verification status
            if ($verification['status'] === 'APPROVED') {
                return [
                    'success' => true,
                    'hasVerification' => true,
                    'verificationId' => (string)$verification['_id'],
                    'status' => 'APPROVED',
                    'startAtStep' => 0, // Already completed
                    'message' => 'Your identity has been verified successfully'
                ];
            }
            
            if ($verification['status'] === 'REJECTED') {
                return [
                    'success' => true,
                    'hasVerification' => true,
                    'verificationId' => (string)$verification['_id'],
                    'status' => 'REJECTED',
                    'startAtStep' => 1, // Start again
                    'message' => 'Your previous verification was rejected. Please try again.',
                    'rejectionReason' => $verification['reviewNotes'] ?? 'No reason provided'
                ];
            }
            
            // For pending verification, determine which step to continue from
            $startAtStep = 1;
            
            // Check if they've completed personal info (step 1)
            if (isset($verification['personalInfo']) && 
                $this->isPersonalInfoComplete($verification['personalInfo'])) {
                $startAtStep = 2;
            }
            
            // Check if they've uploaded ID (step 2)
            if (isset($verification['documents']['primaryId']) && 
                $verification['documents']['primaryId']) {
                $startAtStep = 3;
            }
            
            // Check if they've uploaded selfie (step 3)
            if (isset($verification['documents']['selfie']) && 
                $verification['documents']['selfie']) {
                $startAtStep = 4;
            }
            
            return [
                'success' => true,
                'hasVerification' => true,
                'verificationId' => (string)$verification['_id'],
                'status' => $verification['status'],
                'startAtStep' => $startAtStep,
                'message' => 'Continue from step ' . $startAtStep,
                'personalInfo' => $verification['personalInfo'] ?? null
            ];
        } catch (Exception $e) {
            error_log("Error getting user verification status: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'startAtStep' => 1 // Default to step 1 if error
            ];
        }
    }
    
    /**
     * Helper function to check if personal info is complete
     */
    private function isPersonalInfoComplete($personalInfo) {
        $requiredFields = [
            'firstName', 'lastName', 'dateOfBirth', 
            'address', 'city', 'state', 'postalCode', 'country'
        ];
        
        foreach ($requiredFields as $field) {
            if (!isset($personalInfo[$field]) || empty($personalInfo[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Safely format a MongoDB date field
     * Handles various date formats that might be stored in the database
     * 
     * @param mixed $dateField The date field from MongoDB
     * @param string $format Date format (default: ISO 8601/RFC 3339)
     * @param string $defaultIfEmpty Value to return if date is empty
     * @return string Formatted date string
     */
    private function formatMongoDate($dateField, $format = 'c', $defaultIfEmpty = null) {
        // Handle empty date field
        if (empty($dateField)) {
            return $defaultIfEmpty !== null ? $defaultIfEmpty : date($format);
        }
        
        try {
            // Handle MongoDB\BSON\UTCDateTime objects
            if (is_object($dateField) && method_exists($dateField, 'toDateTime')) {
                return $dateField->toDateTime()->format($format);
            }
            
            // Try to get a timestamp that we can format
            $timestamp = null;
            
            // Handle MongoDB date as array with $date field (older driver format)
            if (is_array($dateField) && isset($dateField['$date'])) {
                // $date might be a timestamp, a string, or another nested object
                if (is_numeric($dateField['$date'])) {
                    // Timestamp in milliseconds
                    $timestamp = $dateField['$date'] / 1000;
                } elseif (is_string($dateField['$date'])) {
                    // ISO string
                    $timestamp = strtotime($dateField['$date']);
                } elseif (is_array($dateField['$date']) && isset($dateField['$date']['$numberLong'])) {
                    // MongoDB extended JSON format
                    $timestamp = intval($dateField['$date']['$numberLong']) / 1000;
                }
            }
            // Handle timestamp integers
            else if (is_numeric($dateField)) {
                // Ensure it's a Unix timestamp (seconds, not milliseconds)
                $timestamp = $dateField;
                if ($timestamp > 10000000000) { // Greater than year 2286, likely milliseconds
                    $timestamp = $timestamp / 1000;
                }
            }
            // Handle date strings
            else if (is_string($dateField)) {
                $timestamp = strtotime($dateField);
            }
            
            // Format timestamp if we found a valid one
            if ($timestamp !== null && $timestamp !== false) {
                return date($format, $timestamp);
            }
            
            // If we get here, we couldn't parse the date
            error_log("Unparseable date format: " . json_encode($dateField));
            return $defaultIfEmpty !== null ? $defaultIfEmpty : date($format);
        } catch (Exception $e) {
            error_log("Error formatting date: " . $e->getMessage());
            error_log("Date field: " . json_encode($dateField));
            return $defaultIfEmpty !== null ? $defaultIfEmpty : date($format);
        }
    }
    
    /**
     * Reset verification status for a user
     * This allows starting a new verification process
     * 
     * @param bool $force Whether to force reset even if verified
     * @return array Result of the operation
     */
    public function resetStatus($force = false) {
        try {
            // Get the current user
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception("Authentication required");
            }
            
            // Find the user's most recent verification
            $verification = $this->collection->findOne(
                ['userId' => new MongoDB\BSON\ObjectId($userId)],
                ['sort' => ['createdAt' => -1]]
            );
            
            // If no verification exists or force is true, return success
            if (!$verification || $force) {
                return [
                    'success' => true,
                    'message' => 'No verification to reset or force reset applied',
                    'status' => 'RESET'
                ];
            }
            
            // Check if verification is completed and we're not forcing
            if ($verification['status'] === 'APPROVED' && !$force) {
                return [
                    'success' => false,
                    'error' => 'Verification is already approved. Use force=true to reset anyway.'
                ];
            }
            
            // Create an audit entry
            $auditLog = [
                'action' => 'VERIFICATION_RESET',
                'verificationId' => $verification['_id'],
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'originalStatus' => $verification['status'],
                'timestamp' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Add audit entry
            $db = $this->getDb();
            $db->audit_logs->insertOne($auditLog);
            
            return [
                'success' => true,
                'message' => 'Verification status reset successfully',
                'status' => 'RESET'
            ];
        } catch (Exception $e) {
            error_log("Error resetting verification status: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update personal information for an existing verification record
     * 
     * @param string $verificationId Verification ID
     * @param array $data Updated personal information
     * @return array Update status
     */
    public function updatePersonalInfo($verificationId, $data) {
        try {
            // Verify user has permission to update this record
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception("Authentication required");
            }
            
            // Get the existing verification
            $verification = $this->read($verificationId);
            if (!$verification) {
                throw new Exception("Verification not found");
            }
            
            // Verify this verification belongs to the user
            if ((string)$verification['userId'] !== $userId) {
                throw new Exception("You don't have permission to update this verification");
            }
            
            // Prepare personal info update
            $personalInfo = [
                'firstName' => $data['firstName'] ?? $verification['personalInfo']['firstName'] ?? null,
                'lastName' => $data['lastName'] ?? $verification['personalInfo']['lastName'] ?? null,
                'dateOfBirth' => isset($data['dateOfBirth']) ? 
                    new MongoDB\BSON\UTCDateTime(strtotime($data['dateOfBirth']) * 1000) : 
                    ($verification['personalInfo']['dateOfBirth'] ?? null),
                'address' => $data['address'] ?? $verification['personalInfo']['address'] ?? null,
                'city' => $data['city'] ?? $verification['personalInfo']['city'] ?? null,
                'state' => $data['state'] ?? $verification['personalInfo']['state'] ?? null,
                'postalCode' => $data['postalCode'] ?? $verification['personalInfo']['postalCode'] ?? null,
                'country' => $data['country'] ?? $verification['personalInfo']['country'] ?? null
            ];
            
            // Enhanced validation with more fields and stricter checks
            $requiredFields = [
                'firstName' => ['type' => 'string', 'min' => 2, 'max' => 50],
                'lastName' => ['type' => 'string', 'min' => 2, 'max' => 50],
                'dateOfBirth' => ['type' => 'date'],
                'address' => ['type' => 'string', 'min' => 5, 'max' => 100],
                'city' => ['type' => 'string', 'min' => 2, 'max' => 50],
                'state' => ['type' => 'string', 'min' => 2, 'max' => 50],
                'postalCode' => ['type' => 'string', 'pattern' => '/^[0-9a-zA-Z\- ]+$/'],
                'country' => ['type' => 'string', 'min' => 2, 'max' => 2]
            ];
            
            foreach ($requiredFields as $field => $rules) {
                if (empty($personalInfo[$field])) {
                    throw new Exception("Required field missing: $field");
                }
                
                // Type checking
                if ($rules['type'] === 'string' && !is_string($personalInfo[$field])) {
                    throw new Exception("$field must be a string");
                }
                
                // Length validation
                if (isset($rules['min']) && strlen($personalInfo[$field]) < $rules['min']) {
                    throw new Exception("$field must be at least {$rules['min']} characters");
                }
                
                if (isset($rules['max']) && strlen($personalInfo[$field]) > $rules['max']) {
                    throw new Exception("$field must be no more than {$rules['max']} characters");
                }
                
                // Pattern matching
                if (isset($rules['pattern']) && !preg_match($rules['pattern'], $personalInfo[$field])) {
                    throw new Exception("$field format is invalid");
                }
                
                // Date validation
                if ($rules['type'] === 'date' && !strtotime($personalInfo[$field])) {
                    throw new Exception("$field must be a valid date");
                }
            }
            
            // Update the record
            $updateData = [
                'personalInfo' => $personalInfo,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];
            
            try {
                $filter = ['_id' => new MongoDB\BSON\ObjectId($verificationId)];
                $updateDoc = ['$set' => $updateData];
                
                // Use the collection's updateOne method directly
                $updateResult = $this->collection->updateOne($filter, $updateDoc);
                
                // Check if we got a MongoDB result object or an array
                if (is_object($updateResult) && method_exists($updateResult, 'getMatchedCount')) {
                    $result = [
                        'success' => $updateResult->getModifiedCount() > 0 || $updateResult->getMatchedCount() > 0,
                        'matchedCount' => $updateResult->getMatchedCount(),
                        'modifiedCount' => $updateResult->getModifiedCount()
                    ];
                } else {
                    // It's probably already an array with success/error info
                    $result = is_array($updateResult) ? $updateResult : ['success' => false, 'error' => 'Unknown update error'];
                }
                
                if (!$result['success']) {
                    throw new Exception("Failed to update verification: " . json_encode($result));
                }
            } catch (Exception $e) {
                error_log("Error updating verification personal info: " . $e->getMessage());
                throw new Exception("Failed to update verification: " . $e->getMessage());
            }
            
            return [
                'success' => true,
                'message' => 'Verification information updated',
                'verificationId' => $verificationId
            ];
        } catch (Exception $e) {
            error_log("Error updating verification: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
