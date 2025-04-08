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
            switch ($provider) {
                case 'aws':
                    require_once __DIR__ . '/AWSRekognitionClient.php';
                    $this->client = new AWSRekognitionClient();
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
                    // Custom implementation would go here
                    throw new Exception('Custom face recognition not implemented');
                    break;
                    
                default:
                    throw new Exception("Unsupported facial recognition provider: {$provider}");
            }
        } catch (Exception $e) {
            error_log("Failed to initialize face recognition client: " . $e->getMessage());
            throw $e;
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
            return $this->client->detectFaces($imagePath);
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
            return $this->client->compareFaces($sourceImagePath, $targetImagePath);
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
        
        list($width, $height, $type) = $image;
        
        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($imagePath);
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
        
        // Check for skin tone pixels in central area
        $centerX = $width / 2;
        $centerY = $height / 2;
        $radius = min($width, $height) / 5;
        
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
                
                if ($r > 60 && $g > 40 && $b > 20 && $r > $g && $r > $b) {
                    $skinTonePixels++;
                }
            }
        }
        
        imagedestroy($image);
        
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
     * This is NOT a reliable face comparison method and should only be used 
     * for development or testing purposes.
     */
    public function fallbackFaceComparison($sourceImagePath, $targetImagePath) {
        // First check if both images have faces
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
        
        // Load and resize images for comparison
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
        
        // Resize to same dimensions
        $size = 100;
        $resizedSource = imagecreatetruecolor($size, $size);
        $resizedTarget = imagecreatetruecolor($size, $size);
        
        imagecopyresampled($resizedSource, $sourceImage, 0, 0, 0, 0, $size, $size, $sourceWidth, $sourceHeight);
        imagecopyresampled($resizedTarget, $targetImage, 0, 0, 0, 0, $size, $size, $targetWidth, $targetHeight);
        
        // Convert to grayscale
        imagefilter($resizedSource, IMG_FILTER_GRAYSCALE);
        imagefilter($resizedTarget, IMG_FILTER_GRAYSCALE);
        
        // Compare pixels
        $totalPixels = $size * $size;
        $matchingPixels = 0;
        $diffSum = 0;
        
        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                $sourcePixel = imagecolorat($resizedSource, $x, $y) & 0xFF;
                $targetPixel = imagecolorat($resizedTarget, $x, $y) & 0xFF;
                
                $diff = abs($sourcePixel - $targetPixel);
                $diffSum += $diff;
                
                if ($diff < 40) {
                    $matchingPixels++;
                }
            }
        }
        
        // Calculate similarity
        $similarity = $matchingPixels / $totalPixels;
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
        imagedestroy($resizedSource);
        imagedestroy($resizedTarget);
        
        return [
            'success' => true,
            'similarity' => $similarity,
            'isMatch' => $similarity >= 0.7,
            'matchConfidence' => $similarity * 100,
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
            default:
                return null;
        }
    }
} 