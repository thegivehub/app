<?php
// lib/DocumentUploader.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Auth.php';

/**
 * Centralized file upload handler for all application resources
 * Handles campaign images, profile images, verification documents, and selfies
 */
class DocumentUploader {
    private $db;
    private $auth;
    private $config;
    private $collection;
    private $resourceType;

    /**
     * Initialize the document uploader
     * @param Auth $auth Authentication instance (optional)
     * @param mixed $collection MongoDB collection for storing metadata (optional)
     * @param string $resourceType Type of resource being uploaded (campaign, profile, document, selfie)
     */
    public function __construct($auth = null, $collection = null, $resourceType = 'document') {
        $this->db = new Database();
        $this->auth = $auth ?: new Auth();
        $this->collection = $collection;
        $this->resourceType = $resourceType;
        
        // Base upload directories for different resource types
        $this->config = [
            'base_dir' => __DIR__ . '/../uploads/',
            'document_dir' => __DIR__ . '/../uploads/documents/',
            'selfie_dir' => __DIR__ . '/../uploads/selfies/',
            'campaign_dir' => __DIR__ . '/../uploads/campaigns/',
            'profile_dir' => __DIR__ . '/../uploads/profiles/',
            'allowed_types' => [
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'application/pdf' => 'pdf',
                'image/tiff' => 'tiff',
                'image/bmp' => 'bmp',
                'image/svg+xml' => 'svg'
            ],
            'max_size' => 10 * 1024 * 1024, // 10MB
            'min_size' => 5 * 1024, // 5KB
        ];
        
        // Ensure all upload directories exist
        $this->ensureDirectoriesExist();
    }

    /**
     * Create upload directories if they don't exist
     */
    private function ensureDirectoriesExist() {
        $directories = [
            $this->config['document_dir'],
            $this->config['selfie_dir'],
            $this->config['campaign_dir'],
            $this->config['profile_dir']
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Get upload directory based on resource type and ID
     * 
     * @param string $resourceType Type of resource (campaign, profile, document, selfie)
     * @param string $resourceId ID of the resource (campaign ID, user ID)
     * @return string Path to the upload directory
     */
    private function getUploadDirectory($resourceType, $resourceId = null) {
        $baseDir = $this->config['base_dir'];
        
        switch ($resourceType) {
            case 'campaign':
                $dir = $this->config['campaign_dir'];
                // If campaign ID is provided, create a subdirectory for it
                if ($resourceId) {
                    $dir .= $resourceId . '/';
                }
                break;
            case 'profile':
                $dir = $this->config['profile_dir'];
                break;
            case 'selfie':
                $dir = $this->config['selfie_dir'];
                break;
            case 'document':
            default:
                $dir = $this->config['document_dir'];
                break;
        }
        
        // Ensure directory exists
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return $dir;
    }

    /**
     * Validate uploaded file meets requirements
     * 
     * @param array $file File data from $_FILES
     * @param array $allowedTypes Optional array of allowed MIME types
     * @param int $maxSize Optional maximum file size in bytes
     * @param int $minSize Optional minimum file size in bytes
     * @throws Exception if validation fails
     */
    protected function validateFile($file, $allowedTypes = null, $maxSize = null, $minSize = null) {
        // Use specified values or defaults from config
        $allowedTypes = $allowedTypes ?: array_keys($this->config['allowed_types']);
        $maxSize = $maxSize ?: $this->config['max_size'];
        $minSize = $minSize ?: $this->config['min_size'];
        
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new Exception('Invalid file parameter');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('File too large');
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('No file uploaded');
            default:
                throw new Exception('Unknown upload error');
        }

        if ($file['size'] > $maxSize) {
            throw new Exception('File too large - maximum size is ' . ($maxSize / 1024 / 1024) . 'MB');
        }

        if ($file['size'] < $minSize) {
            throw new Exception('File too small - minimum size is ' . ($minSize / 1024) . 'KB');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('Invalid file type - allowed types: ' . implode(', ', $allowedTypes));
        }
        
        // Additional image validation for image files
        if (strpos($mimeType, 'image/') === 0 && $mimeType !== 'image/svg+xml') {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                throw new Exception('Invalid image file');
            }
            
            // Check minimum dimensions for images (300x300 pixels)
            if ($imageInfo[0] < 300 || $imageInfo[1] < 300) {
                throw new Exception('Image dimensions too small - minimum 300x300 pixels required');
            }
        }
    }

    /**
     * Process a base64 encoded image and save it to the filesystem
     * 
     * @param string $base64Image Base64 encoded image data
     * @param string $resourceType Type of resource (campaign, profile, document)
     * @param string $resourceId ID of the resource (campaign ID, user ID)
     * @return array Result with file path and URL
     */
    public function processBase64Image($base64Image, $resourceType, $resourceId = null) {
        try {
            // Trim any whitespace
            $base64Image = trim($base64Image);
            
            // Debug info
            error_log("Processing base64 image for {$resourceType}. Data begins with: " . substr($base64Image, 0, 50));
            
            // Extract the MIME type and decode the image
            if (preg_match('/^data:image\/(\w+|\w+\+\w+);base64,/', $base64Image, $matches)) {
                $imageType = $matches[1];
                // Handle special cases for image types
                if ($imageType === 'svg+xml') {
                    $imageType = 'svg';
                }
                
                $base64Data = substr($base64Image, strpos($base64Image, ',') + 1);
                
                // Remove any whitespace/line breaks which could cause decoding issues
                $base64Data = preg_replace('/\s+/', '', $base64Data);
                
                $decodedImage = base64_decode($base64Data, true);
                
                if ($decodedImage === false) {
                    throw new Exception('Failed to decode base64 image data - invalid encoding');
                }
                
                // Verify we have actual image data
                if (strlen($decodedImage) < 100) {
                    throw new Exception('Decoded image data too small to be valid (' . strlen($decodedImage) . ' bytes)');
                }
                
                // Create temp file for validation
                $tempFile = tempnam(sys_get_temp_dir(), 'base64_img_');
                file_put_contents($tempFile, $decodedImage);
                
                // Verify the file is actually an image by checking its signature
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detectedType = $finfo->file($tempFile);
                
                // SVG files might be detected as text/plain or application/xml, so handle them specially
                if ($imageType === 'svg' && ($detectedType === 'text/plain' || $detectedType === 'application/xml' || $detectedType === 'text/xml')) {
                    // Additional validation - check if it contains SVG tags
                    $fileContent = file_get_contents($tempFile);
                    if (stripos($fileContent, '&lt;svg') === false && stripos($fileContent, '<svg') === false) {
                        unlink($tempFile); // Clean up temp file
                        throw new Exception('Invalid SVG file (no SVG tag found)');
                    }
                    // Override detected type for SVG files
                    $detectedType = 'image/svg+xml';
                }
                
                if (strpos($detectedType, 'image/') !== 0) {
                    unlink($tempFile); // Clean up temp file
                    throw new Exception('Decoded data is not a valid image (detected: ' . $detectedType . ')');
                }
                
                // Create a mock file array for validation
                $mockFile = [
                    'tmp_name' => $tempFile,
                    'size' => filesize($tempFile),
                    'type' => 'image/' . $imageType,
                    'error' => UPLOAD_ERR_OK
                ];
                
                try {
                    // Validate the file but catch validation exceptions
                    $this->validateFile($mockFile);
                } catch (Exception $e) {
                    error_log("Image validation failed but continuing: " . $e->getMessage());
                    // Continue despite validation errors - the image is real but may not meet size requirements
                }
                
                // Generate filename and save the file
                $userId = $this->auth->getUserIdFromToken();
                $uuid = bin2hex(random_bytes(8));
                $filename = "{$resourceType}_{$uuid}.{$imageType}";
                
                // Get appropriate directory
                $uploadDir = $this->getUploadDirectory($resourceType, $resourceId);
                $filePath = $uploadDir . $filename;
                
                // Save the file
                if (!file_put_contents($filePath, $decodedImage)) {
                    throw new Exception('Failed to save image file');
                }
                
                // Clean up temp file
                unlink($tempFile);
                
                // Generate relative URL path
                $urlPath = str_replace(__DIR__ . '/../', '/', $filePath);
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $filePath,
                    'url' => $urlPath,
                ];
            } else {
                // Try to handle URLs that might have been misidentified as base64
                if (filter_var($base64Image, FILTER_VALIDATE_URL)) {
                    error_log("Found URL instead of base64 data: " . $base64Image);
                    return [
                        'success' => true,
                        'url' => $base64Image, // Just return the existing URL
                        'message' => 'Kept existing URL'
                    ];
                }
                
                throw new Exception('Invalid base64 image format (missing data:image/xxx;base64, prefix)');
            }
        } catch (Exception $e) {
            error_log("Base64 image processing error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate a unique filename for the uploaded file
     * 
     * @param array $file File data from $_FILES
     * @param string $userId User ID
     * @param string $resourceType Type of resource
     * @param string $resourceId ID of the resource
     * @return string Generated filename
     */
    private function generateUniqueFilename($file, $userId, $resourceType, $resourceId = null) {
        $mimeType = $file['type'];
        $extension = isset($this->config['allowed_types'][$mimeType]) ? 
                     $this->config['allowed_types'][$mimeType] : 'bin';
        
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        
        // Format: resourceType_userId_resourceId_timestamp_random.extension
        $filename = "{$resourceType}_{$userId}_";
        
        if ($resourceId) {
            $filename .= "{$resourceId}_";
        }
        
        $filename .= "{$timestamp}_{$random}.{$extension}";
        
        return $filename;
    }

    /**
     * Upload a file and save it to the filesystem
     * 
     * @param array $file File data from $_FILES
     * @param string $resourceType Type of resource (campaign, profile, document, selfie)
     * @param string $resourceId ID of the resource (campaign ID, document ID)
     * @param array $metadata Additional metadata to store with the file
     * @return array Upload result with file information
     */
    public function upload($file, $resourceType = null, $resourceId = null, $metadata = []) {
        try {
            // Use provided resource type or default from constructor
            $resourceType = $resourceType ?: $this->resourceType;
            
            // Get user ID from token
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }
            
            // Validate file
            $this->validateFile($file);
            
            // Generate unique filename
            $filename = $this->generateUniqueFilename($file, $userId, $resourceType, $resourceId);
            
            // Get upload directory and ensure it exists
            $uploadDir = $this->getUploadDirectory($resourceType, $resourceId);
            $filePath = $uploadDir . $filename;
            
            // Save file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }
            
            // Generate relative URL path
            $urlPath = str_replace(__DIR__ . '/../', '/', $filePath);
            
            // Create record with file information
            $fileRecord = [
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'resourceType' => $resourceType,
                'resourceId' => $resourceId,
                'filename' => $filename,
                'originalName' => $file['name'],
                'mimeType' => $file['type'],
                'size' => $file['size'],
                'filePath' => $filePath,
                'url' => $urlPath,
                'uploadDate' => new MongoDB\BSON\UTCDateTime(),
            ];
            
            // Merge additional metadata if provided
            if (!empty($metadata)) {
                $fileRecord = array_merge($fileRecord, $metadata);
            }
            
            // Save metadata to database if collection is provided
            if ($this->collection) {
                $result = $this->collection->insertOne($fileRecord);
                
                if (!$result['success']) {
                    throw new Exception('Failed to save file metadata');
                }
                
                $fileRecord['_id'] = $result['id'];
            }
            
            return [
                'success' => true,
                'file' => $fileRecord
            ];
            
        } catch (Exception $e) {
            error_log('File upload error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle document file upload (for ID verification)
     * 
     * @param array $file File data from $_FILES
     * @param string $type Document type (id_card, passport, etc.)
     * @param string $description Optional description
     * @return array Upload result
     */
    public function uploadDocument($file, $type, $description = '') {
        try {
            // Get user ID from token
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }

            // Validate document type against allowed enum values
            $allowedTypes = ["passport", "drivers_license", "national_id", "residence_permit"];
            if (!in_array($type, $allowedTypes)) {
                throw new Exception('Invalid document type. Allowed types: ' . implode(', ', $allowedTypes));
            }

            // Check if this is an update to an existing document
            $documentId = $_POST['documentId'] ?? null;
            $isUpdate = !empty($documentId);
            
            error_log("Document upload: " . ($isUpdate ? "Updating existing document: $documentId" : "Creating new document"));

            // Get user details from POST data
            $firstName = $_POST['firstName'] ?? null;
            $lastName = $_POST['lastName'] ?? null;
            $dateOfBirth = $_POST['dateOfBirth'] ?? null;
            $address = $_POST['address'] ?? null;
            $city = $_POST['city'] ?? null;
            $state = $_POST['state'] ?? null;
            $postalCode = $_POST['postalCode'] ?? null;
            $country = $_POST['country'] ?? null;
            $documentNumber = $_POST['documentNumber'] ?? null;
            $documentExpiry = $_POST['documentExpiry'] ?? null;

            // Only validate personal fields if this is a new document (not an update)
            if (!$isUpdate) {
                error_log("New document creation - validating personal information fields");
                // Validate required fields for new documents
                $requiredFields = [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'dateOfBirth' => $dateOfBirth,
                    'address' => $address,
                    'city' => $city,
                    'state' => $state,
                    'postalCode' => $postalCode,
                    'country' => $country
                ];
                
                foreach ($requiredFields as $field => $value) {
                    if (!$value) {
                        error_log("Missing required field for new document: {$field}");
                        throw new Exception("Missing required field: {$field}");
                    }
                }
            } else {
                error_log("Document update - skipping personal information validation");
            }
            
            // Validate document-specific fields regardless of whether it's an update
            if (!$documentNumber) {
                error_log("Missing document number");
                throw new Exception("Missing required field: documentNumber");
            }
            
            if (!$documentExpiry) {
                error_log("Missing document expiry date");
                throw new Exception("Missing required field: documentExpiry");
            }
            
            // Validate file
            $this->validateFile($file);
            
            // Generate unique filename
            $filename = $this->generateUniqueFilename($file, $userId, 'document');
            
            // Save file
            $filePath = $this->config['document_dir'] . $filename;
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }
            
            // Generate relative URL path
            $urlPath = '/uploads/documents/' . $filename;
            
            // Get database collection
            $documentCollection = $this->db->getCollection('documents');
            
            if ($isUpdate) {
                // First verify the document ID exists and belongs to this user
                $existingDocument = $documentCollection->findOne([
                    '_id' => new MongoDB\BSON\ObjectId($documentId),
                    'userId' => new MongoDB\BSON\ObjectId($userId)
                ]);
                
                if (!$existingDocument) {
                    error_log("Document not found or doesn't belong to user. ID: $documentId, User: $userId");
                    throw new Exception('Document not found or does not belong to current user');
                }
                
                error_log("Found existing document, updating with document image and fields");
                
                // Update only the document-specific fields
                $result = $documentCollection->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($documentId)],
                    [
                        '$set' => [
                            'documentType' => $type,
                            'documentNumber' => $documentNumber,
                            'documentExpiry' => new MongoDB\BSON\UTCDateTime(strtotime($documentExpiry) * 1000),
                            'documentImageUrl' => $urlPath,
                            'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                            'status' => 'pending'
                        ]
                    ]
                );
                
                if (!$result['success']) {
                    error_log("Failed to update document metadata. Error: " . ($result['error'] ?? 'Unknown error'));
                    throw new Exception('Failed to update document metadata: ' . ($result['error'] ?? 'Unknown error'));
                }
                
                return [
                    'success' => true,
                    'documentId' => $documentId,
                    'filename' => $filename,
                    'type' => $type,
                    'url' => $urlPath,
                    'status' => 'pending',
                    'message' => 'Document updated successfully and pending verification'
                ];
            } else {
                // Create document record matching schema requirements
                $document = [
                    'userId' => new MongoDB\BSON\ObjectId($userId),
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'dateOfBirth' => new MongoDB\BSON\UTCDateTime(strtotime($dateOfBirth) * 1000),
                    'address' => $address,
                    'city' => $city,
                    'state' => $state,
                    'postalCode' => $postalCode,
                    'country' => $country,
                    'documentType' => $type,
                    'documentNumber' => $documentNumber,
                    'documentExpiry' => new MongoDB\BSON\UTCDateTime(strtotime($documentExpiry) * 1000),
                    'documentImageUrl' => $urlPath,
                    'selfieImageUrl' => null,
                    'similarityScore' => 0,
                    'status' => 'pending',
                    'verificationAttempts' => 0,
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                    'ipAddress' => $_SERVER['REMOTE_ADDR'],
                    'userAgent' => $_SERVER['HTTP_USER_AGENT'],
                    'metadata' => [
                        'documentAuthenticityScore' => 0,
                        'documentQualityScore' => 0,
                        'faceDetectionScore' => 0,
                        'livenessScore' => 0
                    ]
                ];
                
                // Log document structure before insertion
                error_log("Attempting to insert document with structure: " . json_encode($document));
                
                // Save document metadata to database
                $result = $documentCollection->insertOne($document);
                
                if (!$result['success']) {
                    error_log("Failed to save document metadata. Error: " . ($result['error'] ?? 'Unknown error'));
                    throw new Exception('Failed to save document metadata: ' . ($result['error'] ?? 'Unknown error'));
                }
                
                // Update user verification status
                $this->updateUserVerificationStatus($userId, $type, 'pending');
                
                return [
                    'success' => true,
                    'documentId' => $result['id'],
                    'filename' => $filename,
                    'type' => $type,
                    'url' => $urlPath,
                    'status' => 'pending',
                    'message' => 'Document uploaded successfully and pending verification'
                ];
            }
            
        } catch (Exception $e) {
            error_log('Document upload error in uploadDocument: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Upload a profile image for a user
     * 
     * @param array $file File data from $_FILES
     * @return array Upload result
     */
    public function uploadProfileImage($file) {
        try {
            // Get user ID from token
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }
            
            // Only allow image files for profile pictures
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            
            // Validate file
            $this->validateFile($file, $allowedTypes);
            
            // Generate unique filename
            $filename = $this->generateUniqueFilename($file, $userId, 'profile');
            
            // Save file
            $filePath = $this->config['profile_dir'] . $filename;
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }
            
            // Generate relative URL path
            $urlPath = '/uploads/profiles/' . $filename;
            
            // Update user profile with new image URL
            $userCollection = $this->db->getCollection('users');
            $result = $userCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                [
                    '$set' => [
                        'profile.avatar' => $urlPath,
                        'updated' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            if (!$result['success']) {
                throw new Exception('Failed to update user profile with new image');
            }
            
            return [
                'success' => true,
                'url' => $urlPath,
                'message' => 'Profile image uploaded successfully'
            ];
            
        } catch (Exception $e) {
            error_log('Profile image upload error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Upload a campaign image
     * 
     * @param array $file File data from $_FILES
     * @param string $campaignId Campaign ID
     * @param string $imageType Type of campaign image (main, gallery, etc.)
     * @return array Upload result
     */
    public function uploadCampaignImage($file, $campaignId, $imageType = 'main') {
        try {
            // Get user ID from token
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }
            
            // Only allow image files for campaign images
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            
            // Validate file
            $this->validateFile($file, $allowedTypes);
            
            // Generate unique filename
            $filename = $this->generateUniqueFilename($file, $userId, 'campaign', $campaignId);
            
            // Ensure campaign directory exists
            $campaignDir = $this->config['campaign_dir'] . $campaignId . '/';
            if (!is_dir($campaignDir)) {
                mkdir($campaignDir, 0755, true);
            }
            
            // Save file
            $filePath = $campaignDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }
            
            // Generate relative URL path
            $urlPath = '/uploads/campaigns/' . $campaignId . '/' . $filename;
            
            // Create file record
            $fileRecord = [
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
                'filename' => $filename,
                'originalName' => $file['name'],
                'type' => $imageType,
                'mimeType' => $file['type'],
                'size' => $file['size'],
                'url' => $urlPath,
                'uploadDate' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Update campaign with new image URL
            $campaignCollection = $this->db->getCollection('campaigns');
            
            // Different update based on image type
            if ($imageType === 'main') {
                // Update main image
                $result = $campaignCollection->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($campaignId)],
                    [
                        '$set' => [
                            'imageUrl' => $urlPath,
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]
                    ]
                );
            } else {
                // Add to gallery
                $result = $campaignCollection->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($campaignId)],
                    [
                        '$push' => [
                            'gallery' => [
                                'url' => $urlPath,
                                'caption' => $file['name'],
                                'uploadedAt' => new MongoDB\BSON\UTCDateTime()
                            ]
                        ],
                        '$set' => [
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]
                    ]
                );
            }
            
            if (!$result['success']) {
                throw new Exception('Failed to update campaign with new image');
            }
            
            return [
                'success' => true,
                'url' => $urlPath,
                'type' => $imageType,
                'message' => 'Campaign image uploaded successfully'
            ];
            
        } catch (Exception $e) {
            error_log('Campaign image upload error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle selfie file upload and update document record
     * 
     * @param array $file Uploaded selfie file (from $_FILES)
     * @param string $documentId Associated document ID
     * @return array Upload result
     */
    public function handleSelfieUpload($file, $documentId = null) {
        try {
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }

            if (!$documentId && isset($_POST['documentId'])) {
                $documentId = $_POST['documentId'];
            }
            
            if (!$documentId) {
                throw new Exception('Document ID is required to upload selfie');
            }
            
            // Only allow image files for selfies
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            
            // Validate file
            $this->validateFile($file, $allowedTypes);

            // Generate unique filename
            $filename = $this->generateUniqueFilename($file, $userId, 'selfie');
            $filePath = $this->config['selfie_dir'] . $filename;

            // Save file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to save selfie file');
            }
            
            // Generate relative URL path
            $urlPath = '/uploads/selfies/' . $filename;

            // Log selfie upload details
            error_log("Selfie uploaded successfully: " . $urlPath);
            error_log("Updating document ID: " . $documentId);

            // Get document record to be updated
            $documentCollection = $this->db->getCollection('documents');
            $document = $documentCollection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($documentId),
                'userId' => new MongoDB\BSON\ObjectId($userId)
            ]);
            
            if (!$document) {
                throw new Exception('Document not found or does not belong to the current user');
            }
            
            // Update document with selfie URL
            $result = $documentCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($documentId)],
                [
                    '$set' => [
                        'selfieImageUrl' => $urlPath,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );

            if (!$result['success']) {
                throw new Exception('Failed to update document with selfie URL');
            }
            
            // If document is complete, try face verification
            if (isset($document['documentImageUrl']) && !empty($document['documentImageUrl'])) {
                try {
                    // Optionally perform face verification if available
                    if (class_exists('FaceVerifier')) {
                        $faceVerifier = new FaceVerifier();
                        
                        $verificationResult = $faceVerifier->verifySelfie([
                            'tmp_name' => $filePath,
                            'name' => $filename,
                            'type' => $file['type'],
                            'size' => $file['size'],
                            'error' => $file['error']
                        ], $documentId);
                        
                        if ($verificationResult['success']) {
                            error_log("Face verification successful");
                        } else {
                            error_log("Face verification needs manual review");
                        }
                    } else {
                        error_log("FaceVerifier class not available. Skipping automatic verification.");
                    }
                } catch (Exception $e) {
                    // Don't fail upload if verification fails
                    error_log("Face verification error: " . $e->getMessage());
                }
            }

            return [
                'success' => true,
                'documentId' => $documentId,
                'url' => $urlPath,
                'message' => 'Selfie uploaded successfully'
            ];

        } catch (Exception $e) {
            error_log('Selfie upload error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update user verification status for the document type
     */
    private function updateUserVerificationStatus($userId, $type, $status) {
        $userCollection = $this->db->getCollection('users');
        
        // Map document types to verification fields
        $verificationFields = [
            'id_card' => 'idCardVerification',
            'passport' => 'passportVerification',
            'driving_license' => 'drivingLicenseVerification',
            'residence_proof' => 'residenceProofVerification',
            'selfie' => 'selfieVerification'
        ];
        
        if (!isset($verificationFields[$type])) {
            // Unknown document type, create generic verification field
            $fieldName = 'documentVerification.' . $type;
        } else {
            $fieldName = 'verification.' . $verificationFields[$type];
        }
        
        // Update user verification status
        return $userCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($userId)],
            [
                '$set' => [
                    $fieldName => [
                        'status' => $status,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                        'documentSubmitted' => true
                    ]
                ]
            ]
        );
    }
}
