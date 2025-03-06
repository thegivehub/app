<?php
// Add this to a new file called profile-completion.php in your API directory
require_once __DIR__ . '/lib/ProfileCompletion.php';
header('Content-Type: application/json');

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if this is an API request for profile completion
$requestUri = $_SERVER['REQUEST_URI'];
if (strpos($requestUri, '/api/user/profile-completion') !== false) {
    $profileCompletion = new ProfileCompletion();
    $result = $profileCompletion->getProfileCompletionEndpoint();
    echo json_encode($result);
    exit;
}
