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

// Ensure the uploads directory exists
$uploadsDir = __DIR__ . '/uploads/campaign_images';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
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
$campaignId = $data['campaignId'] ?? '';

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
$filename = uniqid('campaign_') . '_' . time() . '.' . $imageType;
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
$imageUrl = $protocol . '://' . $host . '/uploads/campaign_images/' . $filename;

// Update campaign record if campaignId is provided
if (!empty($campaignId)) {
    try {
        $db = new Database("givehub");
        $campaigns = $db->getCollection('campaigns');
        
        // Find the existing campaign
        $campaign = $campaigns->findOne(['_id' => new MongoDB\BSON\ObjectId($campaignId)]);
        
        if ($campaign) {
            // Add the new image URL to the campaign's images array
            $images = isset($campaign['images']) ? $campaign['images'] : [];
            $images[] = $imageUrl;
            
            // Update the campaign with the new images array
            $campaigns->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($campaignId)],
                ['$set' => ['images' => $images]]
            );
        }
    } catch (Exception $e) {
        logMessage('Error updating campaign with image', [
            'campaignId' => $campaignId,
            'error' => $e->getMessage()
        ], 'error');
    }
}

// Return success with the image URL
echo json_encode([
    'success' => true,
    'url' => $imageUrl,
    'filename' => $filename
]);
