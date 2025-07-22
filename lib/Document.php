<?php
// lib/Document.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/DocumentUploader.php';
require_once __DIR__ . '/NotificationService.php';

class Document {
    private $db;
    private $auth;
    private $config;
    private $documentUploader;

    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
        
        $this->config = [
            'upload_dir' => __DIR__ . '/../uploads/documents/',
            'allowed_types' => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'application/pdf' => 'pdf',
                'image/tiff' => 'tiff',
                'image/bmp' => 'bmp'
            ],
            'max_size' => 10 * 1024 * 1024, // 10MB
            'min_size' => 5 * 1024, // 5KB
        ];
        
        // Initialize the document uploader
        $documentCollection = $this->db->getCollection('documents');
        $this->documentUploader = new DocumentUploader($this->auth, $documentCollection, 'document');
        
        // Ensure upload directory exists
        if (!is_dir($this->config['upload_dir'])) {
            mkdir($this->config['upload_dir'], 0755, true);
        }
    }

    /**
     * Upload a document for verification
     * 
     * @param array $file The uploaded file from $_FILES
     * @param string $type Document type (id_card, passport, etc.)
     * @param string $description Optional description
     * @return array Response with upload status and document ID
     */
    public function uploadDocument($file, $type, $description = '') {
        try {
            // Get user ID from token
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }

            // Validate file
            $this->validateFile($file);
            
            // Generate unique filename
            $filename = $this->generateUniqueFilename($file, $userId);
            
            // Save file
            $filePath = $this->config['upload_dir'] . $filename;
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }
            
            // Generate relative URL path
            $urlPath = '/uploads/documents/' . $filename;

            // Check if this is a selfie upload
            $isSelfie = isset($_POST['type']) && $_POST['type'] === 'selfie';
            $documentId = $_POST['documentId'] ?? null;

            if ($isSelfie && $documentId) {
                // Update existing document with selfie
                $documentCollection = $this->db->getCollection('documents');
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
                    throw new Exception('Failed to update document with selfie');
                }

                return [
                    'success' => true,
                    'documentId' => $documentId,
                    'selfieUrl' => $urlPath,
                    'message' => 'Selfie uploaded successfully'
                ];
            }

            // Create new document record
            $document = [
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'firstName' => $_POST['firstName'] ?? '',
                'lastName' => $_POST['lastName'] ?? '',
                'dateOfBirth' => new MongoDB\BSON\UTCDateTime(strtotime($_POST['dateOfBirth']) * 1000),
                'address' => $_POST['address'] ?? '',
                'city' => $_POST['city'] ?? '',
                'state' => $_POST['state'] ?? '',
                'postalCode' => $_POST['postalCode'] ?? '',
                'country' => $_POST['country'] ?? '',
                'documentType' => $_POST['documentType'] ?? $type,
                'documentNumber' => $_POST['documentNumber'] ?? '',
                'documentExpiry' => new MongoDB\BSON\UTCDateTime(strtotime($_POST['documentExpiry']) * 1000),
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
            
            // Save document metadata to database
            $documentCollection = $this->db->getCollection('documents');
            $result = $documentCollection->insertOne($document);
            
            if (!$result['success']) {
                throw new Exception('Failed to save document metadata');
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
            
        } catch (Exception $e) {
            error_log('Document upload error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process a base64 encoded document image
     * 
     * @param string $base64Image Base64 encoded image data
     * @param string $type Document type (id_card, passport, etc.)
     * @param string $description Optional description
     * @return array Response with processing status and document ID
     */
    public function processBase64Document($base64Image, $type, $description = '') {
        try {
            // Get user ID from token
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }
            
            // Use the DocumentUploader to process the base64 image
            $result = $this->documentUploader->processBase64Image($base64Image, 'document');
            
            if (!$result['success']) {
                throw new Exception($result['error'] ?? 'Failed to process document image');
            }
            
            // Create document record
            $document = [
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'filename' => $result['filename'],
                'originalName' => 'Base64 Upload',
                'type' => $type,
                'description' => $description,
                'mimeType' => 'image/jpeg', // Assuming JPEG for base64 images
                'fileUrl' => $result['url'],
                'status' => 'pending',
                'uploadDate' => new MongoDB\BSON\UTCDateTime(),
                'verificationHistory' => []
            ];
            
            // Save document metadata to database
            $documentCollection = $this->db->getCollection('documents');
            $dbResult = $documentCollection->insertOne($document);
            
            if (!$dbResult['success']) {
                throw new Exception('Failed to save document metadata');
            }
            
            // Update user verification status
            $this->updateUserVerificationStatus($userId, $type, 'pending');
            
            return [
                'success' => true,
                'documentId' => $dbResult['id'],
                'filename' => $result['filename'],
                'type' => $type,
                'url' => $result['url'],
                'status' => 'pending',
                'message' => 'Document uploaded successfully and pending verification'
            ];
            
        } catch (Exception $e) {
            error_log('Base64 document processing error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check if file exists and was uploaded successfully
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No file uploaded or upload failed');
        }
        
        // Check file size
        if ($file['size'] > $this->config['max_size']) {
            throw new Exception('File size exceeds maximum allowed (' . ($this->config['max_size'] / 1024 / 1024) . 'MB)');
        }
        
        if ($file['size'] < $this->config['min_size']) {
            throw new Exception('File size is too small - must be at least ' . ($this->config['min_size'] / 1024) . 'KB');
        }
        
        // Check file type
        if (!isset($this->config['allowed_types'][$file['type']])) {
            throw new Exception('File type not allowed. Allowed types: ' . implode(', ', array_keys($this->config['allowed_types'])));
        }
        
        // Verify file is actually of the claimed type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!isset($this->config['allowed_types'][$detectedType])) {
            throw new Exception('Detected file type not allowed');
        }
        
        // Additional image validation for image files
        if (strpos($detectedType, 'image/') === 0) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                throw new Exception('Invalid image file');
            }
            
            // Check minimum dimensions for images
            if ($imageInfo[0] < 300 || $imageInfo[1] < 300) {
                throw new Exception('Image dimensions too small - minimum 300x300 pixels required');
            }
        }
        
        return true;
    }
    
    /**
     * Generate a unique filename for the uploaded document
     */
    private function generateUniqueFilename($file, $userId) {
        $extension = $this->config['allowed_types'][$file['type']];
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        
        return "doc_{$userId}_{$timestamp}_{$random}.{$extension}";
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
    
    /**
     * Get user documents
     * 
     * @param string $userId Optional user ID (defaults to current authenticated user)
     * @param string $type Optional document type filter
     * @return array List of user documents
     */
    public function getUserDocuments($userId = null, $type = null) {
        try {
            // If no userId provided, get from token
            if (!$userId) {
                $userId = $this->auth->getUserIdFromToken();
                if (!$userId) {
                    throw new Exception('Authentication required');
                }
            }
            
            // Build query
            $query = ['userId' => new MongoDB\BSON\ObjectId($userId)];
            
            if ($type) {
                $query['type'] = $type;
            }
            
            // Get documents
            $documentCollection = $this->db->getCollection('documents');
            $documents = $documentCollection->find($query, [
                'sort' => ['uploadDate' => -1]
            ]);
            
            return [
                'success' => true,
                'documents' => $documents
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get document details
     * 
     * @param string $documentId The document ID
     * @return array Document details
     */
    public function getDocumentDetails($documentId) {
        try {
            $documentCollection = $this->db->getCollection('documents');
            $document = $documentCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($documentId)]);
            
            if (!$document) {
                throw new Exception('Document not found');
            }
            
            // Check if user has access to this document
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId || $document['userId'] != $userId) {
                // Allow admin access for verification
                $user = $this->auth->getCurrentUser();
                if (!$user || !in_array('admin', $user['roles'] ?? [])) {
                    throw new Exception('Access denied');
                }
            }
            
            return [
                'success' => true,
                'document' => $document
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify a document (admin/verifier only)
     * 
     * @param string $documentId The document ID
     * @param string $status Verification status (approved, rejected)
     * @param string $notes Optional verification notes
     * @return array Verification result
     */
    public function verifyDocument($documentId, $status, $notes = '') {
        try {
            // Check if user is an admin or verifier
            $user = $this->auth->getCurrentUser();
            if (!$user || !in_array('admin', $user['roles'] ?? []) && !in_array('verifier', $user['roles'] ?? [])) {
                throw new Exception('Permission denied - only admins and verifiers can verify documents');
            }
            
            // Get document
            $documentCollection = $this->db->getCollection('documents');
            $document = $documentCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($documentId)]);
            
            if (!$document) {
                throw new Exception('Document not found');
            }
            
            // Update document status
            $verificationEntry = [
                'status' => $status,
                'verifiedBy' => $user['_id'],
                'verifierName' => $user['displayName'] ?? 'Administrator',
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'notes' => $notes
            ];
            
            $result = $documentCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($documentId)],
                [
                    '$set' => [
                        'status' => $status,
                        'lastVerifiedAt' => new MongoDB\BSON\UTCDateTime()
                    ],
                    '$push' => [
                        'verificationHistory' => $verificationEntry
                    ]
                ]
            );
            
            if (!$result['success']) {
                throw new Exception('Failed to update document status');
            }
            
            // Update user verification status
            $this->updateUserVerificationStatus(
                $document['userId'],
                $document['type'],
                $status
            );

            // Dispatch notification about verification result
            try {
                $notifier = new NotificationService();
                $subject = 'Document Verification ' . ucfirst($status);
                $message = "Your {$document['type']} document has been {$status}.";
                $emails = [$document['userEmail'] ?? ''];
                $phones = [$document['userPhone'] ?? ''];
                $notifier->send($subject, $message, array_filter($emails), array_filter($phones));
            } catch (Exception $e) {
                error_log('Notification error: ' . $e->getMessage());
            }
            
            return [
                'success' => true,
                'status' => $status,
                'message' => 'Document verification updated successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete a document
     * 
     * @param string $documentId The document ID
     * @return array Deletion result
     */
    public function deleteDocument($documentId) {
        try {
            // Get document
            $documentCollection = $this->db->getCollection('documents');
            $document = $documentCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($documentId)]);
            
            if (!$document) {
                throw new Exception('Document not found');
            }
            
            // Check if user has access to this document
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId || $document['userId'] != $userId) {
                // Allow admin deletion
                $user = $this->auth->getCurrentUser();
                if (!$user || !in_array('admin', $user['roles'] ?? [])) {
                    throw new Exception('Access denied');
                }
            }
            
            // Delete file
            $filePath = $this->config['upload_dir'] . $document['filename'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Delete database record
            $result = $documentCollection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($documentId)]);
            
            if (!$result['success']) {
                throw new Exception('Failed to delete document record');
            }
            
            return [
                'success' => true,
                'message' => 'Document deleted successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create a document with personal information (without file upload)
     * 
     * @param array $data Document data including personal information
     * @return array Response with creation status and document ID
     */
    public function createDocument($data) {
        try {
            // Get user ID from token
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }
            
            // Ensure required personal information fields are present
            $requiredPersonalFields = [
                'firstName', 'lastName', 'dateOfBirth', 'address', 
                'city', 'state', 'postalCode', 'country'
            ];
            
            foreach ($requiredPersonalFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new Exception("Required field '$field' is missing or empty");
                }
            }
            
            // Ensure all required fields have values by creating a complete document object
            $now = new MongoDB\BSON\UTCDateTime();
            
            // Create document record with ALL required schema fields
            $document = [
                // Required fields according to schema
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'firstName' => $data['firstName'],
                'lastName' => $data['lastName'],
                'dateOfBirth' => new MongoDB\BSON\UTCDateTime(strtotime($data['dateOfBirth']) * 1000),
                'address' => $data['address'],
                'city' => $data['city'],
                'state' => $data['state'],
                'postalCode' => $data['postalCode'],
                'country' => $data['country'],
                'status' => $data['status'] ?? 'pending',
                'createdAt' => $now,
                'updatedAt' => $now,
                
                // Optional fields with default values
                'documentType' => $data['documentType'] ?? 'pending',
                'documentNumber' => $data['documentNumber'] ?? '',
                'documentExpiry' => isset($data['documentExpiry']) ? new MongoDB\BSON\UTCDateTime(strtotime($data['documentExpiry']) * 1000) : null,
                'documentImageUrl' => null,
                'selfieImageUrl' => null,
                'similarityScore' => null,
                'verificationAttempts' => 0,
                'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'metadata' => [
                    'documentAuthenticityScore' => null,
                    'documentQualityScore' => null,
                    'faceDetectionScore' => null,
                    'livenessScore' => null
                ]
            ];
            
            // Log what we're trying to insert
            error_log('Creating document with data: ' . json_encode($document));
            
            // Save document metadata to database
            $documentCollection = $this->db->getCollection('documents');
            $result = $documentCollection->insertOne($document);
            
            if (!$result || !isset($result['success']) || !$result['success']) {
                error_log('Insert operation failed with result: ' . json_encode($result));
                throw new Exception('Failed to create document record');
            }
            
            error_log('Document created successfully with ID: ' . $result['id']);
            
            return [
                'success' => true,
                'documentId' => $result['id'],
                'message' => 'Document created successfully'
            ];
            
        } catch (Exception $e) {
            error_log('Document creation error: ' . $e->getMessage());
            error_log('Exception trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
