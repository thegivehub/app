<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        if (!empty($name) && !empty($value)) {
            putenv(sprintf('%s=%s', trim($name), trim($value)));
            $_ENV[trim($name)] = trim($value);
            $_SERVER[trim($name)] = trim($value);
        }
    }
}

// Check if AWS SDK is available
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("Error: vendor/autoload.php not found. Please run 'composer install' first.\n");
}

require_once __DIR__ . '/vendor/autoload.php';

// Check if AWS classes are available
if (!class_exists('Aws\Rekognition\RekognitionClient')) {
    die("Error: AWS SDK classes not found. Please check composer installation.\n");
}

require_once __DIR__ . '/lib/AWSRekognitionClient.php';

try {
    echo "Testing AWS Rekognition connection...\n";
    
    // Verify AWS credentials are loaded
    echo "\nAWS Configuration:\n";
    $region = getenv('AWS_REGION');
    $accessKey = getenv('AWS_ACCESS_KEY_ID');
    $secretKey = getenv('AWS_SECRET_ACCESS_KEY');
    
    echo "Region: " . ($region ?: 'NOT SET') . "\n";
    echo "Access Key: " . ($accessKey ? substr($accessKey, 0, 5) . "..." : 'NOT SET') . "\n";
    echo "Secret Key: " . ($secretKey ? substr($secretKey, 0, 5) . "..." : 'NOT SET') . "\n";
    
    if (!$region || !$accessKey || !$secretKey) {
        die("Error: AWS credentials not properly loaded from .env file\n");
    }
    
    // Initialize the client
    $client = new AWSRekognitionClient();
    echo "✓ Successfully initialized AWS Rekognition client\n";
    
    // Test directory paths
    $uploadDir = getenv('UPLOAD_DIR') ?: __DIR__ . '/uploads';
    $selfieDir = getenv('SELFIE_DIR') ?: $uploadDir . '/selfies';
    $documentDir = getenv('DOCUMENT_DIR') ?: $uploadDir . '/documents';
    
    echo "\nChecking directories:\n";
    echo "Upload dir: " . $uploadDir . " - " . (is_dir($uploadDir) ? "✓ exists" : "✗ missing") . "\n";
    echo "Selfie dir: " . $selfieDir . " - " . (is_dir($selfieDir) ? "✓ exists" : "✗ missing") . "\n";
    echo "Document dir: " . $documentDir . " - " . (is_dir($documentDir) ? "✓ exists" : "✗ missing") . "\n";
    
    // Create directories if missing
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    if (!is_dir($selfieDir)) mkdir($selfieDir, 0755, true);
    if (!is_dir($documentDir)) mkdir($documentDir, 0755, true);
    
    echo "\nSystem ready for testing with actual images.\n";
    echo "Please provide a selfie image and an ID document image to test face comparison.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} 