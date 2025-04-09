<?php
// Endpoints for face verification API
require_once __DIR__ . '/lib/FaceVerifier.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/DocumentUploader.php';

class VerificationController {
    private $auth;
    private $faceVerifier;
    private $db;
    private $faceRecognitionAvailable = false;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->db = new Database();
        
        // Try to initialize face verifier, but make it optional
        try {
            $this->faceVerifier = new FaceVerifier();
            $this->faceRecognitionAvailable = true;
        } catch (Exception $e) {
            error_log('Face recognition not available: ' . $e->getMessage());
            $this->faceRecognitionAvailable = false;
        }
    }
    
    /**
     * Save personal information and create a document record
     */
    public function savePersonalInfo() {
        try {
            // Verify authentication
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                http_response_code(401);
                return [
                    'success' => false,
                    'error' => 'Authentication required'
                ];
            }
            
            // Get JSON body
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Invalid request data'
                ];
            }
            
            // Validate required fields
            $requiredFields = ['firstName', 'lastName', 'dateOfBirth', 'address', 'city', 'state', 'postalCode', 'country'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    http_response_code(400);
                    return [
                        'success' => false,
                        'error' => "Missing required field: {$field}"
                    ];
                }
            }
            
            // Create document record
            $documentCollection = $this->db->getCollection('documents');
            
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
                'documentImageUrl' => null,
                'selfieImageUrl' => null,
                'similarityScore' => 0,
                'status' => 'pending',
                'verificationSteps' => [
                    'personalInfo' => true,
                    'document' => false,
                    'selfie' => false,
                    'review' => false
                ],
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                'ipAddress' => $_SERVER['REMOTE_ADDR'],
                'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ];
            
            $result = $documentCollection->insertOne($document);
            
            if (!$result['success']) {
                http_response_code(500);
                return [
                    'success' => false,
                    'error' => 'Failed to save document information'
                ];
            }
            
            return [
                'success' => true,
                'documentId' => $result['id'],
                'message' => 'Personal information saved successfully'
            ];
            
        } catch (Exception $e) {
            error_log('Personal info save error: ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Upload document
     */
    public function uploadDocument() {
        try {
            // Verify authentication
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                http_response_code(401);
                return [
                    'success' => false,
                    'error' => 'Authentication required'
                ];
            }
            
            // Check if file was uploaded
            if (!isset($_FILES['document']) || !is_uploaded_file($_FILES['document']['tmp_name'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'No document file uploaded or upload failed'
                ];
            }
            
            // Get document ID from POST data
            $documentId = $_POST['documentId'] ?? null;
            if (!$documentId) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Document ID is required'
                ];
            }
            
            // Validate document type
            $documentType = $_POST['documentType'] ?? '';
            $validTypes = ['passport', 'drivers_license', 'national_id', 'residence_permit'];
            if (!in_array($documentType, $validTypes)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Invalid document type'
                ];
            }
            
            // Get document number and expiry
            $documentNumber = $_POST['documentNumber'] ?? '';
            $documentExpiry = $_POST['documentExpiry'] ?? '';
            
            if (empty($documentNumber) || empty($documentExpiry)) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Document number and expiry date are required'
                ];
            }
            
            // Verify the document exists and belongs to the user
            $documentCollection = $this->db->getCollection('documents');
            $document = $documentCollection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($documentId),
                'userId' => new MongoDB\BSON\ObjectId($userId)
            ]);
            
            if (!$document) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Document record not found'
                ];
            }
            
            // Generate unique filename
            $timestamp = time();
            $random = bin2hex(random_bytes(8));
            $originalName = $_FILES['document']['name'];
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $filename = "doc_{$userId}_{$timestamp}_{$random}.{$extension}";
            $uploadDir = __DIR__ . '/uploads/documents/';
            
            // Ensure upload directory exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Save file
            $filePath = $uploadDir . $filename;
            if (!move_uploaded_file($_FILES['document']['tmp_name'], $filePath)) {
                http_response_code(500);
                return [
                    'success' => false,
                    'error' => 'Failed to save document file'
                ];
            }
            
            // Generate relative URL path
            $urlPath = '/uploads/documents/' . $filename;
            
            // Update document record
            $updateData = [
                'documentType' => $documentType,
                'documentNumber' => $documentNumber,
                'documentExpiry' => new MongoDB\BSON\UTCDateTime(strtotime($documentExpiry) * 1000),
                'documentImageUrl' => $urlPath,
                'verificationSteps.document' => true,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $documentCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($documentId)],
                ['$set' => $updateData]
            );
            
            if (!$result['success']) {
                http_response_code(500);
                return [
                    'success' => false,
                    'error' => 'Failed to update document record'
                ];
            }
            
            return [
                'success' => true,
                'documentId' => $documentId,
                'message' => 'Document uploaded successfully'
            ];
            
        } catch (Exception $e) {
            error_log('Document upload error: ' . $e->getMessage());
            http_response_code(500);
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
            // Verify authentication
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                http_response_code(401);
                return [
                    'success' => false,
                    'error' => 'Authentication required'
                ];
            }
            
            // Check if selfie was uploaded
            if (!isset($_FILES['selfie']) || !is_uploaded_file($_FILES['selfie']['tmp_name'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'No selfie file uploaded or upload failed'
                ];
            }
            
            // Get document ID from POST data
            $documentId = $_POST['documentId'] ?? null;
            if (!$documentId) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Document ID is required'
                ];
            }
            
            // Verify the document exists and belongs to the user
            $documentCollection = $this->db->getCollection('documents');
            $document = $documentCollection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($documentId),
                'userId' => new MongoDB\BSON\ObjectId($userId)
            ]);
            
            if (!$document) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Document record not found'
                ];
            }
            
            // Check if document has been uploaded
            if (!isset($document['documentImageUrl']) || empty($document['documentImageUrl'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Document image must be uploaded first'
                ];
            }
            
            // Generate unique filename
            $timestamp = time();
            $random = bin2hex(random_bytes(8));
            $originalName = $_FILES['selfie']['name'];
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $filename = "selfie_{$userId}_{$timestamp}_{$random}.{$extension}";
            $uploadDir = __DIR__ . '/uploads/selfies/';
            
            // Ensure upload directory exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Save file
            $filePath = $uploadDir . $filename;
            if (!move_uploaded_file($_FILES['selfie']['tmp_name'], $filePath)) {
                http_response_code(500);
                return [
                    'success' => false,
                    'error' => 'Failed to save selfie file'
                ];
            }
            
            // Generate relative URL path
            $urlPath = '/uploads/selfies/' . $filename;
            
            // Set the verification method and similarity
            $verificationResult = [
                'needsManualReview' => true
            ];
            
            // Try to use face recognition if available
            if ($this->faceRecognitionAvailable) {
                try {
                    // Attempt face comparison
                    $faceResult = $this->faceVerifier->verifySelfie([
                        'tmp_name' => $filePath,
                        'name' => $filename
                    ], $documentId);
                    
                    if ($faceResult['success']) {
                        $verificationResult = [
                            'similarity' => $faceResult['similarityScore'] ?? 0,
                            'needsManualReview' => $faceResult['needs_review'] ?? true,
                            'method' => 'automated'
                        ];
                    }
                } catch (Exception $e) {
                    error_log('Face comparison failed: ' . $e->getMessage());
                    // Continue without face verification
                }
            }
            
            // Update document record with selfie and verification result
            $updateData = [
                'selfieImageUrl' => $urlPath,
                'verificationSteps.selfie' => true,
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];
            
            if (isset($verificationResult['similarity'])) {
                $updateData['similarityScore'] = $verificationResult['similarity'];
            }
            
            if (isset($verificationResult['needsManualReview'])) {
                $updateData['needsManualReview'] = $verificationResult['needsManualReview'];
            }
            
            $result = $documentCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($documentId)],
                ['$set' => $updateData]
            );
            
            if (!$result['success']) {
                http_response_code(500);
                return [
                    'success' => false,
                    'error' => 'Failed to update document with selfie'
                ];
            }
            
            return [
                'success' => true,
                'documentId' => $documentId,
                'message' => 'Selfie uploaded successfully',
                'faceRecognitionAvailable' => $this->faceRecognitionAvailable,
                'verificationMethod' => $verificationResult['method'] ?? 'manual'
            ];
            
        } catch (Exception $e) {
            error_log('Identity verification error: ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Complete verification process
     */
    public function completeVerification() {
        try {
            // Verify authentication
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                http_response_code(401);
                return [
                    'success' => false,
                    'error' => 'Authentication required'
                ];
            }
            
            // Get JSON body
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !isset($data['documentId'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Document ID is required'
                ];
            }
            
            $documentId = $data['documentId'];
            
            // Verify the document exists and belongs to the user
            $documentCollection = $this->db->getCollection('documents');
            $document = $documentCollection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($documentId),
                'userId' => new MongoDB\BSON\ObjectId($userId)
            ]);
            
            if (!$document) {
                http_response_code(404);
                return [
                    'success' => false,
                    'error' => 'Document record not found'
                ];
            }
            
            // Check if document has been uploaded
            if (!isset($document['documentImageUrl']) || empty($document['documentImageUrl'])) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Document image must be uploaded first'
                ];
            }
            
            // For some document types, selfie is required
            $requiresSelfie = in_array($document['documentType'] ?? '', ['passport', 'national_id']);
            if ($requiresSelfie && (!isset($document['selfieImageUrl']) || empty($document['selfieImageUrl']))) {
                http_response_code(400);
                return [
                    'success' => false,
                    'error' => 'Selfie must be uploaded first'
                ];
            }
            
            // Determine verification status
            // If face recognition isn't available or manual review is needed, set as pending_review
            $verificationStatus = 'pending_review';
            
            // If face recognition was used successfully and high score, could auto-approve
            if ($this->faceRecognitionAvailable && 
                isset($document['similarityScore']) && 
                $document['similarityScore'] > 0.8 && 
                !($document['needsManualReview'] ?? true)) {
                $verificationStatus = 'approved';
            }
            
            // Update document record to mark as completed/submitted
            $result = $documentCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($documentId)],
                [
                    '$set' => [
                        'status' => $verificationStatus,
                        'verificationSteps.review' => true,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                        'submittedAt' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            if (!$result['success']) {
                http_response_code(500);
                return [
                    'success' => false,
                    'error' => 'Failed to update document status'
                ];
            }
            
            // Update user verification status
            $userCollection = $this->db->getCollection('users');
            $userCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                [
                    '$set' => [
                        'verification.status' => $verificationStatus,
                        'verification.documentId' => new MongoDB\BSON\ObjectId($documentId),
                        'verification.submittedAt' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            return [
                'success' => true,
                'message' => 'Verification completed and submitted for review',
                'status' => $verificationStatus,
                'requiresManualReview' => ($verificationStatus === 'pending_review')
            ];
            
        } catch (Exception $e) {
            error_log('Complete verification error: ' . $e->getMessage());
            http_response_code(500);
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
            // If face recognition is available, use it
            if ($this->faceRecognitionAvailable) {
                return $this->faceVerifier->getFaceVerificationStatus();
            } 
            
            // Otherwise, do a direct database query
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                http_response_code(401);
                return [
                    'success' => false,
                    'error' => 'Authentication required'
                ];
            }
            
            // Get latest verification document for user
            $documentCollection = $this->db->getCollection('documents');
            $document = $documentCollection->findOne(
                [
                    'userId' => new MongoDB\BSON\ObjectId($userId)
                ],
                [
                    'sort' => ['submittedAt' => -1]
                ]
            );
            
            if (!$document) {
                return [
                    'success' => true,
                    'status' => 'not_started',
                    'message' => 'No verification process found'
                ];
            }
            
            return [
                'success' => true,
                'status' => $document['status'] ?? 'pending',
                'documentId' => (string)$document['_id'],
                'submittedAt' => isset($document['submittedAt']) ? date('Y-m-d H:i:s', $document['submittedAt']->toDateTime()->getTimestamp()) : null,
                'verificationMethod' => 'manual'
            ];
            
        } catch (Exception $e) {
            error_log('Get verification status error: ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Handle API requests
$controller = new VerificationController();

// Get the request path
$endpoint = $_SERVER['PATH_INFO'] ?? '';

// Map endpoints to controller methods
switch ($endpoint) {
    case '/personal-info':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
        }
        echo json_encode($controller->savePersonalInfo());
        break;
        
    case '/document/upload':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
        }
        echo json_encode($controller->uploadDocument());
        break;
    
    case '/selfie/upload':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
        }
        echo json_encode($controller->verifyIdentity());
        break;
    
    case '/complete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
        }
        echo json_encode($controller->completeVerification());
        break;
    
    case '/status':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            break;
        }
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
