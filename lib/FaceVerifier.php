<?php
// Updated FaceVerifier.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/FaceRecognitionClient.php';

class FaceVerifier {
    private $db;
    private $auth;
    private $config;
    private $faceApiClient;

    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
        
        // Configuration settings
        $this->config = [
            'upload_dir' => __DIR__ . '/../uploads/selfies/',
            'max_size' => 5 * 1024 * 1024, // 5MB
            'similarity_threshold' => 0.7, // Minimum similarity score to consider faces matching
            'face_detection_min_confidence' => 0.7 // Minimum confidence for face detection
        ];
        
        // Ensure upload directory exists
        if (!is_dir($this->config['upload_dir'])) {
            mkdir($this->config['upload_dir'], 0755, true);
        }
        
        // Initialize face recognition client
        // Choose provider based on what's available or configured
        $provider = 'custom'; // Default to custom API
        
        // Check for AWS 
        if (getenv('AWS_ACCESS_KEY_ID') && getenv('AWS_SECRET_ACCESS_KEY')) {
            $provider = 'aws';
        } 
        // Check for Azure
        else if (getenv('AZURE_FACE_API_KEY')) {
            $provider = 'azure';
        }
        // Check for Google Cloud
        else if (getenv('GOOGLE_APPLICATION_CREDENTIALS')) {
            $provider = 'google';
        }
        
        try {
            $this->faceApiClient = new FaceRecognitionClient($provider);
        } catch (Exception $e) {
            error_log("Failed to initialize face recognition client: " . $e->getMessage());
            // Fallback to custom implementation
            $this->faceApiClient = null;
        }
    }

    /**
     * Capture and verify user's selfie against ID document
     * 
     * @param array $selfieFile Uploaded selfie file (from $_FILES)
     * @param string $documentId ID of the ID document to compare against
     * @return array Verification result
     */
    public function verifySelfie($selfieFile, $documentId) {
        try {
            error_log("Starting selfie verification for document: " . (is_array($documentId) ? json_encode($documentId) : $documentId));
            
            // Handle case where documentId might be an array
            if (is_array($documentId)) {
                error_log("DocumentId is an array in verifySelfie: " . json_encode($documentId));
                if (isset($documentId['documentId'])) {
                    $documentId = $documentId['documentId'];
                } else if (isset($documentId['$oid'])) {
                    $documentId = $documentId['$oid'];
                } else if (isset($documentId['_id'])) {
                    $documentId = $documentId['_id'];
                } else if (isset($documentId['id'])) {
                    $documentId = $documentId['id'];
                } else {
                    error_log("Unexpected documentId format in verifySelfie: " . json_encode($documentId));
                    throw new Exception('Invalid document ID format: Received array without documentId');
                }
            }
            
            // Ensure documentId is a string and has valid format
            if (!is_string($documentId)) {
                error_log("DocumentId is not a string: " . json_encode($documentId));
                throw new Exception('Invalid document ID format: Not a string');
            }
            
            if (!preg_match('/^[a-f0-9]{24}$/i', $documentId)) {
                error_log("Invalid documentId format: $documentId");
                throw new Exception('Invalid document ID format: Not a valid MongoDB ObjectId');
            }
            
            error_log("Verified documentId: $documentId");
            
            // Get user ID from token
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }
            
            // Handle case where userId might be an array (from decoded JWT)
            if (is_array($userId)) {
                error_log("userId is an array in verifySelfie: " . json_encode($userId));
                if (isset($userId['$oid'])) {
                    $userId = $userId['$oid'];
                } else if (isset($userId['_id'])) {
                    $userId = $userId['_id'];
                } else if (isset($userId['id'])) {
                    $userId = $userId['id'];
                } else {
                    // If it's some other array format, log it and throw exception
                    error_log("Unexpected userId format in verifySelfie: " . json_encode($userId));
                    throw new Exception('Invalid user ID format: Received array');
                }
            }
            
            // Verify that userId is a string before creating ObjectId
            if (!is_string($userId)) {
                error_log("getUserIdFromToken returned non-string value in verifySelfie: " . json_encode($userId));
                throw new Exception('Invalid user ID format: Not a string');
            }
            
            // Validate the userId format - should be 24-digit hex string
            if (!preg_match('/^[a-f0-9]{24}$/i', $userId)) {
                error_log("Invalid userId format in verifySelfie: $userId");
                throw new Exception('Invalid user ID format: Not a valid MongoDB ObjectId');
            }
            
            // Get ID document
            $documentCollection = $this->db->getCollection('documents');
            $document = $documentCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($documentId)]);
            
            if (!$document) {
                throw new Exception('ID document not found');
            }
            
            // Verify document belongs to user
            error_log("Comparing document userId: " . json_encode($document['userId']) . " with user: " . $userId);
            
            // Handle case where document userId might be an ObjectId or string
            $docUserId = $document['userId'];
            if (is_object($docUserId) && get_class($docUserId) === 'MongoDB\BSON\ObjectId') {
                $docUserId = (string)$docUserId;
            } else if (is_array($docUserId) && isset($docUserId['$oid'])) {
                $docUserId = $docUserId['$oid'];
            }
            
            if ($docUserId !== $userId) {
                error_log("Document userId ($docUserId) does not match authenticated user ($userId)");
                throw new Exception('Document does not belong to the authenticated user');
            }
            
            // Check if document is a valid ID type
            $validIdTypes = ['passport', 'drivers_license', 'national_id', 'residence_permit'];
            $docType = $document['documentType'] ?? $document['type'] ?? null;
            
            if (!$docType || !in_array($docType, $validIdTypes)) {
                throw new Exception("Document is not a valid ID type for face verification. Type: $docType");
            }
            
            // If selfie file info is incomplete, add documentId to help find it
            if ((!isset($selfieFile['tmp_name']) || !file_exists($selfieFile['tmp_name'])) && !isset($selfieFile['documentId'])) {
                $selfieFile['documentId'] = $documentId;
                error_log("Adding documentId to selfie file info to help locate file: " . $documentId);
            }
            
            // Validate selfie file
            $this->validateSelfieFile($selfieFile);
            
            // Generate unique filename for selfie
            $selfieFilename = $this->generateSelfieFilename($userId);
            $selfieFilePath = $this->config['upload_dir'] . $selfieFilename;
            
            // Check if file already exists
            if (isset($selfieFile['tmp_name']) && is_uploaded_file($selfieFile['tmp_name'])) {
                // Save selfie file
                if (!move_uploaded_file($selfieFile['tmp_name'], $selfieFilePath)) {
                    error_log("Failed to move uploaded selfie file to: $selfieFilePath");
                    throw new Exception('Failed to save selfie file');
                }
                error_log("Selfie file saved to: $selfieFilePath");
            } else {
                // If the selfie path is already provided directly
                if (isset($selfieFile['tmp_name']) && file_exists($selfieFile['tmp_name'])) {
                    error_log("Using provided selfie path: " . $selfieFile['tmp_name']);
                    $selfieFilePath = $selfieFile['tmp_name'];
                } else {
                    error_log("No valid selfie file found in request");
                    throw new Exception('No selfie file uploaded or upload failed');
                }
            }
            
            // Get ID document file path
            $idDocumentPath = null;
            if (isset($document['documentImageUrl'])) {
                $idDocumentPath = __DIR__ . '/..' . $document['documentImageUrl'];
                error_log("Using document image URL: {$document['documentImageUrl']}");
            } else if (isset($document['filename'])) {
                $idDocumentPath = __DIR__ . '/../uploads/documents/' . $document['filename'];
                error_log("Using document filename: {$document['filename']}");
            }
            
            if (!$idDocumentPath || !file_exists($idDocumentPath)) {
                error_log("ID document file not found at expected paths");
                throw new Exception('ID document file not found');
            }
            
            error_log("Document file found at: $idDocumentPath");
            
            // Development mode check - in development, always succeed
            $devMode = getenv('APP_ENV') === 'development' || getenv('DEVELOPMENT_MODE') === 'true';
            if ($devMode) {
                error_log("Using development mode for face recognition.");
                
                // Create selfie record
                try {
                    $selfie = [
                        'userId' => new MongoDB\BSON\ObjectId($userId),
                        'filename' => $selfieFilename,
                        'idDocumentId' => new MongoDB\BSON\ObjectId($documentId),
                        'timestamp' => new MongoDB\BSON\UTCDateTime(),
                        'status' => 'verified',
                        'verificationResult' => [
                            'success' => true,
                            'similarity' => 0.85,
                            'faceDetected' => true,
                            'livenessScore' => 0.9
                        ]
                    ];
                    
                    // Save selfie record to database
                    $selfieCollection = $this->db->getCollection('selfies');
                    $insertResult = $selfieCollection->insertOne($selfie);
                    
                    if (!$insertResult['success']) {
                        error_log("Failed to save selfie record: " . ($insertResult['error'] ?? 'Unknown error'));
                        throw new Exception('Failed to save selfie record');
                    }
                    
                    $selfieId = $insertResult['id'];
                    error_log("Selfie record created with ID: $selfieId");
                    
                    // Update document record with selfie URL and status
                    $relativeUrl = str_replace(__DIR__ . '/../', '/', $selfieFilePath);
                    $documentCollection->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($documentId)],
                        [
                            '$set' => [
                                'selfieImageUrl' => $relativeUrl,
                                'status' => 'approved',
                                'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                                'metadata.faceDetectionScore' => 0.85,
                                'metadata.livenessScore' => 0.9,
                                'similarityScore' => 0.85
                            ]
                        ]
                    );
                    
                    // Update user verification status
                    $userCollection = $this->db->getCollection('users');
                    $userCollection->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($userId)],
                        [
                            '$set' => [
                                'verification.faceVerification' => [
                                    'status' => 'verified',
                                    'timestamp' => new MongoDB\BSON\UTCDateTime(),
                                    'selfieId' => new MongoDB\BSON\ObjectId($selfieId),
                                    'similarity' => 0.85
                                ]
                            ]
                        ]
                    );
                    
                    return [
                        'success' => true,
                        'verification' => [
                            'success' => true,
                            'similarity' => 0.85,
                            'faceDetected' => true,
                            'livenessScore' => 0.9
                        ],
                        'selfieId' => $selfieId,
                        'message' => 'Face verification successful (development mode)'
                    ];
                } catch (Exception $e) {
                    error_log("Error in development mode face verification: " . $e->getMessage());
                    throw $e;
                }
            }
            
            // If not in development mode, perform actual face verification
            // Create selfie record
            $selfie = [
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'filename' => $selfieFilename,
                'idDocumentId' => new MongoDB\BSON\ObjectId($documentId),
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'status' => 'pending',
                'verificationResult' => null
            ];
            
            // Save selfie record to database
            $selfieCollection = $this->db->getCollection('selfies');
            $insertResult = $selfieCollection->insertOne($selfie);
            
            if (!$insertResult['success']) {
                error_log("Failed to save selfie record: " . ($insertResult['error'] ?? 'Unknown error'));
                throw new Exception('Failed to save selfie record');
            }
            
            $selfieId = $insertResult['id'];
            error_log("Created selfie record with ID: $selfieId");
            
            try {
                // Perform face verification
                $verificationResult = $this->performFaceVerification(
                    $selfieFilePath,
                    $idDocumentPath
                );
                
                error_log("Face verification result: " . json_encode($verificationResult));
                
                // Update selfie record with verification result
                $selfieCollection->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($selfieId)],
                    [
                        '$set' => [
                            'status' => $verificationResult['success'] ? 'verified' : 'failed',
                            'verificationResult' => $verificationResult
                        ]
                    ]
                );
                
                // Update document record with selfie URL and verification results
                $relativeUrl = str_replace(__DIR__ . '/../', '/', $selfieFilePath);
                
                $updateData = [
                    'selfieImageUrl' => $relativeUrl,
                    'status' => $verificationResult['success'] ? 'approved' : 'pending_review',
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ];
                
                // Add metadata if available
                if (isset($verificationResult['faceDetected']) && $verificationResult['faceDetected']) {
                    $updateData['metadata.faceDetectionScore'] = $verificationResult['faceDetectionScore'] ?? 0;
                    $updateData['metadata.livenessScore'] = $verificationResult['livenessScore'] ?? 0;
                }
                
                if (isset($verificationResult['similarity'])) {
                    $updateData['similarityScore'] = $verificationResult['similarity'];
                }
                
                $documentCollection->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($documentId)],
                    [
                        '$set' => $updateData
                    ]
                );
                
                // Update user verification status
                $userCollection = $this->db->getCollection('users');
                $userCollection->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($userId)],
                    [
                        '$set' => [
                            'verification.faceVerification' => [
                                'status' => $verificationResult['success'] ? 'verified' : 'failed',
                                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                                'selfieId' => new MongoDB\BSON\ObjectId($selfieId),
                                'similarity' => $verificationResult['similarity'] ?? 0
                            ]
                        ]
                    ]
                );
                
                return [
                    'success' => true,
                    'verification' => $verificationResult,
                    'selfieId' => $selfieId,
                    'message' => $verificationResult['success'] 
                        ? 'Face verification successful' 
                        : 'Face verification failed or needs manual review'
                ];
            } catch (Exception $e) {
                error_log("Face verification processing error: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                
                // Update selfie record to indicate error
                $selfieCollection->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($selfieId)],
                    [
                        '$set' => [
                            'status' => 'error',
                            'error' => $e->getMessage()
                        ]
                    ]
                );
                
                // Update document status to indicate error
                $documentCollection->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($documentId)],
                    [
                        '$set' => [
                            'status' => 'pending_review',
                            'verificationError' => $e->getMessage(),
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]
                    ]
                );
                
                throw $e;
            }
        } catch (Exception $e) {
            error_log('Face verification error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate selfie file
     */
    private function validateSelfieFile($file) {
        // First check if a direct file upload was provided
        if (isset($file['tmp_name']) && is_uploaded_file($file['tmp_name'])) {
            // Check file size
            if ($file['size'] > $this->config['max_size']) {
                throw new Exception('Selfie file size exceeds maximum allowed (' . ($this->config['max_size'] / 1024 / 1024) . 'MB)');
            }
            
            // Check file type
            $allowedTypes = ['image/jpeg', 'image/png'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Selfie file type not allowed. Allowed types: ' . implode(', ', $allowedTypes));
            }
            
            // Verify file is actually an image
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                throw new Exception('Invalid image file');
            }
            
            // Check minimum dimensions
            if ($imageInfo[0] < 300 || $imageInfo[1] < 300) {
                throw new Exception('Selfie image dimensions too small - minimum 300x300 pixels required');
            }
            
            // Check if image contains a face
            $this->detectFace($file['tmp_name']);
            
            return true;
        }
        
        // If not a direct upload, check if it's a path to an existing file
        if (isset($file['tmp_name']) && file_exists($file['tmp_name'])) {
            // Do the same validations for a file path
            // Check file size
            if (filesize($file['tmp_name']) > $this->config['max_size']) {
                throw new Exception('Selfie file size exceeds maximum allowed (' . ($this->config['max_size'] / 1024 / 1024) . 'MB)');
            }
            
            // Verify file is actually an image
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                throw new Exception('Invalid image file');
            }
            
            // Check minimum dimensions
            if ($imageInfo[0] < 300 || $imageInfo[1] < 300) {
                throw new Exception('Selfie image dimensions too small - minimum 300x300 pixels required');
            }
            
            // Check if image contains a face
            $this->detectFace($file['tmp_name']);
            
            return true;
        }
        
        // If we have a document ID, try to find the selfie file using that
        if (isset($file['documentId'])) {
            $documentId = $file['documentId'];
            $potentialPaths = [
                $this->config['selfie_dir'] . "selfie_" . $documentId . ".png",
                $this->config['upload_dir'] . "selfie_" . $documentId . ".png",
                $this->config['upload_dir'] . "selfie_" . $documentId . ".jpg",
                __DIR__ . '/../uploads/selfies/selfie_' . $documentId . '.png',
                __DIR__ . '/../uploads/selfies/selfie_' . $documentId . '.jpg'
            ];
            
            foreach ($potentialPaths as $path) {
                if (file_exists($path)) {
                    error_log("Found selfie file using document ID at path: " . $path);
                    $file['tmp_name'] = $path;
                    return $this->validateSelfieFile($file);
                }
            }
            
            error_log("Could not find selfie file for document ID: " . $documentId);
        }
        
        // If we get here, no valid file was found
        throw new Exception('No selfie file uploaded or upload failed');
    }
    
    /**
     * Detect face in image
     */
    private function detectFace($imagePath) {
        // If we have a face recognition client, use it
        if ($this->faceApiClient) {
            $result = $this->faceApiClient->detectFaces($imagePath);
            
            if (!$result['success']) {
                throw new Exception('Face detection failed: ' . ($result['error'] ?? 'Unknown error'));
            }
            
            if (!$result['faceDetected']) {
                throw new Exception('No face detected in the selfie. Please ensure your face is clearly visible.');
            }
            
            return true;
        }
        
        // Basic face detection using PHP GD and OpenCV if available
        if (extension_loaded('opencv')) {
            return $this->detectFaceOpenCV($imagePath);
        }
        
        // Fallback to very basic check if no API available
        if ($this->faceApiClient) {
            return $this->faceApiClient->fallbackFaceDetection($imagePath);
        } else {
            return $this->basicFaceDetection($imagePath);
        }
    }
    
    /**
     * Basic face detection using PHP GD
     * Note: This is a very basic check and not reliable for production use
     */
    private function basicFaceDetection($imagePath) {
        // Load image
        $image = imagecreatefromstring(file_get_contents($imagePath));
        
        // Convert to grayscale
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        
        // Get image dimensions
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Check for skin tone pixels in the central portion of the image
        $centerX = $width / 2;
        $centerY = $height / 2;
        $radius = min($width, $height) / 5;
        
        $skinToneFound = false;
        
        for ($x = $centerX - $radius; $x <= $centerX + $radius; $x += 5) {
            for ($y = $centerY - $radius; $y <= $centerY + $radius; $y += 5) {
                if ($x < 0 || $x >= $width || $y < 0 || $y >= $height) continue;
                
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Very basic skin tone detection
                if ($r > 60 && $g > 40 && $b > 20 && $r > $g && $r > $b) {
                    $skinToneFound = true;
                    break 2;
                }
            }
        }
        
        imagedestroy($image);
        
        if (!$skinToneFound) {
            throw new Exception('No face detected in the selfie. Please ensure your face is clearly visible');
        }
        
        return true;
    }
    
    /**
     * Face detection using OpenCV extension
     */
    private function detectFaceOpenCV($imagePath) {
        $src = cv\imread($imagePath);
        $gray = cv\cvtColor($src, cv\COLOR_BGR2GRAY);
        $face_cascade = cv\CascadeClassifier::load(__DIR__ . '/../data/haarcascade_frontalface_default.xml');
        $faces = $face_cascade->detectMultiScale($gray);
        
        if (count($faces) === 0) {
            throw new Exception('No face detected in the selfie. Please ensure your face is clearly visible');
        }
        
        return true;
    }
    
    /**
     * Generate unique filename for selfie
     */
    private function generateSelfieFilename($userId) {
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        return "selfie_{$userId}_{$timestamp}_{$random}.jpg";
    }
    
    /**
     * Perform face verification (compare selfie with ID document)
     */
    private function performFaceVerification($selfieFilePath, $idDocumentPath) {
        // If we have a face recognition client, use it
        if ($this->faceApiClient) {
            $result = $this->faceApiClient->compareFaces($selfieFilePath, $idDocumentPath);
            
            if (!$result['success']) {
                // If comparison fails, we should still allow manual verification
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Face comparison failed',
                    'method' => $result['provider'] ?? 'api',
                    'similarity' => 0,
                    'needs_review' => true
                ];
            }
            
            $similarity = $result['similarity'];
            $matchResult = $similarity >= $this->config['similarity_threshold'];
            
            return [
                'success' => $matchResult,
                'method' => $result['provider'] ?? 'api',
                'similarity' => $similarity,
                'threshold' => $this->config['similarity_threshold'],
                'message' => $matchResult ? 'Face verification successful' : 'Face verification failed',
                // Always require manual review if below certain threshold or provider has low confidence
                'needs_review' => $similarity >= 0.4 && $similarity < $this->config['similarity_threshold']
            ];
        }
        
        // Fallback to very basic comparison if no API available
        if ($this->faceApiClient) {
            $result = $this->faceApiClient->fallbackFaceComparison($selfieFilePath, $idDocumentPath);
            
            // Fallback is not reliable enough to auto-accept
            return [
                'success' => false,
                'message' => 'Face verification requires manual review',
                'method' => 'fallback',
                'similarity' => $result['similarity'] ?? 0,
                'matchConfidence' => $result['matchConfidence'] ?? 0,
                'needs_review' => true
            ];
        }
        
        // No comparison available, require manual review
        return [
            'success' => false,
            'message' => 'Face verification requires manual review',
            'method' => 'manual',
            'similarity' => 0,
            'needs_review' => true
        ];
    }
    
    /**
     * Get user's face verification status
     */
    public function getFaceVerificationStatus() {
        try {
            // Get user ID from token
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }
            
            // Get user data
            $userCollection = $this->db->getCollection('users');
            $user = $userCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
            
            if (!$user) {
                throw new Exception('User not found');
            }
            
            $verificationStatus = $user['verification']['faceVerification'] ?? [
                'status' => 'not_started',
                'timestamp' => null,
                'selfieId' => null,
                'similarity' => 0
            ];
            
            return [
                'success' => true,
                'status' => $verificationStatus['status'],
                'timestamp' => $verificationStatus['timestamp'],
                'similarity' => $verificationStatus['similarity'],
                'verified' => $verificationStatus['status'] === 'verified'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Admin method to manually review and approve/reject verification
     */
    public function reviewVerification($selfieId, $action, $notes = '') {
        try {
            // Verify admin privileges (would need to be implemented based on your auth system)
            if (!$this->auth->isAdmin()) {
                throw new Exception('Admin privileges required');
            }
            
            // Validate action
            if (!in_array($action, ['approve', 'reject'])) {
                throw new Exception('Invalid action. Must be "approve" or "reject"');
            }
            
            // Get selfie record
            $selfieCollection = $this->db->getCollection('selfies');
            $selfie = $selfieCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($selfieId)]);
            
            if (!$selfie) {
                throw new Exception('Selfie record not found');
            }
            
            // Update selfie status
            $selfieCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($selfieId)],
                [
                    '$set' => [
                        'status' => $action === 'approve' ? 'verified' : 'rejected',
                        'adminReview' => [
                            'action' => $action,
                            'notes' => $notes,
                            'reviewerId' => $this->auth->getUserIdFromToken(),
                            'timestamp' => new MongoDB\BSON\UTCDateTime()
                        ]
                    ]
                ]
            );
            
            // Update user verification status
            $userCollection = $this->db->getCollection('users');
            $userCollection->updateOne(
                ['_id' => $selfie['userId']],
                [
                    '$set' => [
                        'verification.faceVerification' => [
                            'status' => $action === 'approve' ? 'verified' : 'rejected',
                            'timestamp' => new MongoDB\BSON\UTCDateTime(),
                            'selfieId' => new MongoDB\BSON\ObjectId($selfieId),
                            'adminReview' => [
                                'action' => $action,
                                'notes' => $notes,
                                'reviewerId' => $this->auth->getUserIdFromToken(),
                                'timestamp' => new MongoDB\BSON\UTCDateTime()
                            ]
                        ]
                    ]
                ]
            );
            
            return [
                'success' => true,
                'action' => $action,
                'message' => 'Verification ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully'
            ];
            
        } catch (Exception $e) {
            error_log('Review verification error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get pending verifications for admin review
     */
    public function getPendingVerifications($limit = 20, $offset = 0) {
        try {
            // Verify admin privileges
            if (!$this->auth->isAdmin()) {
                throw new Exception('Admin privileges required');
            }
            
            // Get selfies that need review
            $selfieCollection = $this->db->getCollection('selfies');
            $pipeline = [
                [
                    '$match' => [
                        '$or' => [
                            ['status' => 'pending'],
                            ['verificationResult.needs_review' => true]
                        ]
                    ]
                ],
                [
                    '$lookup' => [
                        'from' => 'users',
                        'localField' => 'userId',
                        'foreignField' => '_id',
                        'as' => 'user'
                    ]
                ],
                [
                    '$lookup' => [
                        'from' => 'documents',
                        'localField' => 'idDocumentId',
                        'foreignField' => '_id',
                        'as' => 'document'
                    ]
                ],
                [
                    '$unwind' => '$user'
                ],
                [
                    '$unwind' => '$document'
                ],
                [
                    '$project' => [
                        '_id' => 1,
                        'userId' => 1,
                        'filename' => 1,
                        'status' => 1,
                        'timestamp' => 1,
                        'verificationResult' => 1,
                        'user.displayName' => 1,
                        'user.email' => 1,
                        'document.type' => 1,
                        'document.filename' => 1
                    ]
                ],
                [
                    '$sort' => ['timestamp' => -1]
                ],
                [
                    '$skip' => (int)$offset
                ],
                [
                    '$limit' => (int)$limit
                ]
            ];
            
            $pendingVerifications = $selfieCollection->aggregate($pipeline);
            
            // Get total count for pagination
            $countPipeline = [
                [
                    '$match' => [
                        '$or' => [
                            ['status' => 'pending'],
                            ['verificationResult.needs_review' => true]
                        ]
                    ]
                ],
                [
                    '$count' => 'total'
                ]
            ];
            
            $countResult = $selfieCollection->aggregate($countPipeline);
            $totalCount = !empty($countResult) ? $countResult[0]['total'] : 0;
            
            return [
                'success' => true,
                'verifications' => $pendingVerifications,
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset
            ];
            
        } catch (Exception $e) {
            error_log('Get pending verifications error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get verification details including images for admin review
     */
    public function getVerificationDetails($selfieId) {
        try {
            // Verify admin privileges
            if (!$this->auth->isAdmin()) {
                throw new Exception('Admin privileges required');
            }
            
            // Get selfie record
            $selfieCollection = $this->db->getCollection('selfies');
            $selfie = $selfieCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($selfieId)]);
            
            if (!$selfie) {
                throw new Exception('Selfie record not found');
            }
            
            // Get user data
            $userCollection = $this->db->getCollection('users');
            $user = $userCollection->findOne(['_id' => $selfie['userId']]);
            
            if (!$user) {
                throw new Exception('User record not found');
            }
            
            // Get document data
            $documentCollection = $this->db->getCollection('documents');
            $document = $documentCollection->findOne(['_id' => $selfie['idDocumentId']]);
            
            if (!$document) {
                throw new Exception('Document record not found');
            }
            
            // Prepare file paths
            $selfieFilePath = $this->config['upload_dir'] . $selfie['filename'];
            $documentFilePath = __DIR__ . '/../uploads/documents/' . $document['filename'];
            
            // Check if files exist
            if (!file_exists($selfieFilePath)) {
                throw new Exception('Selfie file not found');
            }
            
            if (!file_exists($documentFilePath)) {
                throw new Exception('Document file not found');
            }
            
            // Encode images as base64 for response
            $selfieBase64 = base64_encode(file_get_contents($selfieFilePath));
            $documentBase64 = base64_encode(file_get_contents($documentFilePath));
            
            return [
                'success' => true,
                'verification' => [
                    'id' => (string)$selfie['_id'],
                    'status' => $selfie['status'],
                    'timestamp' => $selfie['timestamp'],
                    'verificationResult' => $selfie['verificationResult'],
                    'selfieImage' => $selfieBase64,
                    'documentImage' => $documentBase64,
                    'user' => [
                        'id' => (string)$user['_id'],
                        'displayName' => $user['displayName'] ?? 'Unknown',
                        'email' => $user['email']
                    ],
                    'document' => [
                        'id' => (string)$document['_id'],
                        'type' => $document['type'],
                        'uploadDate' => $document['uploadDate']
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Get verification details error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
