<?php
/**
 * SignatureController - Handles operations related to digital signatures
 */
require_once __DIR__ . '/db.php';

class SignatureController {
    private $db;
    private $collection;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
        $this->collection = $this->db->getCollection('signatures');
    }
    
    /**
     * Save a signature to the database
     * 
     * @param string $userId User ID associated with the signature
     * @param string $signatureData Base64 encoded signature data
     * @param string $type Type of signature (consent, agreement, document, verification, other)
     * @param string|null $documentId Optional ID of the document being signed
     * @param string|null $description Description of what was signed
     * @param array $metadata Additional metadata about the signature
     * @return array Result of the operation
     */
    public function saveSignature($userId, $signatureData, $type, $documentId = null, $description = null, $metadata = []) {
        // Validate required parameters
        if (empty($userId)) {
            return ['success' => false, 'error' => 'User ID is required'];
        }
        
        if (empty($signatureData)) {
            return ['success' => false, 'error' => 'Signature data is required'];
        }
        
        $validTypes = ['consent', 'agreement', 'document', 'verification', 'other'];
        if (!in_array($type, $validTypes)) {
            return ['success' => false, 'error' => 'Invalid signature type. Must be one of: ' . implode(', ', $validTypes)];
        }
        
        // Prepare metadata with client information
        $clientMetadata = [
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        
        // Merge with provided metadata
        $metadata = array_merge($clientMetadata, $metadata);
        
        // Create signature document
        $signature = [
            'userId' => $userId,
            'signatureData' => $signatureData,
            'type' => $type,
            'createdAt' => new MongoDB\BSON\UTCDateTime(),
            'metadata' => $metadata
        ];
        
        // Add optional fields if provided
        if ($documentId) {
            $signature['documentId'] = $documentId;
        }
        
        if ($description) {
            $signature['description'] = $description;
        }
        
        // Insert into database
        $result = $this->collection->insertOne($signature);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Signature saved successfully',
                'signatureId' => $result['id']
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to save signature: ' . ($result['error'] ?? 'Unknown error')
            ];
        }
    }
    
    /**
     * Get a signature by ID
     * 
     * @param string $signatureId ID of the signature to retrieve
     * @return array|null The signature document or null if not found
     */
    public function getSignature($signatureId) {
        return $this->collection->findOne(['_id' => $signatureId]);
    }
    
    /**
     * Get all signatures for a user
     * 
     * @param string $userId User ID to get signatures for
     * @param array $options Query options (pagination, sorting, etc.)
     * @return array List of signature documents
     */
    public function getUserSignatures($userId, $options = []) {
        // Set default options
        $defaultOptions = [
            'limit' => 50,
            'page' => 1,
            'sort' => ['createdAt' => -1]
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        return $this->collection->find(['userId' => $userId], $options);
    }
    
    /**
     * Get signatures for a specific document
     * 
     * @param string $documentId Document ID to get signatures for
     * @param array $options Query options (pagination, sorting, etc.)
     * @return array List of signature documents
     */
    public function getDocumentSignatures($documentId, $options = []) {
        // Set default options
        $defaultOptions = [
            'limit' => 50,
            'page' => 1,
            'sort' => ['createdAt' => -1]
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        return $this->collection->find(['documentId' => $documentId], $options);
    }
    
    /**
     * Delete a signature by ID
     * 
     * @param string $signatureId ID of the signature to delete
     * @return array Result of the operation
     */
    public function deleteSignature($signatureId) {
        return $this->collection->deleteOne(['_id' => $signatureId]);
    }
    
    /**
     * Delete all signatures for a user
     * 
     * @param string $userId User ID to delete signatures for
     * @return array Result of the operation
     */
    public function deleteUserSignatures($userId) {
        return $this->collection->deleteMany(['userId' => $userId]);
    }
} 