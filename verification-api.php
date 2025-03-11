<?php
// Endpoints for face verification API
require_once __DIR__ . '/lib/FaceVerifier.php';
require_once __DIR__ . '/lib/Auth.php';

class VerificationController {
    private $auth;
    private $faceVerifier;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->faceVerifier = new FaceVerifier();
    }
    
    /**
     * Upload document
     */
    public function uploadDocument() {
        try {
            // Verify authentication
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                return [
                    'success' => false,
                    'error' => 'Authentication required'
                ];
            }
            
            // Check if file was uploaded
            if (!isset($_FILES['documentFile']) || !is_uploaded_file($_FILES['documentFile']['tmp_name'])) {
                return [
                    'success' => false,
                    'error' => 'No document file uploaded or upload failed'
                ];
            }
            
            // Validate document type
            $documentType = $_POST['documentType'] ?? '';
            $validTypes = ['passport', 'drivers_license', 'id_card'];
            if (!in_array($documentType, $validTypes)) {
                return [
                    'success' => false,
                    'error' => 'Invalid document type'
                ];
            }
            
            // Upload document
            $db = new Database();
            $documentCollection = $db->getCollection('documents');
            
            // Generate unique filename
            $timestamp = time();
            $random = bin2hex(random_bytes(8));
            $originalName = $_FILES['documentFile']['name'];
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $filename = "doc_{$userId}_{$timestamp}_{$random}.{$extension}";
            $uploadDir = __DIR__ . '/uploads/documents/';
            
            // Ensure upload directory exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Save file
            $filePath = $uploadDir . $filename;
            if (!move_uploaded_file($_FILES['documentFile']['tmp_name'], $filePath)) {
                return [
                    'success' => false,
                    'error' => 'Failed to save document file'
                ];
            }
            
            // Create document record
            $document = [
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'type' => $documentType,
                'filename' => $filename,
                'originalName' => $originalName,
                'uploadDate' => new MongoDB\BSON\UTCDateTime(),
                'status' => 'pending',
                'verified' => false
            ];
            
            // Save document record
            $result = $documentCollection->insertOne($document);
            
            if (!$result['success']) {
                // Clean up file if database insertion fails
                @unlink($filePath);
                return [
                    'success' => false,
                    'error' => 'Failed to save document information'
                ];
            }
            
            return [
                'success' => true,
                'documentId' => (string)$result['id'],
                'message' => 'Document uploaded successfully'
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
     * Verify identity with selfie
     */
    public function verifyIdentity() {
        try {
            // Get request body
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Check if document ID was provided
            if (!isset($data['documentId']) || empty($data['documentId'])) {
                return [
                    'success' => false,
                    'error' => 'Document ID is required'
                ];
            }
            
            // Check if selfie was uploaded
            if (!isset($_FILES['selfieFile']) || !is_uploaded_file($_FILES['selfieFile']['tmp_name'])) {
                return [
                    'success' => false,
                    'error' => 'No selfie file uploaded or upload failed'
                ];
            }
            
            // Verify selfie against document
            $result = $this->faceVerifier->verifySelfie($_FILES['selfieFile'], $data['documentId']);
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Identity verification error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get verification status
     */
    public function getVerificationStatus() {
        try {
            return $this->faceVerifier->getFaceVerificationStatus();
        } catch (Exception $e) {
            error_log('Get verification status error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Handle API requests
$controller = new VerificationController();

// Map endpoint to controller method
$endpoint = $_SERVER['PATH_INFO'] ?? '';

switch ($endpoint) {
    case '/document/upload':
        echo json_encode($controller->uploadDocument());
        break;
    
    case '/user/verify-identity':
        echo json_encode($controller->verifyIdentity());
        break;
    
    case '/user/verification-status':
        echo json_encode($controller->getVerificationStatus());
        break;
    
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Endpoint not found'
        ]);
        break;
}
