<?php
/**
 * AWSRekognitionClient - AWS Rekognition implementation for face detection and comparison
 */

use Aws\Rekognition\RekognitionClient;
use Aws\Exception\AwsException;

class AWSRekognitionClient {
    private $client;
    private $config;

    /**
     * Constructor - initializes AWS Rekognition client
     * 
     * @throws Exception if AWS credentials are not configured
     */
    public function __construct() {
        // Load configuration
        $this->config = [
            'version' => 'latest',
            'region' => getenv('AWS_REGION') ?: 'us-east-1',
            'credentials' => [
                'key' => getenv('AWS_ACCESS_KEY_ID'),
                'secret' => getenv('AWS_SECRET_ACCESS_KEY')
            ],
            'similarity_threshold' => (float)(getenv('FACE_SIMILARITY_THRESHOLD') ?: 80.0),
            'detection_confidence' => (float)(getenv('FACE_DETECTION_MIN_CONFIDENCE') ?: 0.7)
        ];

        // Debug credentials
        error_log("AWS Initialization - Region: " . $this->config['region']);
        error_log("AWS Key available: " . (!empty($this->config['credentials']['key']) ? 'YES' : 'NO'));
        error_log("AWS Secret available: " . (!empty($this->config['credentials']['secret']) ? 'YES' : 'NO'));

        // Use hardcoded placeholder keys if environment variables are not set
        // In production, these would come from environment variables
        if (empty($this->config['credentials']['key']) || empty($this->config['credentials']['secret'])) {
            error_log("Using fallback hardcoded AWS credentials - REPLACE IN PRODUCTION!");
            
            // The following are placeholder values - replace with actual AWS credentials
            // WARNING: Hard-coding credentials is a security risk
            // This is only for development/testing and should be replaced with 
            // proper environment variables in production
            $this->config['credentials'] = [
                'key' => 'your-access-key-id',
                'secret' => 'your-secret-access-key'
            ];
        }

        // Initialize AWS Rekognition client
        try {
            $this->client = new RekognitionClient([
                'version' => $this->config['version'],
                'region' => $this->config['region'],
                'credentials' => $this->config['credentials']
            ]);
            
            error_log("AWS Rekognition client initialized successfully");
        } catch (AwsException $e) {
            error_log('Failed to initialize AWS Rekognition client: ' . $e->getMessage());
            throw new Exception('Failed to initialize AWS Rekognition client: ' . $e->getMessage());
        }
    }

    /**
     * Detect faces in an image
     * 
     * @param string $imagePath Path to image file
     * @return array Detection results
     * @throws Exception if detection fails
     */
    public function detectFaces($imagePath) {
        try {
            // Read image file
            if (!file_exists($imagePath)) {
                throw new Exception('Image file not found: ' . $imagePath);
            }
            $imageBytes = file_get_contents($imagePath);

            // Call Rekognition DetectFaces
            $result = $this->client->detectFaces([
                'Image' => [
                    'Bytes' => $imageBytes
                ],
                'Attributes' => ['ALL']
            ]);

            // Process results
            $faces = $result->get('FaceDetails');
            $faceCount = count($faces);

            // Check if any faces meet our confidence threshold
            $validFaces = array_filter($faces, function($face) {
                return $face['Confidence'] >= ($this->config['detection_confidence'] * 100);
            });

            return [
                'success' => true,
                'faceDetected' => count($validFaces) > 0,
                'faceCount' => count($validFaces),
                'faces' => $validFaces,
                'provider' => 'aws'
            ];

        } catch (AwsException $e) {
            error_log('AWS Rekognition DetectFaces failed: ' . $e->getMessage());
            throw new Exception('AWS Rekognition DetectFaces failed: ' . $e->getMessage());
        }
    }

    /**
     * Compare faces between two images
     * 
     * @param string $sourceImagePath Path to source image (selfie)
     * @param string $targetImagePath Path to target image (ID document)
     * @return array Comparison results
     * @throws Exception if comparison fails
     */
    public function compareFaces($sourceImagePath, $targetImagePath) {
        try {
            // Validate image files
            if (!file_exists($sourceImagePath)) {
                throw new Exception('Source image file not found: ' . $sourceImagePath);
            }
            if (!file_exists($targetImagePath)) {
                throw new Exception('Target image file not found: ' . $targetImagePath);
            }

            // Read image files
            $sourceImageBytes = file_get_contents($sourceImagePath);
            $targetImageBytes = file_get_contents($targetImagePath);

            // Call Rekognition CompareFaces
            $result = $this->client->compareFaces([
                'SourceImage' => [
                    'Bytes' => $sourceImageBytes
                ],
                'TargetImage' => [
                    'Bytes' => $targetImageBytes
                ],
                'SimilarityThreshold' => $this->config['similarity_threshold']
            ]);

            // Process results
            $faceMatches = $result->get('FaceMatches');
            $unmatchedFaces = $result->get('UnmatchedFaces');

            if (empty($faceMatches)) {
                return [
                    'success' => true,
                    'similarity' => 0,
                    'isMatch' => false,
                    'matchConfidence' => 0,
                    'message' => empty($unmatchedFaces) ? 'No faces detected in one or both images' : 'No matching faces found',
                    'provider' => 'aws'
                ];
            }

            // Get best match
            $bestMatch = $faceMatches[0];
            $similarity = $bestMatch['Similarity'];
            $isMatch = $similarity >= $this->config['similarity_threshold'];

            return [
                'success' => true,
                'similarity' => $similarity / 100, // Normalize to 0-1 range
                'isMatch' => $isMatch,
                'matchConfidence' => $similarity,
                'message' => $isMatch ? 'Face verification successful' : 'Face verification failed',
                'needs_review' => $similarity >= 40 && $similarity < $this->config['similarity_threshold'],
                'provider' => 'aws'
            ];

        } catch (AwsException $e) {
            error_log('AWS Rekognition CompareFaces failed: ' . $e->getMessage());
            throw new Exception('AWS Rekognition CompareFaces failed: ' . $e->getMessage());
        }
    }
} 