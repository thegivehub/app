<?php
// donation-api.php
require_once __DIR__ . '/lib/autoload.php';
require_once __DIR__ . '/lib/DonationProcessor.php';

// Parse the request
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : 'process';

// Get JSON data for POST/PUT requests
$data = null;
if ($method === 'POST' || $method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
}

// Initialize the donation processor
$processor = new DonationProcessor();

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Route the request based on action and method
switch ($action) {
    case 'process':
        if ($method === 'POST') {
            // Process a new donation
            $result = $processor->processDonation($data);
            sendJson(
                $result['success'] ? 200 : 400,
                $result
            );
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'status':
        if ($method === 'GET') {
            // Check donation status
            $transactionId = $_GET['id'] ?? null;
            if (!$transactionId) {
                sendJson(400, ['error' => 'Transaction ID required']);
            }
            
            $result = $processor->getDonationStatus($transactionId);
            sendJson(
                $result['success'] ? 200 : 404,
                $result
            );
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'update':
        if ($method === 'PUT') {
            // Update donation status (e.g., from webhook)
            $transactionId = $_GET['id'] ?? $data['transactionId'] ?? null;
            $status = $data['status'] ?? null;
            
            if (!$transactionId || !$status) {
                sendJson(400, ['error' => 'Transaction ID and status required']);
            }
            
            $result = $processor->updateDonationStatus($transactionId, $status);
            sendJson(
                $result['success'] ? 200 : 404,
                $result
            );
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'recurring':
        if ($method === 'GET') {
            // Process due recurring donations (typically called by a cron job)
            $apiKey = $_GET['key'] ?? null;
            
            // Simple API key validation for cron job
            if ($apiKey !== getenv('CRON_API_KEY')) {
                sendJson(401, ['error' => 'Unauthorized']);
            }
            
            $result = $processor->processRecurringDonations();
            sendJson(200, $result);
        } else if ($method === 'DELETE') {
            // Cancel a recurring donation
            $donationId = $_GET['id'] ?? null;
            
            if (!$donationId) {
                sendJson(400, ['error' => 'Donation ID required']);
            }
            
            $result = $processor->cancelRecurringDonation($donationId);
            sendJson(
                $result['success'] ? 200 : 404,
                $result
            );
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    default:
        sendJson(404, ['error' => 'Action not found']);
}
