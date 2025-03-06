<?php
// lib/DocumentUploader.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Auth.php';

class DocumentUploader {
    private $db;
    private $auth;
    private $config;

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
            
            // Create document record
            $document = [
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'filename' => $filename,
                'originalName' => $file['name'],
                'type' => $type,
                'description' => $description,
                'mimeType' => $file['type'],
                'size' => $file['size'],
                'status' => 'pending',
                'uploadDate' => new MongoDB\BSON\UTCDateTime(),
                'verificationHistory' => []
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
}
