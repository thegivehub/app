<?php
/**
 * FaceRecognitionClient - A class to handle integration with facial recognition APIs
 */
class FaceRecognitionClient {
    private $provider;
    private $client;
    private $config;
    
    /**
     * Constructor
     * 
     * @param string $provider The facial recognition provider to use
     * @param array $config Configuration for the provider
     */
    public function __construct($provider = 'aws', array $config = []) {
        $this->provider = $provider;
        
        try {
            // Try to load environment variables from .env file if they're not set
            $this->loadEnvFile();
            
            switch ($provider) {
                case 'aws':
                    $awsKeyId = getenv('AWS_ACCESS_KEY_ID');
                    $awsSecretKey = getenv('AWS_SECRET_ACCESS_KEY');
                    
                    // Debug output
                    error_log("AWS Key exists: " . ($awsKeyId ? 'YES' : 'NO'));
                    error_log("AWS Secret exists: " . ($awsSecretKey ? 'YES' : 'NO'));
                    
                    if (class_exists('Aws\Rekognition\RekognitionClient') && $awsKeyId && $awsSecretKey) {
                        require_once __DIR__ . '/AWSRekognitionClient.php';
                        $this->client = new AWSRekognitionClient();
                    } else {
                        // Try to get keys from .env directly 
                        $envFile = __DIR__ . '/../.env';
                        if (file_exists($envFile)) {
                            $envContents = file_get_contents($envFile);
                            error_log("ENV file exists and contains " . strlen($envContents) . " characters");
                            
                            // Parse AWS keys from .env content
                            preg_match('/AWS_ACCESS_KEY_ID=([^\s]+)/', $envContents, $keyMatches);
                            preg_match('/AWS_SECRET_ACCESS_KEY=([^\s]+)/', $envContents, $secretMatches);
                            
                            $awsKeyId = $keyMatches[1] ?? null;
                            $awsSecretKey = $secretMatches[1] ?? null;
                            
                            error_log("Extracted AWS Key: " . ($awsKeyId ? 'Found' : 'Not found'));
                            error_log("Extracted AWS Secret: " . ($awsSecretKey ? 'Found' : 'Not found'));
                            
                            if ($awsKeyId && $awsSecretKey && class_exists('Aws\Rekognition\RekognitionClient')) {
                                // Set keys manually for this session
                                putenv("AWS_ACCESS_KEY_ID=$awsKeyId");
                                putenv("AWS_SECRET_ACCESS_KEY=$awsSecretKey");
                                
                                require_once __DIR__ . '/AWSRekognitionClient.php';
                                $this->client = new AWSRekognitionClient();
                            } else {
                                // Fallback to development mode
                                $this->provider = 'development';
                                error_log("AWS Rekognition not available after .env extraction. Using development mode.");
                            }
                        } else {
                            // Fallback to development mode
                            $this->provider = 'development';
                            error_log("AWS Rekognition not available. Using development mode. (.env file not found)");
                        }
                    }
                    break;
                    
                case 'azure':
                    // Azure implementation would go here
                    throw new Exception('Azure Face API not implemented');
                    break;
                    
                case 'google':
                    // Google Cloud Vision implementation would go here
                    throw new Exception('Google Cloud Vision not implemented');
                    break;
                    
                case 'custom':
                    // Use development mode instead of failing
                    $this->provider = 'development';
                    error_log("Using development mode for face recognition.");
                    break;
                    
                case 'development':
                    // This is our fallback mode that doesn't require external APIs
                    $this->provider = 'development';
                    break;
                    
                default:
                    throw new Exception("Unsupported facial recognition provider: {$provider}");
            }
        } catch (Exception $e) {
            error_log("Failed to initialize face recognition client: " . $e->getMessage());
            // Don't rethrow, just use development mode
            $this->provider = 'development';
        }
    }
    
    /**
     * Load environment variables from .env file
     */
    private function loadEnvFile() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse key=value pairs
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                        $value = substr($value, 1, -1);
                    } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                        $value = substr($value, 1, -1);
                    }
                    
                    // Set environment variable
                    if (!getenv($key)) {
                        putenv("$key=$value");
                    }
                }
            }
        }
    }
    
    /**
     * Detect faces in an image
     * 
     * @param string $imagePath Path to the image file
     * @return array Result with face detection information
     */
    public function detectFaces($imagePath) {
        try {
            // If we have an actual client, use it
            if ($this->provider !== 'development' && $this->client) {
                return $this->client->detectFaces($imagePath);
            }
            
            // Otherwise use our fallback implementation
            return $this->fallbackFaceDetection($imagePath);
        } catch (Exception $e) {
            error_log("Face detection error ({$this->provider}): " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'faceDetected' => false,
                'faceCount' => 0
            ];
        }
    }
    
    /**
     * Compare faces between two images
     * 
     * @param string $sourceImagePath Path to the source image (usually the selfie)
     * @param string $targetImagePath Path to the target image (usually the ID document)
     * @return array Comparison results
     */
    public function compareFaces($sourceImagePath, $targetImagePath) {
        try {
            // If we have an actual client, use it
            if ($this->provider !== 'development' && $this->client) {
                return $this->client->compareFaces($sourceImagePath, $targetImagePath);
            }
            
            // Otherwise use our fallback implementation
            return $this->fallbackFaceComparison($sourceImagePath, $targetImagePath);
        } catch (Exception $e) {
            error_log("Face comparison error ({$this->provider}): " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'similarity' => 0,
                'isMatch' => false,
                'matchConfidence' => 0
            ];
        }
    }
    
    /**
     * Fall back to a simplified face detection if no API is available
     * This is a very basic implementation and not suitable for production use.
     */
    public function fallbackFaceDetection($imagePath) {
        // Basic face detection using GD
        $image = @getimagesize($imagePath);
        if (!$image) {
            return [
                'success' => false,
                'error' => 'Invalid image file',
                'faceDetected' => false
            ];
        }
        
        // In development mode, assume any image contains a face
        return [
            'success' => true,
            'faceDetected' => true,
            'faceCount' => 1,
            'confidence' => 0.8,
            'provider' => 'development',
            'message' => 'Development mode - assuming image contains a face'
        ];
    }
    
    /**
     * Fall back to a simplified face comparison if no API is available
     * This is NOT a reliable face comparison method and should only be used 
     * for development or testing purposes.
     */
    public function fallbackFaceComparison($sourceImagePath, $targetImagePath) {
        // In development mode, assume a moderate match that requires manual review
        return [
            'success' => true,
            'similarity' => 0.6,
            'isMatch' => false,  // Not high enough to auto-approve
            'matchConfidence' => 60,
            'message' => 'Development mode - manual review required',
            'needs_review' => true,
            'provider' => 'development'
        ];
    }
    
    /**
     * Helper function to load an image from path
     */
    private function loadImageFromPath($path, $type) {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($path);
            default:
                return null;
        }
    }
} 