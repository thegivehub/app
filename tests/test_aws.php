<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up logging
$logFile = __DIR__ . '/aws_test.log';
function writeLog($message) {
    global $logFile;
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
    echo $message . "\n";
}

writeLog("Starting AWS SDK test...");

require 'vendor/autoload.php';

use Aws\Rekognition\RekognitionClient;

// Check command line arguments
if ($argc < 2) {
    writeLog("Usage: php test_aws.php <selfie_image> [id_image]");
    writeLog("  - selfie_image: Path to selfie image for face detection");
    writeLog("  - id_image: (Optional) Path to ID image for face comparison");
    exit(1);
}

$selfieImage = $argv[1];
$idImage = isset($argv[2]) ? $argv[2] : null;

// Validate image files
if (!file_exists($selfieImage)) {
    writeLog("Error: Selfie image not found: {$selfieImage}");
    exit(1);
}

if ($idImage && !file_exists($idImage)) {
    writeLog("Error: ID image not found: {$idImage}");
    exit(1);
}

try {
    writeLog("Creating Rekognition client...");
    $client = new RekognitionClient([
        'version' => 'latest',
        'region'  => getenv('AWS_REGION') ?: 'us-east-1',
        'credentials' => [
            'key'    => getenv('AWS_ACCESS_KEY_ID'),
            'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
        ]
    ]);
    
    writeLog("Successfully created Rekognition client");
    writeLog("AWS Region: " . (getenv('AWS_REGION') ?: 'us-east-1'));
    writeLog("Access Key ID: " . substr(getenv('AWS_ACCESS_KEY_ID') ?: '', 0, 5) . '...');
    
    // Test face detection
    writeLog("\nTesting face detection on selfie image...");
    $selfieBytes = file_get_contents($selfieImage);
    $result = $client->detectFaces([
        'Image' => [
            'Bytes' => $selfieBytes
        ],
        'Attributes' => ['ALL']
    ]);
    
    $faces = $result['FaceDetails'];
    writeLog("Found " . count($faces) . " face(s) in selfie");
    
    foreach ($faces as $i => $face) {
        writeLog("\nFace #" . ($i + 1) . ":");
        writeLog("- Confidence: " . number_format($face['Confidence'], 2) . "%");
        writeLog("- Age Range: " . $face['AgeRange']['Low'] . "-" . $face['AgeRange']['High'] . " years");
        writeLog("- Gender: " . $face['Gender']['Value'] . " (" . number_format($face['Gender']['Confidence'], 2) . "%)");
        writeLog("- Eyes Open: " . ($face['EyesOpen']['Value'] ? "Yes" : "No") . " (" . number_format($face['EyesOpen']['Confidence'], 2) . "%)");
        writeLog("- Emotions: " . implode(", ", array_map(function($emotion) {
            return $emotion['Type'] . " (" . number_format($emotion['Confidence'], 2) . "%)";
        }, $face['Emotions'])));
    }
    
    // If ID image provided, test face comparison
    if ($idImage) {
        writeLog("\nTesting face comparison...");
        $idBytes = file_get_contents($idImage);
        
        $result = $client->compareFaces([
            'SourceImage' => [
                'Bytes' => $selfieBytes
            ],
            'TargetImage' => [
                'Bytes' => $idBytes
            ],
            'SimilarityThreshold' => 70
        ]);
        
        $matches = $result['FaceMatches'];
        $unmatched = $result['UnmatchedFaces'];
        
        if (count($matches) > 0) {
            foreach ($matches as $i => $match) {
                writeLog("\nMatch #" . ($i + 1) . ":");
                writeLog("- Similarity: " . number_format($match['Similarity'], 2) . "%");
                writeLog("- Confidence: " . number_format($match['Face']['Confidence'], 2) . "%");
                writeLog("- Bounding Box: ");
                writeLog("  - Left: " . number_format($match['Face']['BoundingBox']['Left'], 2));
                writeLog("  - Top: " . number_format($match['Face']['BoundingBox']['Top'], 2));
                writeLog("  - Width: " . number_format($match['Face']['BoundingBox']['Width'], 2));
                writeLog("  - Height: " . number_format($match['Face']['BoundingBox']['Height'], 2));
            }
        } else {
            writeLog("No matching faces found");
            if (count($unmatched) > 0) {
                writeLog("Found " . count($unmatched) . " unmatched face(s) in the target image");
            }
        }
    }
    
} catch (Exception $e) {
    writeLog("Error: " . $e->getMessage());
    writeLog("Stack trace:\n" . $e->getTraceAsString());
} 