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
    private $auth;
    private $uploader;
    private $uploadDir;

    public function __construct() {
        parent::__construct();
        $this->auth = new Auth();
        $this->uploader = new DocumentUploader();
        $this->uploadDir = __DIR__ . '/../uploads/documents/';
        
        // Ensure upload directory exists
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Upload a document or selfie
     * @param string $param Not used
     * @param array $data Not used - we use $_FILES and $_POST directly
     * @return array Response with upload status
     */
    public function upload($param = null, $data = null) {
        try {
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }

            // Handle document upload
            if (isset($_FILES['documentFile'])) {
                return $this->handleDocumentUpload($userId);
            }

            // Handle selfie upload
            if (isset($_FILES['selfieFile'])) {
                return $this->handleSelfieUpload($userId);
            }

            throw new Exception('No file uploaded');

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle document file upload and create document record
     * @param string $userId User ID
     * @return array Upload result
     */
    private function handleDocumentUpload($userId) {
        // Get form data
        $formData = json_decode($_POST['data'], true);
        if (!$formData) {
            throw new Exception('Invalid form data');
        }

        // Validate file
        $file = $_FILES['documentFile'];
        $this->uploader->validateFile($file);

        // Generate unique filename
        $documentFilename = uniqid("doc_{$userId}_") . '.jpg';
        $documentPath = $this->uploadDir . $documentFilename;

        // Save file
        if (!move_uploaded_file($file['tmp_name'], $documentPath)) {
            throw new Exception('Failed to save document file');
        }

        // Create document record
        $document = [
            'userId' => new MongoDB\BSON\ObjectId($userId),
            'firstName' => $formData['firstName'],
            'lastName' => $formData['lastName'],
            'dateOfBirth' => new MongoDB\BSON\UTCDateTime(strtotime($formData['dateOfBirth']) * 1000),
            'address' => $formData['address'],
            'city' => $formData['city'],
            'state' => $formData['state'],
            'postalCode' => $formData['postalCode'],
            'country' => $formData['country'],
            'documentType' => $formData['documentType'],
            'documentNumber' => $formData['documentNumber'],
            'documentExpiry' => new MongoDB\BSON\UTCDateTime(strtotime($formData['documentExpiry']) * 1000),
            'documentImageUrl' => "/uploads/documents/{$documentFilename}",
            'status' => 'pending',
            'verificationAttempts' => 1,
            'createdAt' => new MongoDB\BSON\UTCDateTime(),
            'updatedAt' => new MongoDB\BSON\UTCDateTime(),
            'ipAddress' => $_SERVER['REMOTE_ADDR'],
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'metadata' => [
                'documentAuthenticityScore' => null,
                'documentQualityScore' => null,
                'faceDetectionScore' => null,
                'livenessScore' => null
            ]
        ];

        $result = $this->collection->insertOne($document);
        
        if (!$result->getInsertedId()) {
            throw new Exception('Failed to save document record');
        }

        return [
            'success' => true,
            'documentId' => (string)$result->getInsertedId(),
            'message' => 'Document uploaded successfully'
        ];
    }

    /**
     * Handle selfie file upload and update document record
     * @param string $userId User ID
     * @return array Upload result
     */
    private function handleSelfieUpload($userId) {
        if (!isset($_POST['documentId'])) {
            throw new Exception('Document ID is required for selfie upload');
        }

        $documentId = $_POST['documentId'];
        
        // Validate file
        $file = $_FILES['selfieFile'];
        $this->uploader->validateFile($file);

        // Generate unique filename
        $selfieFilename = uniqid("selfie_{$userId}_") . '.jpg';
        $selfiePath = $this->uploadDir . $selfieFilename;

        // Save file
        if (!move_uploaded_file($file['tmp_name'], $selfiePath)) {
            throw new Exception('Failed to save selfie file');
        }

        // Update document record with selfie
        $result = $this->collection->updateOne(
            [
                '_id' => new MongoDB\BSON\ObjectId($documentId),
                'userId' => new MongoDB\BSON\ObjectId($userId)
            ],
            [
                '$set' => [
                    'selfieImageUrl' => "/uploads/documents/{$selfieFilename}",
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );

        if (!$result->getModifiedCount()) {
            throw new Exception('Failed to update document record with selfie');
        }

        return [
            'success' => true,
            'message' => 'Selfie uploaded successfully'
        ];
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

            // Initialize AWS Rekognition client
            $rekognition = new AWSRekognitionClient();

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

            // Step 1: Detect faces in document
            $documentFaces = $rekognition->detectFaces($documentPath);
            if (!$documentFaces['success'] || !$documentFaces['faceDetected']) {
                throw new Exception('No face detected in ID document');
            }

            // Step 2: Detect faces in selfie
            $selfieFaces = $rekognition->detectFaces($selfiePath);
            if (!$selfieFaces['success'] || !$selfieFaces['faceDetected']) {
                throw new Exception('No face detected in selfie');
            }

            // Step 3: Compare faces
            $comparison = $rekognition->compareFaces($selfiePath, $documentPath);
            if (!$comparison['success']) {
                throw new Exception('Face comparison failed');
            }

            // Calculate quality scores based on face detection results
            $documentQuality = $this->calculateImageQuality($documentFaces['faces'][0]);
            $selfieQuality = $this->calculateImageQuality($selfieFaces['faces'][0]);
            $livenessScore = $this->calculateLivenessScore($selfieFaces['faces'][0]);

            // Prepare verification scores
            $scores = [
                'documentAuthenticityScore' => $documentQuality,
                'documentQualityScore' => $documentQuality,
                'faceDetectionScore' => ($documentFaces['faces'][0]['Confidence'] + $selfieFaces['faces'][0]['Confidence']) / 200,
                'livenessScore' => $livenessScore
            ];

            // Determine verification status
            $status = $this->determineVerificationStatus($comparison, $scores);

            // Update document with verification results
            $result = $this->collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($param)],
                [
                    '$set' => [
                        'status' => $status,
                        'similarityScore' => $comparison['similarity'],
                        'metadata' => $scores,
                        'verificationDetails' => [
                            'documentFaces' => $documentFaces,
                            'selfieFaces' => $selfieFaces,
                            'comparison' => $comparison
                        ],
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );

            if (!$result->getModifiedCount()) {
                throw new Exception('Failed to update verification status');
            }

            return [
                'success' => true,
                'message' => 'Verification completed successfully',
                'status' => $status,
                'scores' => $scores,
                'similarityScore' => $comparison['similarity'],
                'needs_review' => $comparison['needs_review'] ?? false
            ];

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