#!/usr/bin/env php
<?php
/**
 * Face Comparison Test Script for TheGiveHub
 * 
 * This script compares a selfie with an identity document image using AWS Rekognition.
 * It's designed to be run from the command line to test face verification functionality
 * without requiring a web interface.
 * 
 * Usage:
 *   ./test-face-compare.php path/to/selfie.jpg path/to/document.jpg
 * 
 * Requirements:
 *   - PHP 7.4 or higher with GD extension
 *   - AWS SDK for PHP
 *   - AWS credentials configured in .env file
 *   - Valid image files (JPG/PNG) containing faces
 * 
 * @author TheGiveHub Development Team
 */

// Load environment variables from .env file
if (file_exists(__DIR__ . '/.env')) {
    $envVars = parse_ini_file(__DIR__ . '/.env');
    foreach ($envVars as $key => $value) {
        putenv("$key=$value");
    }
}

// Check if AWS credentials are available
if (!getenv('AWS_ACCESS_KEY_ID') || !getenv('AWS_SECRET_ACCESS_KEY') || !getenv('AWS_REGION')) {
    echo "Error: AWS credentials not found in .env file.\n";
    echo "Please ensure your .env file contains AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY and AWS_REGION.\n";
    exit(1);
}

// Check command line arguments
if ($argc != 3) {
    echo "Usage: ./test-face-compare.php path/to/selfie.jpg path/to/document.jpg\n";
    exit(1);
}

$selfieImagePath = $argv[1];
$documentImagePath = $argv[2];

// Check if files exist
if (!file_exists($selfieImagePath)) {
    echo "Error: Selfie image not found: $selfieImagePath\n";
    exit(1);
}

if (!file_exists($documentImagePath)) {
    echo "Error: Document image not found: $documentImagePath\n";
    exit(1);
}

// Verify that the GD extension is loaded
if (!extension_loaded('gd')) {
    echo "Error: PHP GD extension is required but not loaded.\n";
    exit(1);
}

// Require the AWS SDK
require_once __DIR__ . '/vendor/autoload.php';

use Aws\Rekognition\RekognitionClient;
use Aws\Exception\AwsException;

/**
 * Reads an image file and returns its contents as binary data
 * 
 * @param string $imagePath Path to the image file
 * @return string Binary image data
 */
function readImageFile($imagePath) {
    return file_get_contents($imagePath);
}

/**
 * Detect faces in an image using AWS Rekognition
 * 
 * @param RekognitionClient $client AWS Rekognition client
 * @param string $imageData Binary image data
 * @param string $imageType Description of image type for logging (e.g., "selfie" or "document")
 * @return array|null Array with face details or null if no faces detected
 */
function detectFaces($client, $imageData, $imageType) {
    try {
        $result = $client->detectFaces([
            'Image' => [
                'Bytes' => $imageData,
            ],
            'Attributes' => ['ALL'],
        ]);

        if (empty($result['FaceDetails'])) {
            echo "Warning: No faces detected in the $imageType image.\n";
            return null;
        }

        echo "Found " . count($result['FaceDetails']) . " face(s) in the $imageType image.\n";
        
        // Display information about each detected face
        foreach ($result['FaceDetails'] as $index => $face) {
            $faceNum = $index + 1;
            echo "Face #$faceNum details:\n";
            echo "  - Confidence: " . number_format($face['Confidence'], 2) . "%\n";
            
            // Display position information
            $box = $face['BoundingBox'];
            echo "  - Position: Left=" . round($box['Left'] * 100, 2) . "%, Top=" . 
                 round($box['Top'] * 100, 2) . "%, Width=" . 
                 round($box['Width'] * 100, 2) . "%, Height=" . 
                 round($box['Height'] * 100, 2) . "%\n";
            
            // Display age range if available
            if (isset($face['AgeRange'])) {
                echo "  - Estimated Age: " . $face['AgeRange']['Low'] . "-" . $face['AgeRange']['High'] . " years\n";
            }
            
            // Display quality metrics
            if (isset($face['Quality'])) {
                echo "  - Image Quality: Brightness=" . number_format($face['Quality']['Brightness'], 2) . 
                     ", Sharpness=" . number_format($face['Quality']['Sharpness'], 2) . "\n";
            }
        }
        
        return $result['FaceDetails'];
    } catch (AwsException $e) {
        echo "Error detecting faces in $imageType image: " . $e->getMessage() . "\n";
        return null;
    }
}

/**
 * Compare faces between two images using AWS Rekognition
 * 
 * @param RekognitionClient $client AWS Rekognition client
 * @param string $sourceImageData Binary data of the source image (selfie)
 * @param string $targetImageData Binary data of the target image (document)
 * @param float $similarityThreshold Minimum similarity score to consider faces as matching
 * @return array|null Array with comparison results or null on error
 */
function compareFaces($client, $sourceImageData, $targetImageData, $similarityThreshold = 80.0) {
    try {
        $result = $client->compareFaces([
            'SourceImage' => [
                'Bytes' => $sourceImageData,
            ],
            'TargetImage' => [
                'Bytes' => $targetImageData,
            ],
            'SimilarityThreshold' => $similarityThreshold,
        ]);

        return $result;
    } catch (AwsException $e) {
        echo "Error comparing faces: " . $e->getMessage() . "\n";
        return null;
    }
}

// Main execution
echo "Face Comparison Test\n";
echo "-------------------\n";
echo "Selfie image: $selfieImagePath\n";
echo "Document image: $documentImagePath\n\n";

// Initialize AWS Rekognition client
$rekognitionClient = new RekognitionClient([
    'version' => 'latest',
    'region' => getenv('AWS_REGION'),
    'credentials' => [
        'key' => getenv('AWS_ACCESS_KEY_ID'),
        'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
    ],
]);

// Read image files
echo "Reading image files...\n";
$selfieImageData = readImageFile($selfieImagePath);
$documentImageData = readImageFile($documentImagePath);

// Analyze selfie image
echo "\nAnalyzing selfie image...\n";
$selfieFaces = detectFaces($rekognitionClient, $selfieImageData, "selfie");

// Analyze document image
echo "\nAnalyzing document image...\n";
$documentFaces = detectFaces($rekognitionClient, $documentImageData, "document");

// Check if we can proceed with comparison
if (!$selfieFaces || !$documentFaces) {
    echo "\nCannot proceed with face comparison. Please ensure both images contain clearly visible faces.\n";
    exit(1);
}

// Compare faces
echo "\nComparing faces...\n";
// Using a lower threshold for testing purposes (60.0 instead of 80.0)
$comparisonResult = compareFaces($rekognitionClient, $selfieImageData, $documentImageData, 60.0);

if (!$comparisonResult) {
    echo "Face comparison failed. Please ensure both images contain clearly visible faces.\n";
    exit(1);
}

echo "\nComparison Results:\n";
echo "-------------------\n";

if (empty($comparisonResult['FaceMatches'])) {
    echo "No matching faces found.\n";
    echo "Recommendations:\n";
    echo "- Ensure both images have clear, well-lit faces\n";
    echo "- Try using different images with better quality\n";
    echo "- Make sure the person in both images is the same\n";
} else {
    echo "Found " . count($comparisonResult['FaceMatches']) . " matching face(s).\n\n";
    
    foreach ($comparisonResult['FaceMatches'] as $index => $match) {
        $matchNum = $index + 1;
        $similarity = $match['Similarity'];
        echo "Match #$matchNum:\n";
        echo "  - Similarity: " . number_format($similarity, 2) . "%\n";
        
        // Categorize the match based on similarity score
        if ($similarity >= 80.0) {
            echo "  - Verification: STRONG MATCH\n";
        } elseif ($similarity >= 70.0) {
            echo "  - Verification: POSSIBLE MATCH\n";
        } else {
            echo "  - Verification: WEAK MATCH (below recommended threshold)\n";
        }
        
        // Display face information
        $face = $match['Face'];
        echo "  - Face Confidence: " . number_format($face['Confidence'], 2) . "%\n";
        
        // Display bounding box information
        $box = $face['BoundingBox'];
        echo "  - Position: Left=" . round($box['Left'] * 100, 2) . "%, Top=" . 
              round($box['Top'] * 100, 2) . "%, Width=" . 
              round($box['Width'] * 100, 2) . "%, Height=" . 
              round($box['Height'] * 100, 2) . "%\n";
    }
}

if (!empty($comparisonResult['UnmatchedFaces'])) {
    echo "\nFound " . count($comparisonResult['UnmatchedFaces']) . " unmatched face(s) in the document image.\n";
}

echo "\nTest completed successfully.\n"; 