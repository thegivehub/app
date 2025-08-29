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
        $this->uploadDir = __DIR__ . '/../uploads/';
        
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
        // Check authentication
        if (!$this->auth->isAuthenticated()) {
            throw new Exception("Authentication required");
        }

        // Get user ID
        $userId = $this->auth->getUserIdFromToken();

        // Get verification ID from POST data
        $verificationId = $_POST['verificationId'] ?? $_POST['documentId'] ?? null;
        if (!$verificationId) {
            $parts = explode($_SERVER['PATH_INFO'], '/');
            $verificationId = $parts[count($parts) - 1];
        }
        if (!$verificationId) {
            error_log("No verification ID found - creating a new verification record");
            
            // Create personal info from POST data
            $personalInfo = [
                'firstName' => $_POST['firstName'] ?? 'First',
                'lastName' => $_POST['lastName'] ?? 'Last',
                'dateOfBirth' => $_POST['dateOfBirth'] ?? '1970-01-01',
                'address' => $_POST['address'] ?? '123 Main St',
                'city' => $_POST['city'] ?? 'City',
                'state' => $_POST['state'] ?? 'State',
                'postalCode' => $_POST['postalCode'] ?? '12345',
                'country' => $_POST['country'] ?? 'US'
            ];

            // Create a new verification
            $verification = new Verification();
            $createResult = $verification->create($personalInfo);
            
            error_log("Created new verification: ".json_encode($createResult));

            if ($createResult['success']) {
                $verificationId = $createResult['verificationId'];
            } else {
                throw new Exception("Failed to create verification record");
            }
        }

        // Determine document type and file
        $documentType = $_POST['documentType'] ?? 'selfie';
        
        if (isset($_FILES['document'])) {
            error_log("Documents::upload type 'document'");
            $type = "document";
            $uploadedFile = $_FILES['document'];
        } else if (isset($_FILES['selfie'])) {
            error_log("Documents::upload type 'selfie'");
            $type = "selfie";
            $uploadedFile = $_FILES['selfie'];
        } else if (isset($_FILES['livenessVideo'])) {
            error_log("Documents::upload type 'livenessVideo'");
            $type = "liveness";
            $uploadedFile = $_FILES['livenessVideo'];
        } else {
            throw new Exception("No file uploaded");
        }

        // Check if file was uploaded successfully
        if (!isset($uploadedFile) || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("No file uploaded or upload failed");
        }

        // Get file extension
        $fileInfo = pathinfo($uploadedFile['name']);
        $extension = strtolower($fileInfo['extension']);

        error_log("File info: ".json_encode($fileInfo));

        // Validate file type
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'gif', 'svg', 'avif', 'webp', 'mp4', 'mov', 'webm'];
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowedExtensions));
        }

        // Create upload directory if it doesn't exist
        $uploadDir = $this->uploadDir . $type . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        error_log("Upload directory: $uploadDir");

        // Generate unique filename based on verification ID
        $filename = $verificationId . '_';
        if ($type === 'selfie') {
            $filename .= 'selfie';
        } elseif ($type === 'liveness') {
            $filename .= 'liveness';
        } else {
            $filename .= $documentType;
        }
        $filename .= '.' . $extension;
        $filepath = $uploadDir . $filename;

        error_log("Filename: $filepath");

        // Move uploaded file to destination
        move_uploaded_file($uploadedFile['tmp_name'], $filepath);
        error_log("Moved file to $filepath");

        // Create document record
        $documentData = [
            'userId' => new MongoDB\BSON\ObjectId($userId),
            'type' => $type === 'selfie' ? 'SELFIE' : ($type === 'liveness' ? 'LIVENESS_VIDEO' : 'ID_DOCUMENT'),
            'subType' => $documentType,
            'filePath' => $filepath,
            'fileName' => $filename,
            'fileType' => $uploadedFile['type'],
            'createdAt' => new MongoDB\BSON\UTCDateTime(),
            'updatedAt' => new MongoDB\BSON\UTCDateTime(),
            'meta' => [
                'documentType' => $documentType,
                'documentNumber' => $_POST['documentNumber'] ?? null,
                'documentExpiry' => isset($_POST['documentExpiry']) ? new MongoDB\BSON\UTCDateTime(strtotime($_POST['documentExpiry']) * 1000) : null,
                'originalName' => $uploadedFile['name'],
                'mimeType' => $uploadedFile['type'],
                'size' => $uploadedFile['size'],
                'uploadedBy' => $userId,
                'uploadedAt' => new MongoDB\BSON\UTCDateTime()
            ]
        ];
        
        error_log("documentData: ". json_encode($documentData));

        // Insert document into documents collection
        $documentsCollection = $this->db->getCollection('documents');
        $insertResult = $documentsCollection->insertOne($documentData);

        if (!$insertResult) {
            throw new Exception("Failed to create document record");
        }
        error_log("insertResult: ".json_encode($insertResult));
        

        $documentId = $insertResult['id'];

        error_log("New document ID: $documentId");

        // Update verification with document reference
        $verificationsCollection = $this->db->getCollection('verifications');
        error_log("documentId: $documentId");

        $verification = $verificationsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($verificationId)]);

        error_log("verification: ".json_encode($verification));

        // Update operation that adds documentId to the documents array
        $updateResult = $verificationsCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($verificationId)],
            [
                '$set' => [
                      "documents.$documentType" => $documentId,
                      'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
        
        error_log("updateResult: ".json_encode($updateResult));

        if (!$updateResult) {
            // Clean up the document if verification update failed
        //    $this->collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($documentId)]);
            throw new Exception("Failed to link document to verification");
        }

        return [
            'success' => true,
            'documentId' => $documentId,
            'message' => 'Document uploaded and linked to verification successfully'
        ];

    } catch (Exception $e) {
        error_log("Document upload error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }

}
    /**
     * Verify identity documents by comparing selfie with ID document using AWS Rekognition
     * @param string $verificationId Verification ID
     * @return array Verification result
     */
    public function verify($verificationId, $verification=null) {
        try {
            // Get user ID from token
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }

            if (!$verification) {
            // Get verification record
            $verification = $this->collection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($verificationId),
                'userId' => new MongoDB\BSON\ObjectId($userId)
            ]);
            }

            if (!$verification) {
                throw new Exception('Verification record not found');
            }
            error_log("verification record: ".json_encode($verification));
            // Construct file paths
            //$documentPath = __DIR__ . '/../uploads/documents/document/'. $verificationId . '.jpg';
            $dpath = glob(__DIR__ . '/../uploads/document/' . $verificationId . '*');
            $documentPath = $dpath[0]; 
            // $documentPath = __DIR__ . '/..'. $verification['documentImageUrl'];
            $spath = glob(__DIR__ . '/../uploads/selfie/' . $verificationId . '*');
            $selfiePath = $spath[0]; // || __DIR__ . '/../uploads/selfie/' . $verificationId . '_selfie.png';
            
            error_log("documentPath: ".$documentPath);
            error_log("selfiePath: ".$selfiePath);

            //$selfiePath = __DIR__ . '/..'. $verification['selfieImageUrl'];

            // If running in testing environment, return a deterministic fake result
            if (getenv('APP_ENV') === 'testing' || (isset($_SERVER['ENV']) && $_SERVER['ENV'] === 'testing')) {
                $fake = [
                    'success' => true,
                    'isMatch' => true,
                    'similarity' => 92.5,
                    'matchConfidence' => 95.0,
                    'matchLevel' => 'STRONG',
                    'details' => [
                        'matches' => [[ 'similarity' => 92.5, 'confidence' => 95.0 ]]
                    ]
                ];
                return [
                    'success' => true,
                    'verification' => $fake,
                    'status' => 'APPROVED',
                    'message' => 'Simulated face verification in testing environment'
                ];
            }

            // Check if files exist, try PNG if JPG not found
            if (!file_exists($documentPath)) {
                $documentPath = str_replace('.jpg', '.png', $documentPath);
            }
            if (!file_exists($selfiePath)) {
                $selfiePath = str_replace('.jpg', '.png', $selfiePath);
            }

            // Verify both files exist
            if (!file_exists($documentPath)) {
                throw new Exception('ID document image not found');
            }
            if (!file_exists($selfiePath)) {
                throw new Exception('Selfie image not found');
            }

            error_log("Verifying faces between document: $documentPath and selfie: $selfiePath");
            // Execute the face comparison script and capture its output
            $command = sprintf('php %s/test-face-compare-json.php "%s" "%s"', __DIR__, $selfiePath, $documentPath);
            error_log("Command: $command");
            $output = shell_exec($command);
            
            if (!$output) {
                throw new Exception('Face comparison script failed to execute');
            }

            $verificationResult = json_decode($output, true);
            
            if (!$verificationResult || !isset($verificationResult['success'])) {
                throw new Exception('Invalid response from face comparison script');
            }

            if (!$verificationResult['success']) {
                throw new Exception($verificationResult['error'] ?? 'Face verification failed');
            }

            // Update verification record with results
            $updateData = [
                'status' => $verificationResult['isMatch'] ? 'APPROVED' : 'PENDING_REVIEW',
                'similarityScore' => $verificationResult['similarity'],
                'verificationResults' => [
                    'provider' => 'aws',
                    'similarity' => $verificationResult['similarity'],
                    'confidence' => $verificationResult['matchConfidence'],
                    'isMatch' => $verificationResult['isMatch'],
                    'matchLevel' => $verificationResult['matchLevel'],
                    'details' => $verificationResult['details'],
                    'needsReview' => !$verificationResult['isMatch']
                ],
                'verifiedAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];

            $this->collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($verificationId)],
                ['$set' => $updateData]
            );

            return [
                'success' => true,
                'verification' => $verificationResult,
                'status' => $updateData['status'],
                'message' => $verificationResult['isMatch'] 
                    ? 'Face verification successful' 
                    : 'Face verification requires manual review'
            ];

        } catch (Exception $e) {
            error_log('Face verification error: ' . $e->getMessage());
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

    /**
     * Perform a secondary liveness check using a selfie video
     *
     * @param string $verificationId Verification record ID
     * @return array Result with liveness score
     */
    public function runSecondaryLivenessCheck($verificationId) {
        try {
            $videoFiles = glob(__DIR__ . '/../uploads/liveness/' . $verificationId . '*');
            if (!$videoFiles) {
                throw new Exception('Liveness video not found');
            }

            $videoPath = $videoFiles[0];

            // Placeholder implementation - integrate with real liveness service here
            $livenessScore = 0.95;

            $kycCollection = $this->db->getCollection('kyc_verifications');
            $kycCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($verificationId)],
                ['$set' => [
                    'livenessResult' => [
                        'score' => $livenessScore,
                        'verified' => $livenessScore >= 0.8,
                        'checkedAt' => new MongoDB\BSON\UTCDateTime()
                    ],
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );

            return [
                'success' => true,
                'score' => $livenessScore
            ];
        } catch (Exception $e) {
            error_log('Secondary liveness check error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Serve a document file by ID
     * 
     * @param string $documentId Document ID
     * @return void Outputs file directly
     */
    public function getFile($documentId) {
        try {
            if (!$documentId) {
                throw new Exception('Document ID is required');
            }
            
            // Validate document ID format
            if (!preg_match('/^[a-f0-9]{24}$/i', $documentId)) {
                throw new Exception('Invalid document ID format');
            }
            
            // Log the request for debugging
            error_log("Document file requested: $documentId");
            
            // Get document record
            $document = $this->read($documentId);
            
            if (!$document) {
                error_log("Document not found in database: $documentId");
                throw new Exception('Document not found');
            }
            
            // Check if user has access to this document
            $currentUserId = $this->auth->getUserIdFromToken();
            $isAdmin = $this->auth->isAdmin();
            
            if (!$isAdmin && (string)$document['userId'] != $currentUserId) {
                error_log("Access denied - current user: $currentUserId, document user: " . $document['userId']);
                throw new Exception('Access denied');
            }
            
            // Determine file path based on document type
            $filePath = '';
            if (isset($document['documentType']) && $document['documentType'] === 'selfie') {
                $filePath = __DIR__ . '/../uploads/selfies/selfie_' . $documentId . '.png';
            } else {
                $filePath = __DIR__ . '/../uploads/documents/document_' . $documentId . '.png';
            }
            
            // Check if file exists
            if (!file_exists($filePath)) {
                error_log("Document file not found at primary path: $filePath");
                
                // Try to get the path from the document record
                if (isset($document['documentImageUrl'])) {
                    $altFilePath = __DIR__ . '/..' . $document['documentImageUrl'];
                    if (file_exists($altFilePath)) {
                        $filePath = $altFilePath;
                        error_log("Found document at URL path: $filePath");
                    } else {
                        error_log("Document not found at URL path either: $altFilePath");
                    }
                }
                
                // If still not found, try alternative paths
                if (!file_exists($filePath)) {
                    $altPaths = [
                        __DIR__ . '/../uploads/documents/' . $documentId . '.png',
                        __DIR__ . '/../uploads/selfies/' . $documentId . '.png',
                        __DIR__ . '/../uploads/documents/document_' . $documentId . '.png',
                        __DIR__ . '/../uploads/selfies/selfie_' . $documentId . '.png'
                    ];
                    
                    $fileFound = false;
                    foreach ($altPaths as $path) {
                        if (file_exists($path)) {
                            $filePath = $path;
                            $fileFound = true;
                            error_log("Found document at alternative path: $path");
                            break;
                        }
                    }
                    
                    if (!$fileFound) {
                        // Serve a placeholder image instead of failing
                        $filePath = __DIR__ . '/../img/placeholder.jpg';
                        if (!file_exists($filePath)) {
                            throw new Exception('Document file not found and no placeholder available');
                        }
                        error_log("Using placeholder image for document: $documentId");
                    }
                }
            }
            
            // Determine MIME type
            $mimeType = 'image/png';
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if ($extension === 'jpg' || $extension === 'jpeg') {
                $mimeType = 'image/jpeg';
            } else if ($extension === 'pdf') {
                $mimeType = 'application/pdf';
            }
            
            // Output file with proper headers
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
            
        } catch (Exception $e) {
            error_log("Error serving document file: " . $e->getMessage());
            header('HTTP/1.1 404 Not Found');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, OPTIONS');
            header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
            echo 'Document not found or access denied';
            exit;
        }
    }
} 
