<?php
/**
 * DonateApi.php - API handler for donation endpoints
 * 
 * This class provides API endpoints for handling both Square and cryptocurrency donations.
 * It works with the Donate class to process payments and the DonateButton web component
 * for the frontend interface.
 */

require_once __DIR__ . "/Donate.php";

class DonateApi {
    private $donate;

    /**
     * Initialize the API handler
     * 
     * @param bool $testMode Whether to use test mode for payment processors
     */
    public function __construct($testMode = false) {
        $this->donate = new Donate($testMode);
    }

    /**
     * Handle incoming API requests
     * 
     * @return void Outputs JSON response
     */
    public function handleRequest() {
        // Set JSON response headers
        header("Content-Type: application/json");
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type");

        // Handle preflight requests
        if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            http_response_code(200);
            exit;
        }

        // Get the request method and path
        $method = $_SERVER["REQUEST_METHOD"];
        $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
        $pathParts = explode("/", trim($path, "/"));
        $endpoint = end($pathParts);

        try {
            switch ($method) {
                case "GET":
                    $this->handleGetRequest($endpoint);
                    break;

                case "POST":
                    $this->handlePostRequest($endpoint);
                    break;

                default:
                    throw new Exception("Method not allowed");
            }
        } catch (Exception $e) {
            $this->sendErrorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Handle GET requests
     * 
     * @param string $endpoint The API endpoint
     * @return void
     * @throws Exception If endpoint is invalid
     */
    private function handleGetRequest($endpoint) {
        switch ($endpoint) {
            case "supported-cryptos":
                $cryptos = $this->donate->getSupportedCryptos();
                $this->sendResponse([
                    "success" => true,
                    "data" => $cryptos
                ]);
                break;

            default:
                throw new Exception("Invalid endpoint");
        }
    }

    /**
     * Handle POST requests
     * 
     * @param string $endpoint The API endpoint
     * @return void
     * @throws Exception If endpoint is invalid or request data is missing
     */
    private function handlePostRequest($endpoint) {
        // Get POST data
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!$data) {
            throw new Exception("Invalid request data");
        }

        // Validate common required fields
        if (empty($data["amount"]) || empty($data["campaignId"])) {
            throw new Exception("Missing required fields");
        }

        switch ($endpoint) {
            case "crypto":
                if (empty($data["cryptoType"])) {
                    throw new Exception("Cryptocurrency type is required");
                }

                $result = $this->donate->processCryptoDonation([
                    "cryptoType" => $data["cryptoType"],
                    "amount" => $data["amount"],
                    "campaignId" => $data["campaignId"],
                    "donorInfo" => [
                        "name" => $data["name"] ?? "Anonymous",
                        "email" => $data["email"] ?? null
                    ]
                ]);
                
                $this->sendResponse([
                    "success" => true,
                    "walletAddress" => $result["walletAddress"],
                    "network" => $result["network"],
                    "qrCode" => $result["qrCode"],
                    "instructions" => $result["instructions"],
                    "transactionId" => $result["transactionId"]
                ]);
                break;

            case "card":
                if (empty($data["nonce"])) {
                    throw new Exception("Payment nonce is required");
                }

                $result = $this->donate->processSquarePayment([
                    "nonce" => $data["nonce"],
                    "amount" => $data["amount"],
                    "currency" => "USD",
                    "campaignId" => $data["campaignId"],
                    "donorInfo" => [
                        "name" => $data["name"] ?? "Anonymous",
                        "email" => $data["email"] ?? null
                    ]
                ]);
                
                $this->sendResponse([
                    "success" => true,
                    "transactionId" => $result["transactionId"]
                ]);
                break;

            default:
                throw new Exception("Invalid endpoint");
        }
    }

    /**
     * Send a JSON response
     * 
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @return void
     */
    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    /**
     * Send an error response
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @return void
     */
    private function sendErrorResponse($message, $statusCode = 400) {
        $this->sendResponse([
            "success" => false,
            "error" => $message
        ], $statusCode);
    }
} 