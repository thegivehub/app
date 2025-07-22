<?php
// api/kyc.php
require_once __DIR__ . '/../lib/KycController.php';

// Start session and handle CORS
session_start();
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Parse the endpoint action from the URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Extract the action (last part of the URL path)
$action = end($pathParts);

// Initialize KYC controller
$kycController = new KycController();

// Route the request to the appropriate handler
try {
    switch ($action) {
        case 'initiate':
            // POST /api/kyc/initiate - Start verification process
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $kycController->initiateVerification();
            break;
            
        case 'status':
            // GET /api/kyc/status - Get verification status
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $kycController->getVerificationStatus();
            break;
            
        case 'callback':
            // POST /api/kyc/callback - Webhook callback from Jumio
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $kycController->handleWebhook();
            break;

        case 'pending':
            // GET /api/kyc/pending - List verifications awaiting review
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $kycController->listPending();
            break;

        case 'review':
            // POST /api/kyc/review - Approve or reject a verification
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $kycController->reviewVerification();
            break;
            
        case 'admin-override':
            // POST /api/kyc/admin-override - Admin override for verification
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $kycController->adminOverride();
            break;
            
        case 'report':
            // GET /api/kyc/report - Generate KYC verification report
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $kycController->generateReport();
            break;

        case 'compliance':
            // GET /api/kyc/compliance - Generate compliance report
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $kycController->generateComplianceReport();
            break;

        case 'liveness':
            // POST /api/kyc/liveness - Perform secondary liveness check
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $kycController->performLivenessCheck();
            break;

        case 'risk-score':
            // POST /api/kyc/risk-score - Calculate and update risk score
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            $kycController->updateRiskScore();
            break;
            
        default:
            // Route not found
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
