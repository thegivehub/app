<?php
/**
 * Donate.php - Core donation processing class that handles both Square and blockchain donations
 * 
 * This class provides a unified interface for processing donations through Square payments
 * and various blockchain networks. It integrates with the existing DonationProcessor
 * and BlockchainTransactionController for transaction management.
 */

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/DonationProcessor.php";
require_once __DIR__ . "/BlockchainTransactionController.php";
require_once __DIR__ . "/vendor/autoload.php";

use Square\SquareClient;
use Square\Environment;
use Square\Exceptions\ApiException;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class Donate {
    private $db;
    private $donationProcessor;
    private $blockchainController;
    private $squareClient;
    private $supportedCryptos;

    /**
     * Initialize the Donate class with necessary dependencies
     * 
     * @param bool $testMode Whether to use test mode for payment processors
     * @throws Exception If required configuration is missing
     */
    public function __construct($testMode = false) {
        $this->db = new Database();
        $this->donationProcessor = new DonationProcessor();
        $this->blockchainController = new BlockchainTransactionController($testMode);
        
        // Initialize Square client
        $squareAccessToken = getenv("SQUARE_ACCESS_TOKEN");
        if (!$squareAccessToken) {
            throw new Exception("Square access token not configured");
        }
        
        $this->squareClient = new SquareClient([
            "accessToken" => $squareAccessToken,
            "environment" => $testMode ? Environment::SANDBOX : Environment::PRODUCTION
        ]);
        
        // Define supported cryptocurrency networks and their configurations
        $this->supportedCryptos = [
            "ETH" => [
                "name" => "Ethereum",
                "network" => $testMode ? "goerli" : "mainnet",
                "address" => getenv("ETH_WALLET_ADDRESS")
            ],
            "BTC" => [
                "name" => "Bitcoin",
                "network" => $testMode ? "testnet" : "mainnet",
                "address" => getenv("BTC_WALLET_ADDRESS")
            ],
            "XLM" => [
                "name" => "Stellar",
                "network" => $testMode ? "testnet" : "public",
                "address" => getenv("XLM_WALLET_ADDRESS")
            ]
        ];
    }

    /**
     * Process a Square payment
     * 
     * @param array $data Payment data including nonce, amount, currency, etc.
     * @return array Response with transaction details
     * @throws Exception If payment processing fails
     */
    public function processSquarePayment($data) {
        try {
            // Validate required fields
            if (empty($data["nonce"]) || empty($data["amount"]) || empty($data["currency"])) {
                throw new Exception("Missing required payment fields");
            }

            // Create payment with Square API
            $paymentsApi = $this->squareClient->getPaymentsApi();
            $money = new \Square\Models\Money();
            $money->setAmount((int)($data["amount"] * 100)); // Convert to cents
            $money->setCurrency($data["currency"]);

            $createPaymentRequest = new \Square\Models\CreatePaymentRequest(
                $data["nonce"],
                uniqid("GIVE_", true),
                $money
            );

            $response = $paymentsApi->createPayment($createPaymentRequest);

            if ($response->isSuccess()) {
                $payment = $response->getResult()->getPayment();
                
                // Prepare donation data
                $donationData = [
                    "amount" => $data["amount"],
                    "currency" => $data["currency"],
                    "campaignId" => $data["campaignId"],
                    "paymentMethod" => "square",
                    "transaction" => [
                        "id" => $payment->getId(),
                        "status" => $payment->getStatus()
                    ],
                    "donorInfo" => $data["donorInfo"] ?? null
                ];

                // Process donation through existing system
                return $this->donationProcessor->processDonation($donationData);
            } else {
                $errors = $response->getErrors();
                throw new Exception("Square payment failed: " . $errors[0]->getDetail());
            }
        } catch (ApiException $e) {
            throw new Exception("Square API error: " . $e->getMessage());
        }
    }

    /**
     * Process a cryptocurrency donation
     * 
     * @param array $data Donation data including crypto type, amount, etc.
     * @return array Response with wallet address, QR code, and transaction details
     * @throws Exception If crypto type is not supported or configuration is missing
     */
    public function processCryptoDonation($data) {
        try {
            if (empty($data["cryptoType"]) || !isset($this->supportedCryptos[$data["cryptoType"]])) {
                throw new Exception("Unsupported cryptocurrency type");
            }

            $crypto = $this->supportedCryptos[$data["cryptoType"]];
            if (empty($crypto["address"])) {
                throw new Exception("Wallet address not configured for " . $crypto["name"]);
            }

            // Create blockchain transaction record
            $txData = [
                "txHash" => null, // Will be provided by donor
                "type" => "donation",
                "status" => "pending",
                "cryptoType" => $data["cryptoType"],
                "network" => $crypto["network"],
                "expectedAmount" => $data["amount"],
                "walletAddress" => $crypto["address"],
                "campaignId" => new MongoDB\BSON\ObjectId($data["campaignId"]),
                "sourceType" => "donation",
                "metadata" => [
                    "donorInfo" => $data["donorInfo"] ?? null,
                    "campaignData" => $data["campaignData"] ?? null
                ]
            ];

            $txResult = $this->blockchainController->createTransaction($txData);
            
            if (!$txResult["success"]) {
                throw new Exception("Failed to create blockchain transaction record");
            }

            // Generate payment URI and QR code
            $paymentUri = $this->generateCryptoPaymentUri(
                $data["cryptoType"],
                $crypto["address"],
                $data["amount"] ?? null
            );

            return [
                "success" => true,
                "walletAddress" => $crypto["address"],
                "network" => $crypto["network"],
                "transactionId" => $txResult["transactionId"],
                "instructions" => $this->getCryptoInstructions($data["cryptoType"]),
                "paymentUri" => $paymentUri,
                "qrCode" => $this->generateQrCode($paymentUri)
            ];
        } catch (Exception $e) {
            throw new Exception("Crypto donation error: " . $e->getMessage());
        }
    }

    /**
     * Generate a cryptocurrency payment URI
     * 
     * @param string $cryptoType Type of cryptocurrency
     * @param string $address Wallet address
     * @param float|null $amount Optional amount to include in URI
     * @return string Payment URI for QR code
     */
    private function generateCryptoPaymentUri($cryptoType, $address, $amount = null) {
        switch ($cryptoType) {
            case "BTC":
                $uri = "bitcoin:" . $address;
                if ($amount) {
                    $uri .= "?amount=" . $amount;
                }
                break;
                
            case "ETH":
                $uri = "ethereum:" . $address;
                if ($amount) {
                    $uri .= "?value=" . $this->convertEthToWei($amount);
                }
                break;
                
            case "XLM":
                $uri = "web+stellar:pay?destination=" . $address;
                if ($amount) {
                    $uri .= "&amount=" . $amount;
                }
                break;
                
            default:
                throw new Exception("QR code generation not supported for " . $cryptoType);
        }
        
        return $uri;
    }

    /**
     * Generate QR code for payment URI
     * 
     * @param string $uri Payment URI
     * @return string Base64 encoded QR code image data URI
     */
    private function generateQrCode($uri) {
        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_L,
            'version' => 5,
            'imageBase64' => true,
        ]);

        $qrcode = new QRCode($options);
        return $qrcode->render($uri);
    }

    /**
     * Convert ETH amount to Wei
     * 
     * @param float $ethAmount Amount in ETH
     * @return string Amount in Wei
     */
    private function convertEthToWei($ethAmount) {
        return bcmul((string)$ethAmount, "1000000000000000000");
    }

    /**
     * Get cryptocurrency payment instructions
     * 
     * @param string $cryptoType Type of cryptocurrency
     * @return string Payment instructions
     */
    private function getCryptoInstructions($cryptoType) {
        $crypto = $this->supportedCryptos[$cryptoType];
        $instructions = "Please send your {$crypto["name"]} donation to the following address:\n\n";
        $instructions .= $crypto["address"] . "\n\n";
        
        switch ($cryptoType) {
            case "ETH":
                $instructions .= "Make sure to send only ETH or ERC-20 tokens to this address.";
                break;
            case "BTC":
                $instructions .= "Make sure to send only BTC to this address.";
                break;
            case "XLM":
                $instructions .= "Make sure to include the memo ID provided above when sending XLM.";
                break;
        }
        
        return $instructions;
    }

    /**
     * Get supported cryptocurrency options
     * 
     * @return array List of supported cryptocurrencies and their configurations
     */
    public function getSupportedCryptos() {
        $cryptos = [];
        foreach ($this->supportedCryptos as $symbol => $data) {
            $cryptos[$symbol] = [
                "name" => $data["name"],
                "network" => $data["network"]
            ];
        }
        return $cryptos;
    }

    /**
     * Verify a blockchain transaction
     * 
     * @param string $txHash Transaction hash
     * @param string $cryptoType Cryptocurrency type
     * @return array Transaction verification result
     */
    public function verifyBlockchainTransaction($txHash, $cryptoType) {
        if (!isset($this->supportedCryptos[$cryptoType])) {
            throw new Exception("Unsupported cryptocurrency type");
        }

        return $this->blockchainController->checkTransactionStatus($txHash);
    }
} 