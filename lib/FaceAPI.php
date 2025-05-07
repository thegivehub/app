<?php
/**
 * FaceRecognitionClient - A class to handle integration with facial recognition APIs
 * 
 * This class provides an abstraction layer that can work with multiple facial recognition
 * API providers. By default, it uses AWS Rekognition, but can be configured to use
 * Azure Face API, Google Cloud Vision, or a custom provider.
 */
class Face {
    // API provider configs
    private $provider;
    private $config;
    
    /**
     * Constructor
     * 
     * @param string $provider The facial recognition provider to use
     * @param array $config Configuration for the provider
     */
    public function __construct($provider = 'aws', array $config = []) {
        $this->provider = $provider;
        
        // Set default configuration based on provider
        switch ($provider) {
            case 'aws':
                $this->config = array_merge([
                    'region' => 'us-east-1',
                    'version' => 'latest',
                    'key' => getenv('AWS_ACCESS_KEY_ID'),
                    'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
                    'similarity_threshold' => 80.0, // 0-100 match confidence 
                ], $config);
                break;
                
            case 'azure':
                $this->config = array_merge([
                    'endpoint' => 'https://yourresource.cognitiveservices.azure.com/',
                    'key' => getenv('AZURE_FACE_API_KEY'),
                    'similarity_threshold' => 0.6, // 0-1 match confidence
                ], $config);
                break;
                
            case 'google':
                $this->config = array_merge([
                    'credentials_path' => getenv('GOOGLE_APPLICATION_CREDENTIALS'),
                    'similarity_threshold' => 0.6, // 0-1 match confidence
                ], $config);
                break;
                
            case 'custom':
                $this->config = array_merge([
                    'api_url' => 'https://api.example.com/face',
                    'api_key' => getenv('FACE_API_KEY'),
                    'similarity_threshold' => 0.7, // 0-1 match confidence
                ], $config);
                break;
                
            default:
                throw new InvalidArgumentException("Unsupported facial recognition provider: {$provider}");
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
            switch ($this->provider) {
                case 'aws':
                    return $this->detectFacesAWS($imagePath);
                case 'azure':
                    return $this->detectFacesAzure($imagePath);
                case 'google':
                    return $this->detectFacesGoogle($imagePath);
                case 'custom':
                    return $this->detectFacesCustom($imagePath);
                default:
                    throw new Exception("Unsupported provider: {$this->provider}");
            }
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
            switch ($this->provider) {
                case 'aws':
                    return $this->compareFacesAWS($sourceImagePath, $targetImagePath);
                case 'azure':
                    return $this->compareFacesAzure($sourceImagePath, $targetImagePath);
                case 'google':
                    return $this->compareFacesGoogle($sourceImagePath, $targetImagePath);
                case 'custom':
                    return $this->compareFacesCustom($sourceImagePath, $targetImagePath);
                default:
                    throw new Exception("Unsupported provider: {$this->provider}");
            }
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
    
    /*
     * AWS Rekognition implementation
     */
    private function detectFacesAWS($imagePath) {
        // Check if AWS SDK is available
        if (!class_exists('Aws\Rekognition\RekognitionClient')) {
            throw new Exception('AWS SDK not available. Install it with: composer require aws/aws-sdk-php');
        }
        
        // Load the image
        $imageBytes = file_get_contents($imagePath);
        
        // Create AWS Rekognition client
        $rekognition = new Aws\Rekognition\RekognitionClient([
            'version' => $this->config['version'],
            'region' => $this->config['region'],
            'credentials' => [
                'key' => $this->config['key'],
                'secret' => $this->config['secret'],
            ]
        ]);
        
        // Detect faces
        $result = $rekognition->detectFaces([
            'Image' => [
                'Bytes' => $imageBytes,
            ],
            'Attributes' => ['DEFAULT'],
        ]);
        
        $faces = $result->get('FaceDetails');
        $faceCount = count($faces);
        
        return [
            'success' => true,
            'faceDetected' => $faceCount > 0,
            'faceCount' => $faceCount,
            'faces' => $faces,
            'provider' => 'aws'
        ];
    }
    
    private function compareFacesAWS($sourceImagePath, $targetImagePath) {
        // Check if AWS SDK is available
        if (!class_exists('Aws\Rekognition\RekognitionClient')) {
            throw new Exception('AWS SDK not available. Install it with: composer require aws/aws-sdk-php');
        }
        
        // Load images
        $sourceImageBytes = file_get_contents($sourceImagePath);
        $targetImageBytes = file_get_contents($targetImagePath);
        
        // Create AWS Rekognition client
        $rekognition = new Aws\Rekognition\RekognitionClient([
            'version' => $this->config['version'],
            'region' => $this->config['region'],
            'credentials' => [
                'key' => $this->config['key'],
                'secret' => $this->config['secret'],
            ]
        ]);
        
        // Compare faces
        $result = $rekognition->compareFaces([
            'SourceImage' => [
                'Bytes' => $sourceImageBytes,
            ],
            'TargetImage' => [
                'Bytes' => $targetImageBytes,
            ],
            'SimilarityThreshold' => $this->config['similarity_threshold'],
        ]);
        
        $faceMatches = $result->get('FaceMatches');
        $matchCount = count($faceMatches);
        
        if ($matchCount > 0) {
            $bestMatch = $faceMatches[0];
            $similarity = $bestMatch['Similarity'];
            $isMatch = $similarity >= $this->config['similarity_threshold'];
            
            return [
                'success' => true,
                'similarity' => $similarity / 100, // Normalize to 0-1 range
                'isMatch' => $isMatch,
                'matchConfidence' => $similarity,
                'matches' => $faceMatches,
                'provider' => 'aws'
            ];
        } else {
            return [
                'success' => true,
                'similarity' => 0,
                'isMatch' => false,
                'matchConfidence' => 0,
                'matches' => [],
                'provider' => 'aws'
            ];
        }
    }
    
    /*
     * Azure Face API implementation
     */
    private function detectFacesAzure($imagePath) {
        // Load image content
        $imageData = file_get_contents($imagePath);
        
        // Prepare API request
        $endpoint = $this->config['endpoint'];
        $key = $this->config['key'];
        
        $ch = curl_init("{$endpoint}face/v1.0/detect?returnFaceId=true&returnFaceLandmarks=false&recognitionModel=recognition_04");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/octet-stream',
            'Ocp-Apim-Subscription-Key: ' . $key
        ]);
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status !== 200) {
            throw new Exception("Azure Face API error: HTTP {$status}");
        }
        
        $result = json_decode($response, true);
        $faceCount = count($result);
        
        return [
            'success' => true,
            'faceDetected' => $faceCount > 0,
            'faceCount' => $faceCount,
            'faces' => $result,
            'provider' => 'azure'
        ];
    }
    
    private function compareFacesAzure($sourceImagePath, $targetImagePath) {
        // This requires creating a face list, adding faces, and then verifying
        // Simplified implementation for example purposes
        
        // First detect faces in both images
        $sourceFaces = $this->detectFacesAzure($sourceImagePath);
        $targetFaces = $this->detectFacesAzure($targetImagePath);
        
        if (!$sourceFaces['faceDetected'] || !$targetFaces['faceDetected']) {
            return [
                'success' => true,
                'similarity' => 0,
                'isMatch' => false,
                'matchConfidence' => 0,
                'provider' => 'azure'
            ];
        }
        
        // Get face IDs
        $sourceFaceId = $sourceFaces['faces'][0]['faceId'];
        $targetFaceId = $targetFaces['faces'][0]['faceId'];
        
        // Verify faces
        $endpoint = $this->config['endpoint'];
        $key = $this->config['key'];
        
        $postData = json_encode([
            'faceId1' => $sourceFaceId,
            'faceId2' => $targetFaceId
        ]);
        
        $ch = curl_init("{$endpoint}face/v1.0/verify");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Ocp-Apim-Subscription-Key: ' . $key
        ]);
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status !== 200) {
            throw new Exception("Azure Face API error: HTTP {$status}");
        }
        
        $result = json_decode($response, true);
        $similarity = $result['confidence'];
        $isMatch = $result['isIdentical'];
        
        return [
            'success' => true,
            'similarity' => $similarity,
            'isMatch' => $isMatch,
            'matchConfidence' => $similarity * 100, // Convert to percentage
            'provider' => 'azure'
        ];
    }
    
    /*
     * Google Cloud Vision implementation
     */
    private function detectFacesGoogle($imagePath) {
        // Check if Google Cloud client is available
        if (!class_exists('Google\Cloud\Vision\VisionClient')) {
            throw new Exception('Google Cloud Vision client not available. Install it with: composer require google/cloud-vision');
        }
        
        // Initialize Google Cloud Vision
        $vision = new Google\Cloud\Vision\VisionClient([
            'keyFilePath' => $this->config['credentials_path']
        ]);
        
        // Detect faces
        $image = $vision->image(file_get_contents($imagePath), [
            'FACE_DETECTION'
        ]);
        
        $annotation = $vision->annotate($image);
        $faces = $annotation->faces();
        $faceCount = count($faces);
        
        return [
            'success' => true,
            'faceDetected' => $faceCount > 0,
            'faceCount' => $faceCount,
            'faces' => $faces,
            'provider' => 'google'
        ];
    }
    
    private function compareFacesGoogle($sourceImagePath, $targetImagePath) {
        // Google Cloud Vision doesn't provide a direct face comparison API
        // This would typically be implemented using their face detection + custom matching logic
        
        // For this example, we'll return a simplified result
        return [
            'success' => false,
            'error' => 'Face comparison not supported with Google Cloud Vision API directly',
            'provider' => 'google'
        ];
    }
    
    /*
     * Custom API implementation
     * This provides an example of how to implement a custom face recognition API
     */
    private function detectFacesCustom($imagePath) {
        // Load image content
        $imageBytes = file_get_contents($imagePath);
        $base64Image = base64_encode($imageBytes);
        
        // Prepare API request
        $apiUrl = $this->config['api_url'];
        $apiKey = $this->config['api_key'];
        
        $postData = json_encode([
            'image' => $base64Image,
            'operation' => 'detect'
        ]);
        
        // Call API
        $ch = curl_init($apiUrl . '/detect');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status !== 200) {
            throw new Exception("Custom face API error: HTTP {$status}");
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['faces'])) {
            throw new Exception('Invalid response from face detection API');
        }
        
        $faceCount = count($result['faces']);
        
        return [
            'success' => true,
            'faceDetected' => $faceCount > 0,
            'faceCount' => $faceCount,
            'faces' => $result['faces'],
            'provider' => 'custom'
        ];
    }
    
    private function compareFacesCustom($sourceImagePath, $targetImagePath) {
        // Load images
        $sourceBytes = file_get_contents($sourceImagePath);
        $targetBytes = file_get_contents($targetImagePath);
        
        $base64Source = base64_encode($sourceBytes);
        $base64Target = base64_encode($targetBytes);
        
        // Prepare API request
        $apiUrl = $this->config['api_url'];
        $apiKey = $this->config['api_key'];
        
        $postData = json_encode([
            'source_image' => $base64Source,
            'target_image' => $base64Target,
            'operation' => 'compare'
        ]);
        
        // Call API
        $ch = curl_init($apiUrl . '/compare');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status !== 200) {
            throw new Exception("Custom face API error: HTTP {$status}");
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['similarity'])) {
            throw new Exception('Invalid response from face comparison API');
        }
        
        $similarity = (float)$result['similarity'];
        $isMatch = $similarity >= $this->config['similarity_threshold'];
        
        return [
            'success' => true,
            'similarity' => $similarity,
            'isMatch' => $isMatch,
            'matchConfidence' => $similarity * 100, // Convert to percentage
            'provider' => 'custom'
        ];
    }
    
    /**
     * Fall back to a simplified face detection if no API is available
     * 
     * This is a very basic implementation and not suitable for production use.
     * It should only be used for testing or as a last resort.
     * 
     * @param string $imagePath Path to image file
     * @return array Face detection result
     */
    public function fallbackFaceDetection($imagePath) {
        // Load image
        $imageData = @getimagesize($imagePath);
        if (!$imageData) {
            return [
                'success' => false,
                'error' => 'Invalid image file',
                'faceDetected' => false
            ];
        }
        
        // Check file format
        list($width, $height, $type) = $imageData;
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($imagePath);
                break;
            default:
                return [
                    'success' => false,
                    'error' => 'Unsupported image format',
                    'faceDetected' => false
                ];
        }
        
        if (!$image) {
            return [
                'success' => false,
                'error' => 'Failed to load image',
                'faceDetected' => false
            ];
        }
        
        // Convert to grayscale
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        
        // Check for skin tone pixels in the central portion of the image
        // This is a very crude approximation and not reliable
        $centerX = $width / 2;
        $centerY = $height / 2;
        $radius = min($width, $height) / 5;
        
        $skinToneFound = false;
        $skinTonePixels = 0;
        $totalPixels = 0;
        
        for ($x = $centerX - $radius; $x <= $centerX + $radius; $x += 5) {
            for ($y = $centerY - $radius; $y <= $centerY + $radius; $y += 5) {
                if ($x < 0 || $x >= $width || $y < 0 || $y >= $height) continue;
                
                $totalPixels++;
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Very basic skin tone detection
                if ($r > 60 && $g > 40 && $b > 20 && $r > $g && $r > $b) {
                    $skinTonePixels++;
                }
            }
        }
        
        imagedestroy($image);
        
        // Calculate percentage of skin tone pixels
        $skinToneRatio = $totalPixels > 0 ? $skinTonePixels / $totalPixels : 0;
        $faceDetected = $skinToneRatio > 0.3;
        
        return [
            'success' => true,
            'faceDetected' => $faceDetected,
            'faceCount' => $faceDetected ? 1 : 0,
            'confidence' => $skinToneRatio,
            'provider' => 'fallback'
        ];
    }
    
    /**
     * Fall back to a simplified face comparison if no API is available
     * 
     * This is NOT a reliable face comparison method and should only be used 
     * for development or testing purposes when no API is available.
     * 
     * @param string $sourceImagePath Path to source image
     * @param string $targetImagePath Path to target image
     * @return array Comparison result
     */
    public function fallbackFaceComparison($sourceImagePath, $targetImagePath) {
        // First ensure both images have faces
        $sourceFace = $this->fallbackFaceDetection($sourceImagePath);
        $targetFace = $this->fallbackFaceDetection($targetImagePath);
        
        if (!$sourceFace['faceDetected'] || !$targetFace['faceDetected']) {
            return [
                'success' => true,
                'similarity' => 0,
                'isMatch' => false,
                'matchConfidence' => 0,
                'message' => 'Face not detected in one or both images',
                'provider' => 'fallback'
            ];
        }
        
        // Load images and resize to same dimensions for comparison
        list($sourceWidth, $sourceHeight, $sourceType) = getimagesize($sourceImagePath);
        list($targetWidth, $targetHeight, $targetType) = getimagesize($targetImagePath);
        
        $sourceImage = $this->loadImageFromPath($sourceImagePath, $sourceType);
        $targetImage = $this->loadImageFromPath($targetImagePath, $targetType);
        
        if (!$sourceImage || !$targetImage) {
            return [
                'success' => false,
                'error' => 'Failed to load images for comparison',
                'similarity' => 0,
                'isMatch' => false,
                'provider' => 'fallback'
            ];
        }
        
        // Resize images to same dimensions (100x100 should be enough for basic comparison)
        $size = 100;
        $resizedSource = imagecreatetruecolor($size, $size);
        $resizedTarget = imagecreatetruecolor($size, $size);
        
        imagecopyresampled($resizedSource, $sourceImage, 0, 0, 0, 0, $size, $size, $sourceWidth, $sourceHeight);
        imagecopyresampled($resizedTarget, $targetImage, 0, 0, 0, 0, $size, $size, $targetWidth, $targetHeight);
        
        // Convert to grayscale
        imagefilter($resizedSource, IMG_FILTER_GRAYSCALE);
        imagefilter($resizedTarget, IMG_FILTER_GRAYSCALE);
        
        // Calculate similarity by comparing pixel by pixel
        $totalPixels = $size * $size;
        $matchingPixels = 0;
        $diffSum = 0;
        
        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $sourcePixel = imagecolorat($resizedSource, $x, $y) & 0xFF;
                $targetPixel = imagecolorat($resizedTarget, $x, $y) & 0xFF;
                
                // Calculate difference
                $diff = abs($sourcePixel - $targetPixel);
                $diffSum += $diff;
                
                // Count as matching if difference is small
                if ($diff < 40) {
                    $matchingPixels++;
                }
            }
        }
        
        // Calculate similarity score (0-1)
        $similarity = $matchingPixels / $totalPixels;
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        imagedestroy($resizedSource);
        imagedestroy($resizedTarget);
        
        // Determine if it's a match based on similarity threshold
        $isMatch = $similarity >= $this->config['similarity_threshold'];
        
        return [
            'success' => true,
            'similarity' => $similarity,
            'isMatch' => $isMatch,
            'matchConfidence' => $similarity * 100, // Convert to percentage
            'message' => 'Fallback comparison has low reliability',
            'needs_review' => true,
            'provider' => 'fallback'
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
            case IMAGETYPE_GIF:
                return imagecreatefromgif($path);
            default:
                return null;
        }
    }
}
