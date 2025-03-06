<?php
// lib/FaceVerifier.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Auth.php';

class FaceVerifier {
    private $db;
    private $auth;
    private $config;

    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
        
        $this->config = [
            'upload_dir' => __DIR__ . '/../uploads/selfies/',
            'max_size' => 5 * 1024 * 1024, // 5MB
            'face_api_key' => getenv('FACE_API_KEY'),
            'face_api_url' => 'https://api.example.com/face-verification', // Replace with actual API
            'similarity_threshold' => 0.8, // Minimum similarity score to consider faces matching
            'face_detection_min_confidence' => 0.7 // Minimum confidence for face detection
        ];
        
        // Ensure upload directory exists
        if (!is_dir($this->config['upload_dir'])) {
            mkdir($this->config['upload_dir'], 0755, true);
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
            // Get user ID from token
            $userId = $this->auth->getUserIdFromToken();
            if (!$userId) {
                throw new Exception('Authentication required');
            }
            
            // Validate selfie file
            $this->validateSelfieFile($selfieFile);
            
            // Get ID document
            $documentCollection = $this->db->getCollection('documents');
            $document = $documentCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($documentId)]);
            
            if (!$document) {
                throw new Exception('ID document not found');
            }
            
            // Verify document belongs to user
            if ($document['userId'] != $userId) {
                throw new Exception('Document does not belong to the authenticated user');
            }
            
            // Check if document is a valid ID type
            $validIdTypes = ['id_card', 'passport', 'driving_license'];
            if (!in_array($document['type'], $validIdTypes)) {
                throw new Exception('Document is not a valid ID type for face verification');
            }
            
            // Generate unique filename for selfie
            $selfieFilename = $this->generateSelfieFilename($userId);
            $selfieFilePath = $this->config['upload_dir'] . $selfieFilename;
            
            // Save selfie file
            if (!move_uploaded_file($selfieFile['tmp_name'], $selfieFilePath)) {
                throw new Exception('Failed to save selfie file');
            }
            
            // Get ID document file path
            $idDocumentPath = __DIR__ . '/../uploads/documents/' . $document['filename'];
            if (!file_exists($idDocumentPath)) {
                throw new Exception('ID document file not found');
            }
            
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
                throw new Exception('Failed to save selfie record');
            }
            
            $selfieId = $insertResult['id'];
            
            // Perform face verification (if using external API)
            $verificationResult = $this->performFaceVerification(
                $selfieFilePath,
                $idDocumentPath
            );
            
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
                'selfieId' => $selfieId
            ];
            
        } catch (Exception $e) {
            error_log('Face verification error: ' . $e->getMessage());
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
        // Check if file exists and was uploaded successfully
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No selfie file uploaded or upload failed');
        }
        
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
        
        // Check if image contains a face (basic detection)
        $this->detectFace($file['tmp_name']);
        
        return true;
    }
    
    /**
     * Detect face in image
     */
    private function detectFace($imagePath) {
        // If we have a face detection API, use it
        if ($this->config['face_api_key']) {
            // Using external face detection API
            $result = $this->callFaceDetectionAPI($imagePath);
            
            if (!$result['success']) {
                throw new Exception('Face detection failed: ' . $result['error']);
            }
            
            if (!$result['faceDetected']) {
                throw new Exception('No face detected in the selfie. Please ensure your face is clearly visible');
            }
            
            return true;
        }
        
        // Basic face detection using PHP GD and OpenCV if available
        if (extension_loaded('opencv')) {
            return $this->detectFaceOpenCV($imagePath);
        }
        
        // Fallback to very basic check if no API or OpenCV available
        return $this->basicFaceDetection($imagePath);
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
        // This is a very crude approximation and not reliable
        $centerX = $width / 2;
        $centerY = $height / 2;
        $radius = min($width, $height) / 5;
        
        $skinToneFound = false;
        
        for ($x = $centerX - $radius; $x <= $centerX + $radius; $x += 5) {
            for ($y = $centerY - $radius; $y <= $centerY + $radius; $y += 5) {
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
     * Call external face detection API
     */
    private function callFaceDetectionAPI($imagePath) {
        // Load image file
        $imageData = file_get_contents($imagePath);
        $base64Image = base64_encode($imageData);
        
        // Prepare API request
        $postData = [
            'image' => $base64Image,
            'operation' => 'detect'
        ];
        
        // Call API
        $ch = curl_init($this->config['face_api_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->config['face_api_key']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'Face detection API error: HTTP ' . $httpCode
            ];
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['faces'])) {
            return [
                'success' => false,
                'error' => 'Invalid response from face detection API'
            ];
        }
        
        return [
            'success' => true,
            'faceDetected' => count($result['faces']) > 0,
            'faceCount' => count($result['faces']),
            'confidence' => $result['faces'][0]['confidence'] ?? 0
        ];
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
        // If using external API
        if ($this->config['face_api_key']) {
            return $this->callFaceVerificationAPI($selfieFilePath, $idDocumentPath);
        }
        
        // Fallback to basic reporting if no API available
        return [
            'success' => true,
            'message' => 'Face verification requires manual approval',
            'method' => 'manual',
            'similarity' => 0,
            'needs_review' => true
        ];
    }
    
    /**
     * Call external face verification API
     */
    private function callFaceVerificationAPI($selfieFilePath, $idDocumentPath) {
        // Load image files
        $selfieData = file_get_contents($selfieFilePath);
        $idDocumentData = file_get_contents($idDocumentPath);
        
        $base64Selfie = base64_encode($selfieData);
        $base64IdDocument = base64_encode($idDocumentData);
        
        // Prepare API request
        $postData = [
            'selfie' => $base64Selfie,
            'document' => $base64IdDocument,
            'operation' => 'verify'
        ];
        
        // Call API
        $ch = curl_init($this->config['face_api_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->config['face_api_key']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'Face verification API error: HTTP ' . $httpCode,
                'needs_review' => true
            ];
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['similarity'])) {
            return [
                'success' => false,
                'error' => 'Invalid response from face verification API',
                'needs_review' => true
            ];
        }
        
        $similarity = (float)$result['similarity'];
        $matchResult = $similarity >= $this->config['similarity_threshold'];
        
        return [
            'success' => $matchResult,
            'similarity' => $similarity,
            'threshold' => $this->config['similarity_threshold'],
            'message' => $matchResult ? 'Face verification successful' : 'Face verification failed',
            'needs_review' => $similarity >= 0.6 && $similarity < $this->config['similarity_threshold']
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
}
