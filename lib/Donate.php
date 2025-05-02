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
require_once __DIR__ . "/../vendor/autoload.php";

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
                "address" => getenv("ETHEREUM_ADDRESS")
            ],
            "BTC" => [
                "name" => "Bitcoin",
                "network" => $testMode ? "testnet" : "mainnet",
                "address" => getenv("BITCOIN_ADDRESS")
            ],
            "XLM" => [
                "name" => "Stellar",
                "network" => $testMode ? "testnet" : "public",
                "address" => getenv("STELLAR_PUBLIC_KEY")
            ]
        ];
        
        // Only include crypto options that have wallet addresses configured
        foreach ($this->supportedCryptos as $symbol => $data) {
            if (empty($data["address"])) {
                unset($this->supportedCryptos[$symbol]);
            }
        }
        
        // Check if we should enable multiple cryptocurrencies
        $enableMultipleCurrencies = getenv("ENABLE_MULTIPLE_CRYPTOCURRENCIES") === "true";
        if (!$enableMultipleCurrencies) {
            // Keep only the default currency if multiple currencies are disabled
            $defaultCurrency = getenv("DEFAULT_DONATION_CURRENCY") ?: "XLM";
            if (isset($this->supportedCryptos[$defaultCurrency])) {
                $this->supportedCryptos = [
                    $defaultCurrency => $this->supportedCryptos[$defaultCurrency]
                ];
            }
        }
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
            // Validate crypto type
            $cryptoType = strtoupper($data["cryptoType"] ?? '');
            if (empty($cryptoType) || !isset($this->supportedCryptos[$cryptoType])) {
                // If not specified or invalid, try to use default
                $defaultCurrency = getenv("DEFAULT_DONATION_CURRENCY") ?: "XLM";
                
                if (isset($this->supportedCryptos[$defaultCurrency])) {
                    $cryptoType = $defaultCurrency;
                } else {
                    throw new Exception("No supported cryptocurrencies configured");
                }
            }

            $crypto = $this->supportedCryptos[$cryptoType];
            
            // Additional validation
            if (!isset($data["campaignId"])) {
                throw new Exception("Campaign ID is required");
            }
            
            // Create unique transaction reference
            $transactionRef = uniqid("CRYPTO_", true);
            
            // Create blockchain transaction record
            $txData = [
                "txHash" => null, // Will be provided by donor later
                "type" => "payment", // Use "payment" as it's in the allowed list
                "status" => "pending",
                "cryptoType" => $cryptoType,
                "network" => $crypto["network"],
                "expectedAmount" => $data["amount"] ?? null,
                "walletAddress" => $crypto["address"],
                "campaignId" => new MongoDB\BSON\ObjectId($data["campaignId"]),
                "sourceType" => "donation",
                "reference" => $transactionRef,
                "metadata" => [
                    "donorInfo" => $data["donorInfo"] ?? null,
                    "campaignData" => $data["campaignData"] ?? null,
                    "isAnonymous" => $data["isAnonymous"] ?? false,
                    "message" => $data["message"] ?? null,
                    "isTestDonation" => $data["isTestDonation"] ?? false // Track if this is a test donation
                ]
            ];

            // Add user ID if available
            if (isset($data["userId"])) {
                $txData["userId"] = new MongoDB\BSON\ObjectId($data["userId"]);
            }

            $txResult = $this->blockchainController->createTransaction($txData);
            
            if (!$txResult["success"]) {
                throw new Exception("Failed to create blockchain transaction record: " . 
                    ($txResult["error"] ?? "Unknown error"));
            }

            // Generate payment URI and QR code
            // Include reference in payment URI when possible
            $paymentUri = $this->generateCryptoPaymentUri(
                $cryptoType,
                $crypto["address"],
                $data["amount"] ?? null,
                $transactionRef
            );

            return [
                "success" => true,
                "cryptoType" => $cryptoType,
                "cryptoName" => $crypto["name"],
                "walletAddress" => $crypto["address"],
                "network" => $crypto["network"],
                "isTestnet" => $crypto["network"] !== "mainnet" && $crypto["network"] !== "public",
                "transactionId" => $txResult["transactionId"],
                "reference" => $transactionRef,
                "instructions" => $this->getCryptoInstructions($cryptoType, $transactionRef),
                "paymentUri" => $paymentUri,
                "qrCode" => $this->generateQrCode($paymentUri),
                "expectedAmount" => $data["amount"] ?? null
            ];
        } catch (Exception $e) {
            return [
                "success" => false,
                "error" => "Crypto donation error: " . $e->getMessage()
            ];
        }
    }

    /**
     * Generate a cryptocurrency payment URI
     * 
     * @param string $cryptoType Type of cryptocurrency
     * @param string $address Wallet address
     * @param float|null $amount Optional amount to include in URI
     * @param string|null $reference Optional transaction reference to include
     * @return string Payment URI for QR code
     */
    private function generateCryptoPaymentUri($cryptoType, $address, $amount = null, $reference = null) {
        switch ($cryptoType) {
            case "BTC":
                $uri = "bitcoin:" . $address;
                $params = [];
                
                if ($amount) {
                    $params[] = "amount=" . $amount;
                }
                
                if ($reference) {
                    $params[] = "message=GiveHub:" . $reference;
                }
                
                if (!empty($params)) {
                    $uri .= "?" . implode("&", $params);
                }
                break;
                
            case "ETH":
                $uri = "ethereum:" . $address;
                $params = [];
                
                if ($amount) {
                    $params[] = "value=" . $this->convertEthToWei($amount);
                }
                
                if ($reference) {
                    $params[] = "data=GiveHub:" . $reference;
                }
                
                if (!empty($params)) {
                    $uri .= "?" . implode("&", $params);
                }
                break;
                
            case "XLM":
                $uri = "web+stellar:pay?destination=" . $address;
                
                if ($amount) {
                    $uri .= "&amount=" . $amount;
                }
                
                if ($reference) {
                    $uri .= "&memo=" . urlencode($reference);
                    $uri .= "&memo_type=text";
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
        try {
            if (!class_exists('\\chillerlan\\QRCode\\QROptions')) {
                // Return placeholder if QR code library is not available
                return 'QR code generation not available - library not installed';
            }
            
            $options = new QROptions([
                'outputType' => QRCode::OUTPUT_MARKUP_SVG,
                'eccLevel' => QRCode::ECC_L,
                'version' => 5,
                'imageBase64' => true,
            ]);

            $qrcode = new QRCode($options);
            return $qrcode->render($uri);
        } catch (Exception $e) {
            // Return a placeholder if QR code generation fails
            return 'QR code generation failed: ' . $e->getMessage();
        }
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
     * @param string|null $reference Optional transaction reference
     * @return string Payment instructions
     */
    private function getCryptoInstructions($cryptoType, $reference = null) {
        $crypto = $this->supportedCryptos[$cryptoType];
        $network = $crypto["network"] !== "mainnet" && $crypto["network"] !== "public" 
            ? " (" . strtoupper($crypto["network"]) . ")" 
            : "";
            
        $instructions = "Please send your {$crypto["name"]}{$network} donation to the following address:\n\n";
        $instructions .= $crypto["address"] . "\n\n";
        
        if ($reference) {
            $instructions .= "Transaction Reference: " . $reference . "\n\n";
        }
        
        switch ($cryptoType) {
            case "ETH":
                $instructions .= "IMPORTANT:\n";
                $instructions .= "• Make sure to send only ETH or ERC-20 tokens to this address.\n";
                if ($crypto["network"] !== "mainnet") {
                    $instructions .= "• This is a " . strtoupper($crypto["network"]) . " address. Do not send mainnet ETH here.\n";
                }
                break;
                
            case "BTC":
                $instructions .= "IMPORTANT:\n";
                $instructions .= "• Make sure to send only BTC to this address.\n";
                if ($crypto["network"] !== "mainnet") {
                    $instructions .= "• This is a TESTNET address. Do not send mainnet BTC here.\n";
                }
                break;
                
            case "XLM":
                $instructions .= "IMPORTANT:\n";
                if ($reference) {
                    $instructions .= "• When sending XLM, you MUST include this memo: " . $reference . "\n";
                }
                if ($crypto["network"] !== "public") {
                    $instructions .= "• This is a TESTNET address. Do not send public network XLM here.\n";
                }
                break;
        }
        
        $instructions .= "\nScan the QR code to quickly open this address in your wallet app.";
        
        return $instructions;
    }

    /**
     * Get supported cryptocurrency options
     * 
     * @param bool $includeAddresses Whether to include wallet addresses in the response
     * @return array List of supported cryptocurrencies and their configurations
     */
    public function getSupportedCryptos($includeAddresses = false) {
        $cryptos = [];
        
        // If none are configured, provide instructions
        if (empty($this->supportedCryptos)) {
            return [
                'error' => 'No cryptocurrency wallets configured',
                'instructions' => 'Set environment variables for wallet addresses in your .env file'
            ];
        }
        
        // Format response with configured crypto options
        foreach ($this->supportedCryptos as $symbol => $data) {
            $cryptoData = [
                "name" => $data["name"],
                "network" => $data["network"],
                "isTestnet" => $data["network"] !== "mainnet" && $data["network"] !== "public",
                "isDefault" => $symbol === (getenv("DEFAULT_DONATION_CURRENCY") ?: "XLM")
            ];
            
            // Only include addresses if specifically requested (admin features)
            if ($includeAddresses) {
                $cryptoData["address"] = $data["address"];
            }
            
            $cryptos[$symbol] = $cryptoData;
        }
        
        // Add configuration status
        $cryptos["_config"] = [
            "multiCurrencyEnabled" => getenv("ENABLE_MULTIPLE_CRYPTOCURRENCIES") === "true",
            "defaultCurrency" => getenv("DEFAULT_DONATION_CURRENCY") ?: "XLM"
        ];
        
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
