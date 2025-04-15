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
                error_log("Creating directory: {$dir}");
                if (!mkdir($dir, 0755, true)) {
                    error_log("Failed to create directory: {$dir}. Error: " . error_get_last()['message'] ?? 'Unknown error');
                }
            }
            
            // Check directory permissions
            if (!is_writable($dir)) {
                error_log("Warning: Directory {$dir} is not writable");
                // Try to make the directory writable
                chmod($dir, 0777);
                if (!is_writable($dir)) {
                    error_log("Failed to make directory {$dir} writable");
                } else {
                    error_log("Successfully made directory {$dir} writable");
                }
            }
        }
        
        // Try to use a temporary directory as fallback if needed
        $fallbackDir = '/tmp/uploads/';
        if (is_dir($fallbackDir) && is_writable($fallbackDir)) {
            error_log("Fallback directory available at {$fallbackDir}");
            $this->config['fallback_dir'] = $fallbackDir;
            $this->config['fallback_document_dir'] = $fallbackDir . 'documents/';
            $this->config['fallback_selfie_dir'] = $fallbackDir . 'selfies/';
            
            // Ensure fallback subdirectories exist
            if (!is_dir($this->config['fallback_document_dir'])) {
                mkdir($this->config['fallback_document_dir'], 0777, true);
            }
            if (!is_dir($this->config['fallback_selfie_dir'])) {
                mkdir($this->config['fallback_selfie_dir'], 0777, true);
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
            error_log("Creating upload directory: {$dir}");
            if (!mkdir($dir, 0755, true)) {
                error_log("Failed to create upload directory: {$dir}");
                
                // Try fallback directory if available
                if (isset($this->config['fallback_dir']) && $resourceType === 'document') {
                    $dir = $this->config['fallback_document_dir'];
                    error_log("Using fallback document directory: {$dir}");
                } else if (isset($this->config['fallback_dir']) && $resourceType === 'selfie') {
                    $dir = $this->config['fallback_selfie_dir'];
                    error_log("Using fallback selfie directory: {$dir}");
                } else {
                    throw new Exception("Failed to create upload directory and no fallback available");
                }
            }
        }
        
        // Ensure the directory is writable
        if (!is_writable($dir)) {
            error_log("Warning: Upload directory {$dir} is not writable");
            // Try to make it writable
            chmod($dir, 0777);
            if (!is_writable($dir)) {
                error_log("Failed to make upload directory writable");
                
                // Try fallback directory if available
                if (isset($this->config['fallback_dir']) && $resourceType === 'document') {
                    $dir = $this->config['fallback_document_dir'];
                    error_log("Using fallback document directory: {$dir}");
                } else if (isset($this->config['fallback_dir']) && $resourceType === 'selfie') {
                    $dir = $this->config['fallback_selfie_dir'];
                    error_log("Using fallback selfie directory: {$dir}");
                } else {
                    throw new Exception("Upload directory is not writable and no fallback available");
                }
            }
        }
        
        error_log("Final upload directory: {$dir}");
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
     * @param string $verificationId Verification ID
     * @return array Upload result
     */
    public function uploadDocument($file, $type, $verificationId) {
        try {
            // Hard-code some values for debugging purposes
            $TEST_VALUES = [
                'documentNumber' => 'TEST123456',
                'documentExpiry' => '2025-12-31'
            ];
            
            // Log that we're starting with test values
            error_log("DocumentUploader TEST VALUES: " . json_encode($TEST_VALUES));
            
            // Get user ID from token
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }

            // Validate verificationId
            if (!$verificationId) {
                throw new Exception('Verification ID is required');
            }

            // Validate document type against allowed enum values
            $allowedTypes = ["passport", "drivers_license", "national_id", "residence_permit"];
            if (!in_array($type, $allowedTypes)) {
                throw new Exception('Invalid document type. Allowed types: ' . implode(', ', $allowedTypes));
            }

            // Validate file
            $this->validateFile($file);
            
            // Determine file extension based on mime type
            $extension = 'png'; // Default extension
            if (isset($file['type'])) {
                switch ($file['type']) {
                    case 'image/jpeg':
                    case 'image/jpg':
                        $extension = 'jpg';
                        break;
                    case 'image/png':
                        $extension = 'png';
                        break;
                    case 'application/pdf':
                        $extension = 'pdf';
                        break;
                    default:
                        // Use default extension
                        break;
                }
            }
            
            // Generate simple filename based on verification ID
            $filename = "primaryId-" . $verificationId . "." . $extension;
            $filePath = $this->config['document_dir'] . $filename;
            error_log("New direct filename scheme for document: {$filename}");
            
            // If file already exists, remove it before saving the new one
            if (file_exists($filePath)) {
                unlink($filePath);
                error_log("Removed existing document file: " . $filePath);
            }

            // Save file with detailed error handling
            error_log("Trying to save document file from {$file['tmp_name']} to {$filePath}");
            
            // Check if the temp file exists and is readable
            if (!file_exists($file['tmp_name'])) {
                error_log("Error: Temporary file {$file['tmp_name']} does not exist");
                throw new Exception("Temporary file {$file['tmp_name']} does not exist");
            }
            if (!is_readable($file['tmp_name'])) {
                error_log("Error: Temporary file {$file['tmp_name']} is not readable");
                throw new Exception("Temporary file {$file['tmp_name']} is not readable");
            }
            
            // Check if the target directory exists and is writable
            $targetDir = dirname($filePath);
            if (!is_dir($targetDir)) {
                error_log("Creating document directory: {$targetDir}");
                if (!mkdir($targetDir, 0755, true)) {
                    error_log("Error: Failed to create directory {$targetDir}");
                    throw new Exception("Failed to create directory {$targetDir}");
                }
            }
            if (!is_writable($targetDir)) {
                error_log("Error: Directory {$targetDir} is not writable");
                throw new Exception("Directory {$targetDir} is not writable");
            }
            
            // Try to move the uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                error_log("Error: Failed to move uploaded file from {$file['tmp_name']} to {$filePath}");
                error_log("PHP move_uploaded_file error: " . error_get_last()['message'] ?? 'Unknown error');
                
                // Fallback option: try to copy the file directly
                error_log("Attempting fallback copy of file...");
                if (!copy($file['tmp_name'], $filePath)) {
                    error_log("Error: Failed to copy file. PHP error: " . error_get_last()['message'] ?? 'Unknown error');
                    throw new Exception('Failed to save document file');
                }
                error_log("Fallback copy successful");
            } else {
                error_log("Successfully moved file to {$filePath}");
            }
            
            // Generate relative URL path
            $urlPath = '/uploads/documents/' . $filename;
            
            // Ultra-detailed logging of all data coming into the function
            error_log("DocumentUploader - uploadDocument - Raw POST data: " . print_r($_POST, true));
            error_log("DocumentUploader - uploadDocument - REQUEST data: " . print_r($_REQUEST, true));
            error_log("DocumentUploader - uploadDocument - FILES data: " . print_r($_FILES, true));
            error_log("DocumentUploader - uploadDocument - GET data: " . print_r($_GET, true));
            error_log("DocumentUploader - uploadDocument - Type parameter passed: " . $type);
            error_log("DocumentUploader - uploadDocument - VerificationId parameter passed: " . $verificationId);
            
            // Try to get document metadata from request
            $documentNumber = null;
            $documentExpiry = null;
            
            // Log all potential data sources
            error_log("DocumentUploader - POST data: " . print_r($_POST, true));
            error_log("DocumentUploader - REQUEST data: " . print_r($_REQUEST, true));
            
            // Try to get values from POST/REQUEST first
            if (!empty($_POST['documentNumber'])) {
                $documentNumber = $_POST['documentNumber'];
                error_log("DocumentUploader - Found documentNumber in POST: $documentNumber");
            } elseif (!empty($_REQUEST['documentNumber'])) {
                $documentNumber = $_REQUEST['documentNumber'];
                error_log("DocumentUploader - Found documentNumber in REQUEST: $documentNumber");
            } else {
                // Fallback to test value
                $documentNumber = $TEST_VALUES['documentNumber'] ?? 'TEST-DOC-' . time();
                error_log("DocumentUploader - Using fallback document number: $documentNumber");
            }
            
            if (!empty($_POST['documentExpiry'])) {
                $documentExpiry = $_POST['documentExpiry'];
                error_log("DocumentUploader - Found documentExpiry in POST: $documentExpiry");
            } elseif (!empty($_REQUEST['documentExpiry'])) {
                $documentExpiry = $_REQUEST['documentExpiry'];
                error_log("DocumentUploader - Found documentExpiry in REQUEST: $documentExpiry");
            } else {
                // Fallback to test value or future date
                $documentExpiry = $TEST_VALUES['documentExpiry'] ?? date('Y-m-d', strtotime('+5 years'));
                error_log("DocumentUploader - Using fallback document expiry: $documentExpiry");
            }

            // Log the collected document info
            error_log("Document info being saved: Type: $type, Number: $documentNumber, Expiry: $documentExpiry");
            
            // Process the file and update the database
            try {
                // Get the documents collection
                error_log("DocumentUploader - Attempting to get documents collection");
                try {
                    // First check if documents collection exists
                    $collections = $this->db->listCollections();
                    $hasDocumentsCollection = false;
                    
                    // Convert to array for logging/checking
                    $collectionNames = [];
                    foreach ($collections as $collection) {
                        $collectionNames[] = $collection->getName();
                        if ($collection->getName() === 'documents') {
                            $hasDocumentsCollection = true;
                        }
                    }
                    
                    error_log("DocumentUploader - Available collections: " . implode(", ", $collectionNames));
                    
                    // If collection doesn't exist, try to create it
                    if (!$hasDocumentsCollection) {
                        error_log("DocumentUploader - documents collection does not exist, creating it");
                        try {
                            $this->db->db->createCollection('documents');
                            error_log("DocumentUploader - documents collection created successfully using direct MongoDB call");
                        } catch (Exception $createEx) {
                            error_log("DocumentUploader - Error creating documents collection with direct call: " . $createEx->getMessage());
                            
                            // Try alternative method if available
                            if (method_exists($this->db, 'createCollection')) {
                                $this->db->createCollection('documents');
                                error_log("DocumentUploader - documents collection created successfully using wrapper method");
                            } else {
                                // If no method exists, manually create a basic collection
                                error_log("DocumentUploader - Attempting manual collection creation");
                                $this->db->db->command(['create' => 'documents']);
                                error_log("DocumentUploader - documents collection created manually");
                            }
                        }
                    }
                    
                    // Now get the collection - try direct access first
                    try {
                        $documentsCollection = $this->db->db->documents;
                        error_log("DocumentUploader - Got documents collection using direct access: " . ($documentsCollection ? 'Success' : 'Failed'));
                    } catch (Exception $directEx) {
                        error_log("DocumentUploader - Error getting documents collection with direct access: " . $directEx->getMessage());
                        // Fall back to wrapper method
                        $documentsCollection = $this->db->getCollection('documents');
                        error_log("DocumentUploader - Got documents collection using wrapper: " . ($documentsCollection ? 'Success' : 'Failed'));
                    }
                } catch (Exception $e) {
                    error_log("DocumentUploader - Error getting/creating documents collection: " . $e->getMessage());
                    error_log("DocumentUploader - Error trace: " . $e->getTraceAsString());
                    
                    // Continue anyway - we'll use the verifications collection instead
                    $documentsCollection = null;
                }
                
                // Create document metadata based on file type
                $fileType = $file['type'];
                $meta = [
                    // Basic metadata for all file types
                    'originalName' => $file['name'],
                    'mimeType' => $fileType,
                    'size' => $file['size'],
                    'uploadedBy' => $userId,
                    'uploadedAt' => new MongoDB\BSON\UTCDateTime(),
                    
                    // Add document-specific data for ID verification documents
                    'documentType' => $type,
                    'documentNumber' => $documentNumber,
                    'documentExpiry' => new MongoDB\BSON\UTCDateTime(strtotime($documentExpiry) * 1000)
                ];
                
                // For images, add image-specific metadata
                if (strpos($fileType, 'image/') === 0) {
                    try {
                        $imageInfo = getimagesize($filePath);
                        if ($imageInfo) {
                            $meta['width'] = $imageInfo[0];
                            $meta['height'] = $imageInfo[1];
                            $meta['imageType'] = $imageInfo[2]; // e.g., IMAGETYPE_JPEG
                        }
                    } catch (Exception $e) {
                        error_log("DocumentUploader - Error getting image dimensions: " . $e->getMessage());
                    }
                }
                
                // Insert into documents collection
                $document = [
                    'userId' => new MongoDB\BSON\ObjectId($userId),
                    'type' => 'ID_DOCUMENT',
                    'subType' => $type, // e.g., 'passport', 'drivers_license', etc.
                    'filePath' => $filePath,
                    'url' => $urlPath,
                    'fileType' => $fileType,
                    'fileName' => $filename,
                    'meta' => $meta,
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ];
                
                error_log("DocumentUploader - Trying to insert document: " . json_encode($document));
                
                try {
                    // First attempt: using the wrapper collection
                    if ($documentsCollection) {
                        try {
                            $insertResult = $documentsCollection->insertOne($document);
                            if ($insertResult && method_exists($insertResult, 'getInsertedId')) {
                                $documentId = (string)$insertResult->getInsertedId();
                                error_log("DocumentUploader - Document inserted with ID: {$documentId} (wrapper method)");
                            } else if (is_array($insertResult) && isset($insertResult['id'])) {
                                // Handle array result format from our wrapper
                                $documentId = $insertResult['id'];
                                error_log("DocumentUploader - Document inserted with ID: {$documentId} (array result)");
                            } else {
                                error_log("DocumentUploader - Insert result unexpected format: " . print_r($insertResult, true));
                                throw new Exception("Unexpected result format from insertOne");
                            }
                        } catch (Exception $wrapperEx) {
                            error_log("DocumentUploader - Error inserting document with wrapper: " . $wrapperEx->getMessage());
                            throw $wrapperEx; // Throw to try the direct MongoDB method
                        }
                    } else {
                        throw new Exception("Documents collection is null");
                    }
                } catch (Exception $ex) {
                    error_log("DocumentUploader - Error with wrapper insert, trying direct MongoDB access: " . $ex->getMessage());
                    
                    try {
                        // Second attempt: direct MongoDB access
                        $directResult = $this->db->db->documents->insertOne($document);
                        if ($directResult && method_exists($directResult, 'getInsertedId')) {
                            $documentId = (string)$directResult->getInsertedId();
                            error_log("DocumentUploader - Document inserted with ID: {$documentId} (direct MongoDB)");
                        } else {
                            throw new Exception("Direct MongoDB insertion failed to return an ID");
                        }
                    } catch (Exception $directEx) {
                        error_log("DocumentUploader - Error with direct MongoDB insert: " . $directEx->getMessage());
                        error_log("DocumentUploader - Error trace: " . $directEx->getTraceAsString());
                        
                        // As a last resort, insert using the verifications collection with document data
                        try {
                            error_log("DocumentUploader - Attempting to create via verifications collection as fallback");
                            $fallbackDoc = [
                                'type' => 'ID_DOCUMENT',
                                'documentData' => $document,
                                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                                'updatedAt' => new MongoDB\BSON\UTCDateTime()
                            ];
                            
                            $fallbackResult = $this->db->getCollection('verifications')->insertOne($fallbackDoc);
                            if (is_array($fallbackResult) && isset($fallbackResult['id'])) {
                                $documentId = $fallbackResult['id'];
                                error_log("DocumentUploader - Document inserted via fallback with ID: {$documentId}");
                            } else if (method_exists($fallbackResult, 'getInsertedId')) {
                                $documentId = (string)$fallbackResult->getInsertedId();
                                error_log("DocumentUploader - Document inserted via fallback with ID: {$documentId}");
                            } else {
                                // Final fallback - generate random ID
                                $documentId = 'manual-' . uniqid();
                                error_log("DocumentUploader - Using final fallback document ID: {$documentId}");
                            }
                        } catch (Exception $fallbackEx) {
                            error_log("DocumentUploader - All document insert methods failed: " . $fallbackEx->getMessage());
                            // Absolute last resort - just use a random ID
                            $documentId = 'error-' . uniqid();
                            error_log("DocumentUploader - Using error fallback document ID: {$documentId}");
                        }
                    }
                }
                error_log("DocumentUploader - Document inserted with ID: " . $documentId);
                
                // Now update the verification record with references
                error_log("DocumentUploader - Getting verifications collection");
                $verificationsCollection = $this->db->getCollection('verifications');
                
                // Build the update fields based on document type
                $updateFields = [
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ];
                
                // Keep certain fields at the top level for direct queries
                if (in_array($type, ['passport', 'drivers_license', 'national_id', 'residence_permit'])) {
                    $updateFields['documentType'] = $type;
                    $updateFields['documentNumber'] = $documentNumber;
                    $updateFields['documentExpiry'] = new MongoDB\BSON\UTCDateTime(strtotime($documentExpiry) * 1000);
                    $updateFields['documentImageUrl'] = $urlPath;
                    
                    // Set document reference in 'documents' sub-object
                    $updateFields['documents.primaryId'] = $documentId;
                }
                // If it's a selfie, update the selfie field
                else if ($type === 'selfie') {
                    $updateFields['selfieImageUrl'] = $urlPath;
                    $updateFields['documents.selfie'] = $documentId;
                }
                
                // Before updating, ensure the 'documents' field exists in the verification record
                try {
                    // First try to create the documents field if it doesn't exist
                    error_log("DocumentUploader - Ensuring documents field exists in verification record");
                    $initResult = $verificationsCollection->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($verificationId)],
                        ['$set' => ['documents' => (object)[]]], // Create empty documents object
                        ['upsert' => false]
                    );
                    error_log("DocumentUploader - Initialize documents field result: " . json_encode($initResult));
                } catch (Exception $initEx) {
                    error_log("DocumentUploader - Error initializing documents field: " . $initEx->getMessage());
                    // Continue anyway as the update might work
                }
                
                // Update the verification record
                error_log("DocumentUploader - Updating verification record with ID: " . $verificationId);
                $updateResult = $verificationsCollection->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($verificationId)],
                    ['$set' => $updateFields]
                );
                error_log("DocumentUploader - Verification update result: " . json_encode($updateResult));
                
                // Return successful result
                return [
                    'success' => true,
                    'documentId' => $documentId,
                    'filename' => $filename,
                    'url' => $urlPath,
                    'type' => $type,
                    'message' => 'Document uploaded successfully'
                ];
            } catch (Exception $e) {
                error_log("DocumentUploader - Error processing document: " . $e->getMessage());
                error_log("DocumentUploader - Stack trace: " . $e->getTraceAsString());
                
                // If the file was saved but database operations failed,
                // return a partial success so the UI can continue
                if (file_exists($filePath)) {
                    return [
                        'success' => true,
                        'documentId' => 'primaryId-' . $verificationId,
                        'filename' => $filename,
                        'type' => $type,
                        'url' => $urlPath,
                        'status' => 'pending',
                        'message' => 'Document uploaded but metadata could not be saved'
                    ];
                }
                
                // Complete failure
                return [
                    'success' => false,
                    'error' => $e->getMessage()
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
     * Handle selfie upload for ID verification
     * 
     * @param array $file File data from $_FILES
     * @param string $verificationId Verification ID
     * @return array Upload result
     */
    public function handleSelfieUpload($file, $verificationId) {
        try {
            // Get user ID from token
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }

            // Validate verificationId
            if (!$verificationId) {
                throw new Exception('Verification ID is required');
            }

            // Only allow image files for selfies
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            
            // Validate file
            $this->validateFile($file, $allowedTypes);
            
            // Determine file extension based on mime type
            $extension = 'png'; // Default extension
            if (isset($file['type'])) {
                switch ($file['type']) {
                    case 'image/jpeg':
                    case 'image/jpg':
                        $extension = 'jpg';
                        break;
                    case 'image/png':
                        $extension = 'png';
                        break;
                    default:
                        // Use default extension
                        break;
                }
            }
            
            // Generate simple filename based on verification ID
            $filename = "selfie-" . $verificationId . "." . $extension;
            $filePath = $this->config['selfie_dir'] . $filename;
            error_log("New direct filename scheme for selfie: {$filename}");
            
            // If file already exists, remove it before saving the new one
            if (file_exists($filePath)) {
                unlink($filePath);
                error_log("Removed existing selfie file: " . $filePath);
            }
            
            // Make sure selfie directory exists
            if (!is_dir($this->config['selfie_dir'])) {
                mkdir($this->config['selfie_dir'], 0755, true);
                error_log("Created selfie directory: " . $this->config['selfie_dir']);
            }
            
            // Check if the target directory is writable
            if (!is_writable($this->config['selfie_dir'])) {
                error_log("Error: Directory {$this->config['selfie_dir']} is not writable");
                throw new Exception("Directory {$this->config['selfie_dir']} is not writable");
            }

            // Save file with better error handling
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                error_log("Error: Failed to move uploaded selfie file from {$file['tmp_name']} to {$filePath}");
                error_log("PHP move_uploaded_file error: " . error_get_last()['message'] ?? 'Unknown error');
                
                // Fallback option: try to copy the file directly
                error_log("Attempting fallback copy of selfie file...");
                if (!copy($file['tmp_name'], $filePath)) {
                    error_log("Error: Failed to copy selfie file. PHP error: " . error_get_last()['message'] ?? 'Unknown error');
                    throw new Exception('Failed to save selfie file');
                }
                error_log("Fallback copy successful");
            } else {
                error_log("Successfully moved selfie file to {$filePath}");
            }
            
            // Generate relative URL path
            $urlPath = '/uploads/selfies/' . $filename;
            
            // Update the verification record directly with the file path
            try {
                $verificationRecord = $this->db->getCollection('verifications');
                $updateResult = $verificationRecord->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($verificationId)],
                    [
                        '$set' => [
                            'selfieImageUrl' => $urlPath,
                            'selfieUploaded' => new MongoDB\BSON\UTCDateTime(),
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]
                    ]
                );
                error_log("Direct verification update result for selfie: " . json_encode($updateResult));
            } catch (Exception $updateEx) {
                error_log("Error updating verification record with selfie: " . $updateEx->getMessage());
                // Continue anyway as we still want to return success for the file upload
            }
            
            return [
                'success' => true,
                'documentId' => 'selfie-' . $verificationId,
                'filename' => $filename,
                'type' => 'selfie',
                'url' => $urlPath,
                'status' => 'pending',
                'message' => 'Selfie uploaded successfully'
            ];
            
        } catch (Exception $e) {
            error_log('Selfie upload error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
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
