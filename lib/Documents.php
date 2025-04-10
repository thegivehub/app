<?php
require_once __DIR__ . '/Collection.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/DocumentUploader.php';

// Explicitly require MongoDB classes to avoid autoloading issues
if (!class_exists('MongoDB\BSON\ObjectId')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Documents Collection Class
 * Handles identity verification document management
 */
class Documents extends Collection {
    protected $collectionName = 'documents';
    protected $auth;
    protected $uploader;
    protected $uploadDir;

    public function __construct() {
        // Call parent constructor to set up base functionality
        parent::__construct();
        
        // Parent constructor would set the collection - let's grab a reference
        // to the same collection by name (should be 'documents')
        $db = new Database();
        $collection = strtolower(get_class($this));
        if (!preg_match("/s$/", $collection)) {
            $collection .= 's';
        }
        
        error_log("Collection name from class: " . $collection);
        error_log("Explicitly using collection name: documents");
        
        // Use the explicit collection name to ensure consistency
        $this->collection = $db->getCollection('documents');
        
        $this->auth = new Auth();
        
        // Now initialize uploader with the collection
        $this->uploader = new DocumentUploader($this->auth, $this->collection);
        $this->uploadDir = __DIR__ . '/../uploads/documents/';
        
        // Ensure upload directory exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Upload a document or selfie
     * @param array $file File data from $_FILES
     * @param string $type Document type ("document" or "selfie")
     * @return array Response with upload status
     */
    public function upload($file = null, $type = "document") {
        try {
            if (!$this->auth->isAuthenticated()) {
                throw new Exception("Authentication required");
            }

            // Determine upload type and get document subtype from POST data
            $documentType = isset($_POST['documentType']) ? $_POST['documentType'] : 'id_card';
            
            if (isset($_FILES['document'])) {
                $type = "document";
                $uploadedFile = $_FILES['document'];
            } else if (isset($_FILES['selfie'])) {
                $type = "selfie";
                $uploadedFile = $_FILES['selfie'];
            } else {
                throw new Exception("No file uploaded");
            }
            
            if (!isset($uploadedFile) || !$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("No file uploaded or upload failed");
            }

            // Debug logging to understand what's being received
            error_log("Documents upload - Type: " . $type);
            error_log("File details: " . json_encode([
                'name' => $uploadedFile['name'],
                'type' => $uploadedFile['type'],
                'size' => $uploadedFile['size']
            ]));
            error_log("POST data: " . json_encode($_POST));

            if ($type === "document") {
                // Pass the specific document type (id_card, passport, etc.)
                error_log("Uploading document of type: " . $documentType);
                return $this->uploader->uploadDocument($uploadedFile, $documentType);
            } else if ($type === "selfie") {
                // Check if we have a document ID
                $documentId = $_POST['documentId'] ?? null;
                if (!$documentId) {
                    throw new Exception("Document ID is required for selfie upload");
                }
                
                error_log("Uploading selfie for document ID: " . $documentId);
                return $this->uploader->handleSelfieUpload($uploadedFile, $documentId);
            }

            throw new Exception("Invalid upload type");
        } catch (Exception $e) {
            error_log("Document upload error in Documents class: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verify identity documents
     * @param string $param Document ID
     * @param array $data Additional verification data
     * @return array Verification result
     */
    public function verify($param = null, $data = null) {
        try {
            // Log incoming parameters to help with debugging
            error_log("verify method called with param: " . (is_array($param) ? json_encode($param) : $param));
            error_log("verify data: " . ($data ? json_encode($data) : 'null'));
            
            // If $param is null or empty but $data contains documentId, use that instead
            if ((!$param || empty($param)) && $data && isset($data['documentId'])) {
                $param = $data['documentId'];
                error_log("Using documentId from data: " . (is_array($param) ? json_encode($param) : $param));
            }
            
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }
            
            // Handle case where userId might be an array (from decoded JWT)
            if (is_array($userId)) {
                error_log("userId is an array in verify method: " . json_encode($userId));
                if (isset($userId['$oid'])) {
                    $userId = $userId['$oid'];
                } else if (isset($userId['_id'])) {
                    $userId = $userId['_id'];
                } else if (isset($userId['id'])) {
                    $userId = $userId['id'];
                } else {
                    // If it's some other array format, log it and throw exception
                    error_log("Unexpected userId format in verify: " . json_encode($userId));
                    throw new Exception('Invalid user ID format: Received array');
                }
            }
            
            // Verify that userId is a string before creating ObjectId
            if (!is_string($userId)) {
                error_log("getUserIdFromToken returned non-string value in verify: " . json_encode($userId));
                throw new Exception('Invalid user ID format: Not a string');
            }
            
            // Validate the userId format - should be 24-digit hex string
            if (!preg_match('/^[a-f0-9]{24}$/i', $userId)) {
                error_log("Invalid userId format in verify: $userId");
                throw new Exception('Invalid user ID format: Not a valid MongoDB ObjectId');
            }

            // For simple marking as reviewed, handle POST data
            if ($data && isset($data['documentId']) && isset($data['reviewed'])) {
                $documentId = $data['documentId'];
                $reviewed = $data['reviewed'];
                
                error_log("Processing document verification for ID: $documentId, reviewed: " . ($reviewed ? 'true' : 'false'));
                
                // Get document record
                $document = $this->collection->findOne([
                    '_id' => new MongoDB\BSON\ObjectId($documentId),
                    'userId' => new MongoDB\BSON\ObjectId($userId)
                ]);
                
                if (!$document) {
                    throw new Exception('Document not found or does not belong to the current user');
                }
                
                // Update document status to mark as reviewed
                $result = $this->collection->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($documentId)],
                    [
                        '$set' => [
                            'reviewedByUser' => (bool)$reviewed,
                            'reviewedAt' => new MongoDB\BSON\UTCDateTime(),
                            'status' => 'submitted',
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]
                    ]
                );
                
                if (is_object($result) && !$result->getModifiedCount()) {
                    throw new Exception('Failed to update verification status');
                } else if (is_array($result) && isset($result['success']) && !$result['success']) {
                    throw new Exception('Failed to update verification status: ' . ($result['error'] ?? 'Unknown error'));
                }
                
                return [
                    'success' => true,
                    'message' => 'Verification marked as reviewed',
                    'status' => 'submitted'
                ];
            }

            // For API request with document ID in URL
            if (!$param) {
                throw new Exception('Document ID is required');
            }
            
            // Handle case where document ID ($param) might be an array
            if (is_array($param)) {
                error_log("Document ID param is an array: " . json_encode($param));
                
                if (isset($param['documentId'])) {
                    $param = $param['documentId'];
                } else if (isset($param['$oid'])) {
                    $param = $param['$oid'];
                } else if (isset($param['_id'])) {
                    $param = $param['_id'];
                } else if (isset($param['id'])) {
                    $param = $param['id'];
                } else {
                    error_log("Unexpected document ID format: " . json_encode($param));
                    throw new Exception('Invalid document ID format: Received array without documentId');
                }
            }
            
            // Ensure param is a string and has valid format
            if (!is_string($param)) {
                error_log("Document ID is not a string: " . json_encode($param));
                throw new Exception('Invalid document ID format: Not a string');
            }
            
            if (!preg_match('/^[a-f0-9]{24}$/i', $param)) {
                error_log("Invalid document ID format: $param");
                throw new Exception('Invalid document ID format: Not a valid MongoDB ObjectId');
            }
            
            error_log("Verified document ID: $param");

            // Get document record
            $document = $this->collection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($param),
                'userId' => new MongoDB\BSON\ObjectId($userId)
            ]);

            if (!$document) {
                throw new Exception('Document not found');
            }

            if (!isset($document['selfieImageUrl'])) {
                throw new Exception('Selfie image required for verification');
            }

            // Initialize AWS Rekognition client if available
            try {
                if (class_exists('FaceVerifier')) {
                    error_log("FaceVerifier class is available, attempting face verification");
                    $faceVerifier = new FaceVerifier();
                    
                    // Get file paths
                    $documentPath = __DIR__ . '/..' . $document['documentImageUrl'];
                    $selfiePath = __DIR__ . '/..' . $document['selfieImageUrl'];
                    
                    error_log("Document path: $documentPath");
                    error_log("Selfie path: $selfiePath");
                    
                    // Verify files exist
                    if (!file_exists($documentPath)) {
                        error_log("Document image file not found at path: $documentPath");
                        throw new Exception('Document image file not found');
                    }
                    if (!file_exists($selfiePath)) {
                        error_log("Selfie image file not found at path: $selfiePath");
                        throw new Exception('Selfie image file not found');
                    }
                    
                    // Call the face verification process
                    error_log("Calling face verification with document ID: $param");
                    $faceResult = $faceVerifier->verifySelfie([
                        'tmp_name' => $selfiePath,
                        'name' => basename($selfiePath)
                    ], $param);
                    
                    return $faceResult;
                } else {
                    error_log("FaceVerifier class not available, falling back to manual review");
                    // Fall back to manually setting the verification status
                    try {
                        $result = $this->collection->updateOne(
                            ['_id' => new MongoDB\BSON\ObjectId($param)],
                            [
                                '$set' => [
                                    'status' => 'pending_review',
                                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                                ]
                            ]
                        );
                        
                        error_log("Document status updated to pending_review");
                        
                        return [
                            'success' => true,
                            'message' => 'Face verification not available. Document marked for manual review.',
                            'status' => 'pending_review',
                            'needs_review' => true
                        ];
                    } catch (Exception $updateEx) {
                        error_log("Error updating document status: " . $updateEx->getMessage());
                        throw $updateEx;
                    }
                }
            } catch (Exception $e) {
                error_log('Face verification error: ' . $e->getMessage());
                
                // Update document status to indicate verification failure
                try {
                    $this->collection->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($param)],
                        [
                            '$set' => [
                                'status' => 'pending_review',
                                'verificationError' => $e->getMessage(),
                                'updatedAt' => new MongoDB\BSON\UTCDateTime()
                            ]
                        ]
                    );
                    
                    error_log("Document status updated to pending_review with error");
                } catch (Exception $updateEx) {
                    error_log("Failed to update document status after verification error: " . $updateEx->getMessage());
                }
                
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'status' => 'pending_review'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate image quality score based on face detection results
     * @param array $faceDetails Face detection details from AWS Rekognition
     * @return float Quality score between 0 and 1
     */
    private function calculateImageQuality($faceDetails) {
        $weights = [
            'Brightness' => 0.2,
            'Sharpness' => 0.3,
            'Quality' => 0.5
        ];

        $quality = $faceDetails['Quality'];
        $score = 0;

        foreach ($weights as $metric => $weight) {
            $score += ($quality[$metric] / 100) * $weight;
        }

        return max(0, min(1, $score));
    }

    /**
     * Calculate liveness score based on face detection results
     * @param array $faceDetails Face detection details from AWS Rekognition
     * @return float Liveness score between 0 and 1
     */
    private function calculateLivenessScore($faceDetails) {
        // Factors that contribute to liveness detection
        $factors = [
            'EyesOpen' => $faceDetails['EyesOpen']['Confidence'] / 100,
            'MouthOpen' => $faceDetails['MouthOpen']['Confidence'] / 100,
            'Smile' => $faceDetails['Smile']['Confidence'] / 100,
            'Pose' => (100 - abs($faceDetails['Pose']['Pitch'])) / 100
        ];

        return array_sum($factors) / count($factors);
    }

    /**
     * Determine verification status based on comparison results and scores
     * @param array $comparison Face comparison results
     * @param array $scores Quality and liveness scores
     * @return string Status (approved, rejected, pending_review)
     */
    private function determineVerificationStatus($comparison, $scores) {
        // Automatic rejection conditions
        if ($comparison['similarity'] < 0.4) {
            return 'rejected';
        }

        // Automatic approval conditions
        if ($comparison['similarity'] > 0.8 &&
            $scores['documentQualityScore'] > 0.7 &&
            $scores['livenessScore'] > 0.7) {
            return 'approved';
        }

        // All other cases need manual review
        return 'pending_review';
    }

    /**
     * Create a document record from personal information
     * 
     * @param string|array $documentId Optional existing document ID or data array
     * @param array $data Document data
     * @return array Response with creation status
     */
    public function create($documentId = null, $data = null) {
        try {
            // Handle case where parameters might be swapped or incorrectly passed
            if (is_array($documentId) && $data === null) {
                // documentId contains the data, data is null
                error_log("Parameters appear to be swapped - documentId contains data");
                $data = $documentId;
                $documentId = null;
            }
            
            // If documentId is not a string but an array, it's likely the data
            if (is_array($documentId) && isset($documentId['firstName'])) {
                error_log("documentId parameter contains form data, swapping parameters");
                $data = $documentId;
                $documentId = null;
            }

            // Get user ID from token
            try {
                $userId = $this->auth->getUserIdFromToken();
                
                // Debug the userId to see what we're receiving
                error_log("Debug getUserIdFromToken result: " . (is_array($userId) ? json_encode($userId) : $userId));
                
                // Handle case where userId might be an array (from decoded JWT)
                if (is_array($userId) && isset($userId['$oid'])) {
                    $userId = $userId['$oid'];
                } else if (is_array($userId) && isset($userId['_id'])) {
                    $userId = $userId['_id'];
                } else if (is_array($userId) && isset($userId['id'])) {
                    $userId = $userId['id'];
                } else if (is_array($userId)) {
                    // If it's some other array format, log it and throw exception
                    error_log("Unexpected userId format: " . json_encode($userId));
                    throw new Exception('Invalid user ID format: Received array');
                }
                
                // Verify that userId is a string before creating ObjectId
                if (!is_string($userId)) {
                    error_log("getUserIdFromToken returned non-string value: " . json_encode($userId));
                    throw new Exception('Invalid user ID format: Not a string');
                }
                
                // Validate the userId format - should be 24-digit hex string
                if (!preg_match('/^[a-f0-9]{24}$/i', $userId)) {
                    error_log("Invalid userId format: $userId");
                    throw new Exception('Invalid user ID format: Not a valid MongoDB ObjectId');
                }
            } catch (Exception $e) {
                error_log("Auth error: " . $e->getMessage());
                throw new Exception('Authentication required: ' . $e->getMessage());
            }

            // Get the raw POST data if no data provided
            if (!$data) {
                $data = json_decode(file_get_contents('php://input'), true);
            }
            
            if (!$data) {
                throw new Exception('No data provided');
            }

            // Ensure required fields
            $requiredFields = ['firstName', 'lastName', 'dateOfBirth', 'address', 'city', 'state', 'postalCode', 'country'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Required field missing: $field");
                }
            }

            // Create timestamp objects
            $now = new MongoDB\BSON\UTCDateTime();
            
            // Debug the userId at this point to see its exact format
            error_log("userId before creating document: " . json_encode($userId) . ", type: " . gettype($userId));
            
            try {
                // Try to create the ObjectId directly, with error handling
                $userObjectId = new MongoDB\BSON\ObjectId($userId);
                error_log("Successfully created ObjectId from: " . $userId);
                
                // Let's check what the ObjectId actually contains when serialized
                error_log("Serialized ObjectId: " . json_encode($userObjectId));
                error_log("ObjectId class: " . get_class($userObjectId));
                error_log("ObjectId stringified: " . (string)$userObjectId);
                
                // Create the document with all required fields from the schema
                $document = [
                    'userId' => new MongoDB\BSON\ObjectId($userId),
                    'firstName' => $data['firstName'],
                    'lastName' => $data['lastName'],
                    'dateOfBirth' => new MongoDB\BSON\UTCDateTime(strtotime($data['dateOfBirth']) * 1000),
                    'address' => $data['address'],
                    'city' => $data['city'],
                    'state' => $data['state'],
                    'postalCode' => $data['postalCode'],
                    'country' => $data['country'],
                    // Make sure documentType is one of the allowed enum values
                    'documentType' => isset($data['documentType']) && in_array($data['documentType'], ['passport', 'drivers_license', 'national_id', 'residence_permit', 'pending']) 
                                   ? $data['documentType'] : 'pending',
                    'documentNumber' => $data['documentNumber'] ?? '',
                    'documentExpiry' => isset($data['documentExpiry']) && !empty($data['documentExpiry']) 
                                      ? new MongoDB\BSON\UTCDateTime(strtotime($data['documentExpiry']) * 1000) 
                                      : null,
                    'documentImageUrl' => null,
                    'selfieImageUrl' => null,
                    'similarityScore' => null,
                    // Make sure status is one of the allowed enum values
                    'status' => isset($data['status']) && in_array($data['status'], ['pending', 'approved', 'rejected', 'expired']) 
                              ? $data['status'] : 'pending',
                    'createdAt' => $now,
                    'updatedAt' => $now,
                    'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                    // Convert to int for MongoDB (bsonType: "int" in schema)
                    'verificationAttempts' => 0,
                    'metadata' => [
                        'documentAuthenticityScore' => null,
                        'documentQualityScore' => null, 
                        'faceDetectionScore' => null,
                        'livenessScore' => null
                    ]
                ];

                // Log what we're inserting for debugging
                error_log("Creating document: " . json_encode($document));

                if ($documentId && is_string($documentId)) {
                    // Update existing document
                    try {
                        $docObjectId = new MongoDB\BSON\ObjectId($documentId);
                        $result = $this->collection->updateOne(
                            ['_id' => $docObjectId],
                            ['$set' => $document]
                        );
                        
                        // Check the return type and handle accordingly
                        error_log("Update result type: " . gettype($result));
                        if (is_object($result)) {
                            // Standard MongoDB driver returns an object
                            if (!$result->getModifiedCount()) {
                                throw new Exception('Failed to update document - no modifications made');
                            }
                        } else if (is_array($result)) {
                            // Custom wrapper might return an array
                            error_log("Update result array: " . json_encode($result));
                            if (isset($result['modifiedCount']) && $result['modifiedCount'] <= 0) {
                                throw new Exception('Failed to update document - no modifications made');
                            }
                            if (isset($result['success']) && $result['success'] === false) {
                                throw new Exception('Failed to update document: ' . ($result['error'] ?? 'Unknown error'));
                            }
                        } else {
                            throw new Exception('Unexpected result type from updateOne: ' . gettype($result));
                        }
                        
                        return [
                            'success' => true,
                            'documentId' => $documentId,
                            'message' => 'Document updated successfully'
                        ];
                    } catch (Exception $e) {
                        error_log("Error updating document: " . $e->getMessage());
                        return [
                            'success' => false,
                            'error' => 'Failed to update document: ' . $e->getMessage()
                        ];
                    }
                } else {
                    // Insert new document
                    try {
                        $result = $this->collection->insertOne($document);
                        
                        // Check the return type and handle accordingly
                        error_log("Insert result type: " . gettype($result));
                        if (is_object($result)) {
                            // Standard MongoDB driver returns an object
                            if (!$result->getInsertedId()) {
                                throw new Exception('Failed to create document - no ID returned');
                            }
                            $insertedId = (string)$result->getInsertedId();
                        } else if (is_array($result)) {
                            // Custom MongoCollection wrapper returns an array
                            error_log("Insert result array: " . json_encode($result));
                            
                            // Check for success flag
                            if (isset($result['success']) && $result['success'] === false) {
                                // Try to get more detailed validation errors
                                if (strpos($result['error'], 'Document failed validation') !== false) {
                                    error_log("Document validation failed, checking detailed error");
                                    
                                    // Let's try to get more details about the validation error
                                    try {
                                        // Use the collection's raw validate method if available
                                        if (method_exists($this->collection, 'validate')) {
                                            $validationResult = $this->collection->validate($document);
                                            error_log("Validation details: " . json_encode($validationResult));
                                        }
                                    } catch (Exception $validationEx) {
                                        error_log("Failed to get validation details: " . $validationEx->getMessage());
                                    }
                                }
                                
                                throw new Exception('Failed to create document: ' . ($result['error'] ?? 'Unknown error'));
                            }
                            
                            // Get the ID from the expected location
                            $insertedId = $result['id'] ?? null;
                            
                            if (!$insertedId) {
                                // Try alternate places where ID might be
                                $insertedId = $result['insertedId'] ?? $result['_id'] ?? null;
                                
                                // If still no ID but the operation succeeded, this is weird
                                if (!$insertedId && isset($result['success']) && $result['success']) {
                                    error_log("Warning: Document insert succeeded but no ID was returned: " . json_encode($result));
                                    throw new Exception('Document created but no ID was returned');
                                }
                            }
                        } else {
                            throw new Exception('Unexpected result type from insertOne: ' . gettype($result));
                        }
                        
                        if (!$insertedId) {
                            throw new Exception('Failed to retrieve inserted document ID');
                        }
                        
                        return [
                            'success' => true,
                            'documentId' => (string)$insertedId,
                            'message' => 'Document created successfully'
                        ];
                    } catch (Exception $e) {
                        error_log("Error inserting document: " . $e->getMessage());
                        return [
                            'success' => false,
                            'error' => 'Failed to create document: ' . $e->getMessage()
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log("ObjectId creation error: " . $e->getMessage());
                return [
                    'success' => false,
                    'error' => 'Invalid user ID format: ' . $e->getMessage()
                ];
            }
        } catch (Exception $e) {
            error_log("Document creation error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get a report of verification documents for admin dashboard
     * 
     * @param string $status Optional filter by status
     * @param string $startDate Optional filter by start date
     * @param string $endDate Optional filter by end date
     * @return array Report with stats and verification records
     */
    public function getVerificationReport($status = '', $startDate = null, $endDate = null) {
        try {
            // Build query filter
            $filter = [];
            
            // Add status filter if provided
            if (!empty($status)) {
                $filter['status'] = strtoupper($status);
            }
            
            // Add date range filter if provided
            if ($startDate || $endDate) {
                $filter['createdAt'] = [];
                
                if ($startDate) {
                    $startDateTime = new MongoDB\BSON\UTCDateTime(strtotime($startDate) * 1000);
                    $filter['createdAt']['$gte'] = $startDateTime;
                }
                
                if ($endDate) {
                    // Add 1 day to end date to include the entire day
                    $endDateTime = new MongoDB\BSON\UTCDateTime(strtotime($endDate . ' +1 day') * 1000);
                    $filter['createdAt']['$lt'] = $endDateTime;
                }
            }
            
            // Get documents with user information
            $pipeline = [
                ['$match' => $filter],
                [
                    '$lookup' => [
                        'from' => 'users',
                        'localField' => 'userId',
                        'foreignField' => '_id',
                        'as' => 'user'
                    ]
                ],
                [
                    '$project' => [
                        'id' => ['$toString' => '$_id'],
                        'userId' => ['$toString' => '$userId'],
                        'userName' => ['$concat' => ['$firstName', ' ', '$lastName']],
                        'email' => ['$arrayElemAt' => ['$user.email', 0]],
                        'status' => '$status',
                        'documentType' => 1,
                        'documentNumber' => 1,
                        'created' => '$createdAt',
                        'updated' => '$updatedAt'
                    ]
                ],
                ['$sort' => ['updated' => -1]]
            ];
            
            $verifications = $this->collection->aggregate($pipeline);
            
            // Get statistics
            $stats = [
                'total' => $this->collection->count([]),
                'approved' => $this->collection->count(['status' => 'APPROVED']),
                'rejected' => $this->collection->count(['status' => 'REJECTED']),
                'pending' => $this->collection->count(['status' => 'PENDING']),
                'error' => $this->collection->count(['status' => 'ERROR']),
                'expired' => $this->collection->count(['status' => 'EXPIRED'])
            ];
            
            return [
                'success' => true,
                'stats' => $stats,
                'verifications' => $verifications
            ];
        } catch (Exception $e) {
            error_log("Error in getVerificationReport: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Override verification status by administrator
     * 
     * @param string $userId User ID
     * @param string $status New status
     * @param string $reason Reason for override
     * @return array Result
     */
    public function adminOverrideVerification($userId, $status, $reason) {
        try {
            // Validate status
            $validStatuses = ['APPROVED', 'REJECTED', 'PENDING'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception("Invalid status. Must be one of: " . implode(', ', $validStatuses));
            }
            
            // Find the document for this user
            $document = $this->collection->findOne(['userId' => new MongoDB\BSON\ObjectId($userId)]);
            
            if (!$document) {
                throw new Exception("No verification document found for user ID: $userId");
            }
            
            // Create audit log entry
            $adminId = null;
            if (class_exists('AdminAuth')) {
                $adminAuth = new AdminAuth();
                $adminId = $adminAuth->getAdminId();
            }
            
            $auditLog = [
                'action' => 'verification_override',
                'documentId' => $document['_id'],
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'previousStatus' => $document['status'],
                'newStatus' => $status,
                'reason' => $reason,
                'adminId' => $adminId,
                'timestamp' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Add audit log to database
            $auditCollection = $this->db->getCollection('audit_logs');
            $auditCollection->insertOne($auditLog);
            
            // Update document status
            $result = $this->collection->updateOne(
                ['userId' => new MongoDB\BSON\ObjectId($userId)],
                [
                    '$set' => [
                        'status' => $status,
                        'adminOverride' => true,
                        'adminOverrideReason' => $reason,
                        'adminOverrideTime' => new MongoDB\BSON\UTCDateTime(),
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            if (!$result['success'] && !$result['modifiedCount']) {
                throw new Exception("Failed to update document status");
            }
            
            // Update user verification status
            $userCollection = $this->db->getCollection('users');
            $userCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                [
                    '$set' => [
                        'verification.status' => $status,
                        'verification.updatedAt' => new MongoDB\BSON\UTCDateTime(),
                        'verification.adminOverride' => true
                    ]
                ]
            );
            
            return [
                'success' => true,
                'message' => "Verification status updated to $status",
                'documentId' => (string)$document['_id']
            ];
        } catch (Exception $e) {
            error_log("Error in adminOverrideVerification: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get detailed document information for admin review
     * 
     * @param string $documentId Document ID
     * @return array Document details
     */
    public function getDocumentDetailsForAdmin($documentId) {
        try {
            // Get document with user information
            $pipeline = [
                ['$match' => ['_id' => new MongoDB\BSON\ObjectId($documentId)]],
                [
                    '$lookup' => [
                        'from' => 'users',
                        'localField' => 'userId',
                        'foreignField' => '_id',
                        'as' => 'user'
                    ]
                ],
                [
                    '$project' => [
                        'id' => ['$toString' => '$_id'],
                        'userId' => ['$toString' => '$userId'],
                        'userName' => ['$concat' => ['$firstName', ' ', '$lastName']],
                        'email' => ['$arrayElemAt' => ['$user.email', 0]],
                        'firstName' => 1,
                        'lastName' => 1,
                        'dateOfBirth' => 1,
                        'address' => 1,
                        'city' => 1,
                        'state' => 1,
                        'postalCode' => 1,
                        'country' => 1,
                        'documentType' => 1,
                        'documentNumber' => 1,
                        'documentExpiry' => 1,
                        'documentImageUrl' => 1,
                        'selfieImageUrl' => 1,
                        'status' => 1,
                        'similarityScore' => 1,
                        'metadata' => 1,
                        'adminOverride' => 1,
                        'adminOverrideReason' => 1,
                        'adminOverrideTime' => 1,
                        'createdAt' => 1,
                        'updatedAt' => 1,
                        'verificationAttempts' => 1,
                        'ipAddress' => 1,
                        'userAgent' => 1
                    ]
                ]
            ];
            
            $document = $this->collection->aggregate($pipeline)[0] ?? null;
            
            if (!$document) {
                throw new Exception("Document not found");
            }
            
            // Get audit logs for this document
            $auditCollection = $this->db->getCollection('audit_logs');
            $auditLogs = $auditCollection->find(
                ['documentId' => new MongoDB\BSON\ObjectId($documentId)],
                ['sort' => ['timestamp' => -1]]
            );
            
            return [
                'success' => true,
                'document' => $document,
                'auditLogs' => $auditLogs
            ];
        } catch (Exception $e) {
            error_log("Error in getDocumentDetailsForAdmin: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
} 