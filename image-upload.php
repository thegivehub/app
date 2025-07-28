<?php
require_once __DIR__ . '/lib/db.php';

// Set headers for CORS and JSON response
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Function to log messages
function logMessage($message, array $context = [], $level = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    $contextJson = empty($context) ? '' : json_encode($context);
    $logEntry = "[{$timestamp}] [{$level}] {$message} {$contextJson}\n";
    
    // You can adjust the log path as needed
    $logFile = __DIR__ . "/logs/" . date('Y-m-d') . ".log";
    
    // Ensure logs directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    error_log($logEntry, 3, $logFile);
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON data from the request
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

if (!$data || !isset($data['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No image data provided']);
    exit;
}

// Extract the base64 image data
$imageData = $data['image'];

// Get object type and ID information
$objectType = $data['objectType'] ?? 'campaign';
$objectId = $data['objectId'] ?? ($data['campaignId'] ?? '');

// Determine upload directory based on object type
$uploadsBaseDir = __DIR__ . '/uploads';
switch ($objectType) {
    case 'user':
        $uploadsDir = $uploadsBaseDir . '/user_images';
        $filePrefix = 'user_';
        $collectionName = 'users';
        $fieldName = 'profileImage';
        break;
    case 'document':
        $uploadsDir = $uploadsBaseDir . '/documents';
        $filePrefix = 'doc_';
        $collectionName = 'documents';
        $fieldName = 'imageUrl';
        break;
    case 'campaign':
    default:
        $uploadsDir = $uploadsBaseDir . '/campaign_images';
        $filePrefix = 'campaign_';
        $collectionName = 'campaigns';
        $fieldName = 'images';
        break;
}

// Ensure the uploads directory exists
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Check if the image data is a data URL
if (strpos($imageData, 'data:image/') !== 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image format']);
    exit;
}

// Extract image type and content
$parts = explode(',', $imageData);
$imageInfo = explode(';', $parts[0]);
$imageType = str_replace('data:image/', '', $imageInfo[0]);

// Generate a unique filename
$filename = uniqid($filePrefix) . '_' . time() . '.' . $imageType;
$filePath = $uploadsDir . '/' . $filename;

// Extract the actual base64 content and save to file
$imageContent = base64_decode($parts[1]);
if (!file_put_contents($filePath, $imageContent)) {
    logMessage('Failed to save image', ['path' => $filePath], 'error');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save image']);
    exit;
}

// Generate the URL for the saved image
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$relativePath = str_replace(__DIR__, '', $uploadsDir);
$imageUrl = $protocol . '://' . $host . $relativePath . '/' . $filename;

// Update the associated object record if an ID is provided
if (!empty($objectId)) {
    try {
        $db = new Database("givehub");
        $collection = $db->getCollection($collectionName);
        
        // Find the existing object with caching to reduce database load
        $object = $collection->findOneCached(
            ['_id' => new MongoDB\BSON\ObjectId($objectId)],
            [],
            120
        );
        
        if ($object) {
            $updateData = [];
            
            // Handle different field update strategies based on object type
            if ($objectType === 'campaign') {
                // For campaigns, append to the images array
                $images = isset($object[$fieldName]) ? $object[$fieldName] : [];
                $images[] = $imageUrl;
                $updateData = ['$set' => [$fieldName => $images]];
            } else {
                // For users and documents, replace the single image field
                $updateData = ['$set' => [$fieldName => $imageUrl]];
            }
            
            // Update the object with the new image data
            $collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($objectId)],
                $updateData
            );
        }
    } catch (Exception $e) {
        logMessage('Error updating ' . $objectType . ' with image', [
            'objectId' => $objectId,
            'error' => $e->getMessage()
        ], 'error');
    }
}

// Return success with the image URL
echo json_encode([
    'success' => true,
    'url' => $imageUrl,
    'filename' => $filename,
    'objectType' => $objectType
]);
