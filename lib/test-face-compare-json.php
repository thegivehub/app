#!/usr/bin/env php
<?php
/**
 * Face Comparison JSON Output Script for TheGiveHub
 * 
 * This script compares a selfie with an identity document image using AWS Rekognition
 * and outputs the results as JSON.
 * 
 * Usage:
 *   ./test-face-compare-json.php path/to/selfie.jpg path/to/document.jpg
 * 
 * @author TheGiveHub Development Team
 */

// Load environment variables from .env file
if (file_exists(__DIR__ . '/../.env')) {
    $envVars = parse_ini_file(__DIR__ . '/../.env');
    foreach ($envVars as $key => $value) {
        putenv("$key=$value");
    }
}

// Check if AWS credentials are available
if (!getenv('AWS_ACCESS_KEY_ID') || !getenv('AWS_SECRET_ACCESS_KEY') || !getenv('AWS_REGION')) {
    echo json_encode([
        "success" => false,
        "error" => "AWS credentials not found in .env file"
    ]);
    exit(1);
}

// Check command line arguments
if ($argc != 3) {
    echo json_encode([
        "success" => false,
        "error" => "Usage: ./test-face-compare-json.php path/to/selfie.jpg path/to/document.jpg"
    ]);
    exit(1);
}

$selfieImagePath = $argv[1];
$documentImagePath = $argv[2];

// Check if files exist
if (!file_exists($selfieImagePath)) {
    echo json_encode([
        "success" => false,
        "error" => "Selfie image not found: $selfieImagePath"
    ]);
    exit(1);
}

if (!file_exists($documentImagePath)) {
    echo json_encode([
        "success" => false,
        "error" => "Document image not found: $documentImagePath"
    ]);
    exit(1);
}

// Verify that the GD extension is loaded
if (!extension_loaded('gd')) {
    echo json_encode([
        "success" => false,
        "error" => "PHP GD extension is required but not loaded"
    ]);
    exit(1);
}

// Require the AWS SDK
require_once __DIR__ . '/../vendor/autoload.php';

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
 * @param string $imageType Description of image type for logging
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
            return null;
        }

        return $result['FaceDetails'];
    } catch (AwsException $e) {
        return null;
    }
}

/**
 * Compare faces between two images using AWS Rekognition
 * 
 * @param RekognitionClient $client AWS Rekognition client
 * @param string $sourceImageData Binary data of the source image (selfie)
 * @param string $targetImageData Binary data of the target image (document)
 * @param float $similarityThreshold Minimum similarity score
 * @return array Comparison results
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
        return null;
    }
}

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
$selfieImageData = readImageFile($selfieImagePath);
$documentImageData = readImageFile($documentImagePath);

// Analyze selfie image
$selfieFaces = detectFaces($rekognitionClient, $selfieImageData, "selfie");
$documentFaces = detectFaces($rekognitionClient, $documentImageData, "document");

// Check if we can proceed with comparison
if (!$selfieFaces || !$documentFaces) {
    echo json_encode([
        "success" => false,
        "error" => "Could not detect faces in one or both images",
        "details" => [
            "selfieHasFace" => !empty($selfieFaces),
            "documentHasFace" => !empty($documentFaces)
        ]
    ]);
    exit(1);
}

// Compare faces with a lower threshold for testing (60.0)
$comparisonResult = compareFaces($rekognitionClient, $selfieImageData, $documentImageData, 60.0);

if (!$comparisonResult) {
    echo json_encode([
        "success" => false,
        "error" => "Face comparison failed",
        "details" => null
    ]);
    exit(1);
}

// Process results
$response = [
    "success" => true,
    "error" => null,
    "details" => [
        "matchCount" => count($comparisonResult['FaceMatches']),
        "unmatchedCount" => count($comparisonResult['UnmatchedFaces']),
        "matches" => []
    ]
];

if (!empty($comparisonResult['FaceMatches'])) {
    foreach ($comparisonResult['FaceMatches'] as $match) {
        $matchDetails = [
            "similarity" => $match['Similarity'],
            "confidence" => $match['Face']['Confidence'],
            "boundingBox" => $match['Face']['BoundingBox'],
            "matchLevel" => ($match['Similarity'] >= 80.0 ? "STRONG" : 
                          ($match['Similarity'] >= 70.0 ? "MODERATE" : "WEAK"))
        ];
        $response["details"]["matches"][] = $matchDetails;
    }
}

// Sort matches by similarity score (highest first)
if (!empty($response["details"]["matches"])) {
    usort($response["details"]["matches"], function($a, $b) {
        return $b["similarity"] - $a["similarity"];
    });
    
    // Use the highest similarity match as the primary result
    $bestMatch = $response["details"]["matches"][0];
    $response["isMatch"] = $bestMatch["similarity"] >= 80.0;
    $response["similarity"] = $bestMatch["similarity"];
    $response["matchConfidence"] = $bestMatch["confidence"];
    $response["matchLevel"] = $bestMatch["matchLevel"];
} else {
    $response["isMatch"] = false;
    $response["similarity"] = 0;
    $response["matchConfidence"] = 0;
    $response["matchLevel"] = "NO_MATCH";
}

echo json_encode($response, JSON_PRETTY_PRINT); 
