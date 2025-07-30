<?php
// donation-api.php
require_once __DIR__ . '/lib/autoload.php';
require_once __DIR__ . '/lib/DonationProcessor.php';
require_once __DIR__ . '/lib/Donate.php';
require_once __DIR__ . '/lib/Security.php';
Security::sendHeaders();
if (!Security::rateLimit($_SERVER['REMOTE_ADDR'] . '/donation', 50, 60)) {
    header('Retry-After: 60');
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit;
}

// Parse the request
$method = $_SERVER["REQUEST_METHOD"];
$action = isset($_GET["action"]) ? $_GET["action"] : "process";

// Get JSON data for POST/PUT requests
$data = null;
if ($method === "POST" || $method === "PUT") {
    $data = json_decode(file_get_contents("php://input"), true);
}

// Initialize the donation handlers
$processor = new DonationProcessor();
$donate = new Donate(getenv("APP_ENV") === "development");

// Handle CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit(0);
}

// Helper function to send JSON response
function sendJson($code, $data) {
    http_response_code($code);
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}

// Route the request based on action and method
switch ($action) {
    case "process":
        if ($method === "POST") {
            // Process a new donation
            $result = $processor->processDonation($data);
            sendJson(
                $result["success"] ? 200 : 400,
                $result
            );
        } else {
            sendJson(405, ["error" => "Method not allowed"]);
        }
        break;
        
    case "crypto":
        if ($method === "GET" && isset($_GET["supported"])) {
            // Get supported cryptocurrencies
            $cryptos = $donate->getSupportedCryptos();
            sendJson(200, [
                "success" => true,
                "data" => $cryptos
            ]);
        } else if ($method === "POST") {
            // Process crypto donation
            try {
                $result = $donate->processCryptoDonation([
                    "cryptoType" => $data["cryptoType"],
                    "amount" => $data["amount"],
                    "campaignId" => $data["campaignId"],
                    "donorInfo" => [
                        "name" => $data["name"] ?? "Anonymous",
                        "email" => $data["email"] ?? null
                    ]
                ]);
                
                sendJson(200, [
                    "success" => true,
                    "walletAddress" => $result["walletAddress"],
                    "network" => $result["network"],
                    "qrCode" => $result["qrCode"],
                    "instructions" => $result["instructions"],
                    "transactionId" => $result["transactionId"]
                ]);
            } catch (Exception $e) {
                sendJson(400, [
                    "success" => false,
                    "error" => $e->getMessage()
                ]);
            }
        } else {
            sendJson(405, ["error" => "Method not allowed"]);
        }
        break;

    case "square":
        if ($method === "POST") {
            // Process Square payment
            try {
                $result = $donate->processSquarePayment([
                    "nonce" => $data["nonce"],
                    "amount" => $data["amount"],
                    "currency" => "USD",
                    "campaignId" => $data["campaignId"],
                    "donorInfo" => [
                        "name" => $data["name"] ?? "Anonymous",
                        "email" => $data["email"] ?? null
                    ]
                ]);
                
                sendJson(200, [
                    "success" => true,
                    "transactionId" => $result["transactionId"]
                ]);
            } catch (Exception $e) {
                sendJson(400, [
                    "success" => false,
                    "error" => $e->getMessage()
                ]);
            }
        } else {
            sendJson(405, ["error" => "Method not allowed"]);
        }
        break;
        
    case "status":
        if ($method === "GET") {
            // Check donation status
            $transactionId = $_GET["id"] ?? null;
            if (!$transactionId) {
                sendJson(400, ["error" => "Transaction ID required"]);
            }
            
            $result = $processor->getDonationStatus($transactionId);
            sendJson(
                $result["success"] ? 200 : 404,
                $result
            );
        } else {
            sendJson(405, ["error" => "Method not allowed"]);
        }
        break;
        
    case "update":
        if ($method === "PUT") {
            // Update donation status (e.g., from webhook)
            $transactionId = $_GET["id"] ?? $data["transactionId"] ?? null;
            $status = $data["status"] ?? null;
            
            if (!$transactionId || !$status) {
                sendJson(400, ["error" => "Transaction ID and status required"]);
            }
            
            $result = $processor->updateDonationStatus($transactionId, $status);
            sendJson(
                $result["success"] ? 200 : 404,
                $result
            );
        } else {
            sendJson(405, ["error" => "Method not allowed"]);
        }
        break;
        
    case "recurring":
        if ($method === "GET") {
            // Process due recurring donations (typically called by a cron job)
            $apiKey = $_GET["key"] ?? null;
            
            // Simple API key validation for cron job
            if ($apiKey !== getenv("CRON_API_KEY")) {
                sendJson(401, ["error" => "Unauthorized"]);
            }
            
            $result = $processor->processRecurringDonations();
            sendJson(200, $result);
        } else if ($method === "DELETE") {
            // Cancel a recurring donation
            $donationId = $_GET["id"] ?? null;
            
            if (!$donationId) {
                sendJson(400, ["error" => "Donation ID required"]);
            }
            
            $result = $processor->cancelRecurringDonation($donationId);
            sendJson(
                $result["success"] ? 200 : 404,
                $result
            );
        } else {
            sendJson(405, ["error" => "Method not allowed"]);
        }
        break;
        
    default:
        sendJson(404, ["error" => "Action not found"]);
}
