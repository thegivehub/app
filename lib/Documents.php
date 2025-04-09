<?php
require_once __DIR__ . '/Collection.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/DocumentUploader.php';

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
        parent::__construct();
        $this->auth = new Auth();
        
        // Initialize collection first
        $db = new Database();
        $this->collection = $db->getCollection($this->collectionName);
        
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
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }

            // For simple marking as reviewed, handle POST data
            if ($data && isset($data['documentId']) && isset($data['reviewed'])) {
                $documentId = $data['documentId'];
                $reviewed = $data['reviewed'];
                
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
                
                if (!$result->getModifiedCount()) {
                    throw new Exception('Failed to update verification status');
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
                    $faceVerifier = new FaceVerifier();
                    
                    // Get file paths
                    $documentPath = __DIR__ . '/..' . $document['documentImageUrl'];
                    $selfiePath = __DIR__ . '/..' . $document['selfieImageUrl'];
                    
                    // Verify files exist
                    if (!file_exists($documentPath)) {
                        throw new Exception('Document image file not found');
                    }
                    if (!file_exists($selfiePath)) {
                        throw new Exception('Selfie image file not found');
                    }
                    
                    // Call the face verification process
                    $faceResult = $faceVerifier->verifySelfie([
                        'tmp_name' => $selfiePath,
                        'name' => basename($selfiePath)
                    ], $param);
                    
                    return $faceResult;
                } else {
                    // Fall back to manually setting the verification status
                    $result = $this->collection->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($param)],
                        [
                            '$set' => [
                                'status' => 'pending_review',
                                'updatedAt' => new MongoDB\BSON\UTCDateTime()
                            ]
                        ]
                    );
                    
                    return [
                        'success' => true,
                        'message' => 'Face verification not available. Document marked for manual review.',
                        'status' => 'pending_review',
                        'needs_review' => true
                    ];
                }
            } catch (Exception $e) {
                error_log('Face verification error: ' . $e->getMessage());
                
                // Update document status to indicate verification failure
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
} 