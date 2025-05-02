<?php
/**
 * MultiCurrencyWallet.php
 * Handles multiple cryptocurrency wallet operations for donations
 * Supports Stellar (XLM), Ethereum (ETH), and Bitcoin (BTC)
 * API endpoints are automatically created for public methods
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Collection.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Use Soneso Stellar SDK for XLM
use Soneso\StellarSDK\Keypair as StellarKeypair;
use Soneso\StellarSDK\StellarSDK;

class MultiCurrencyWallet extends Collection {
    // Wallet configuration
    private $config = [];
    
    // Networks status (testnet vs mainnet)
    private $networks = [
        'stellar' => 'testnet',
        'ethereum' => 'testnet',
        'bitcoin' => 'testnet'
    ];
    
    // Network API endpoints
    private $apiEndpoints = [
        'stellar' => [
            'testnet' => 'https://horizon-testnet.stellar.org',
            'public' => 'https://horizon.stellar.org'
        ],
        'ethereum' => [
            'testnet' => 'https://goerli.infura.io/v3/', // Needs Infura project ID
            'mainnet' => 'https://mainnet.infura.io/v3/' // Needs Infura project ID
        ],
        'bitcoin' => [
            'testnet' => 'https://blockstream.info/testnet/api',
            'mainnet' => 'https://blockstream.info/api'
        ]
    ];

    // Supported currencies
    private $supportedCurrencies = ['XLM', 'ETH', 'BTC'];
    
    // Default currency
    private $defaultCurrency = 'XLM';
    
    // Enable multiple cryptocurrencies flag
    private $enableMultipleCurrencies = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('wallets');
        
        // Load configuration from environment variables
        $this->loadConfig();
        
        // Set up network configuration
        $this->setupNetworks();
    }
    
    /**
     * Load wallet configuration from environment variables
     */
    private function loadConfig() {
        // Stellar configuration
        $this->config['stellar'] = [
            'publicKey' => getenv('STELLAR_PUBLIC_KEY') ?: null,
            'secretKey' => getenv('STELLAR_SECRET_KEY') ?: null,
            'network' => getenv('STELLAR_NETWORK') ?: 'testnet'
        ];
        
        // Ethereum configuration
        $this->config['ethereum'] = [
            'address' => getenv('ETHEREUM_ADDRESS') ?: null,
            'privateKey' => getenv('ETHEREUM_PRIVATE_KEY') ?: null,
            'network' => getenv('ETHEREUM_NETWORK') ?: 'testnet',
            'infuraProjectId' => getenv('ETHEREUM_INFURA_PROJECT_ID') ?: null
        ];
        
        // Bitcoin configuration
        $this->config['bitcoin'] = [
            'address' => getenv('BITCOIN_ADDRESS') ?: null,
            'privateKey' => getenv('BITCOIN_PRIVATE_KEY') ?: null,
            'network' => getenv('BITCOIN_NETWORK') ?: 'testnet'
        ];
        
        // General settings
        $this->defaultCurrency = getenv('DEFAULT_DONATION_CURRENCY') ?: 'XLM';
        $this->enableMultipleCurrencies = (getenv('ENABLE_MULTIPLE_CRYPTOCURRENCIES') === 'true');
        
        // Validate default currency
        if (!in_array($this->defaultCurrency, $this->supportedCurrencies)) {
            $this->defaultCurrency = 'XLM'; // Fall back to XLM if invalid
        }
    }
    
    /**
     * Setup network configurations based on environment
     */
    private function setupNetworks() {
        // Set network mode for each cryptocurrency
        $this->networks['stellar'] = $this->config['stellar']['network'];
        $this->networks['ethereum'] = $this->config['ethereum']['network'];
        $this->networks['bitcoin'] = $this->config['bitcoin']['network'];
        
        // Update Ethereum API endpoint with Infura project ID if available
        if ($this->config['ethereum']['infuraProjectId']) {
            $infuraId = $this->config['ethereum']['infuraProjectId'];
            $this->apiEndpoints['ethereum']['testnet'] .= $infuraId;
            $this->apiEndpoints['ethereum']['mainnet'] .= $infuraId;
        }
    }
    
    /**
     * API: Get donation wallet address for the specified cryptocurrency
     * Endpoint: /api.php/MultiCurrencyWallet/getDonationAddress
     * @param array $params Request parameters including currency
     * @return array Donation address or error
     */
    public function getDonationAddress($params = null) {
        try {
            // Get currency from parameters or use default
            $currency = isset($params['currency']) ? strtoupper($params['currency']) : $this->defaultCurrency;
            
            // Check if currency is supported
            if (!in_array($currency, $this->supportedCurrencies)) {
                return [
                    'success' => false,
                    'error' => "Unsupported currency: {$currency}"
                ];
            }
            
            // Check if multiple currencies are enabled when requesting non-default
            if ($currency !== $this->defaultCurrency && !$this->enableMultipleCurrencies) {
                return [
                    'success' => false,
                    'error' => "Only {$this->defaultCurrency} is currently supported for donations"
                ];
            }
            
            // Get address based on currency
            $address = $this->getAddressForCurrency($currency);
            
            if (!$address) {
                return [
                    'success' => false,
                    'error' => "Donation address not configured for {$currency}"
                ];
            }
            
            // Get network information
            $network = $this->getNetworkForCurrency($currency);
            
            return [
                'success' => true,
                'donationAddress' => [
                    'currency' => $currency,
                    'address' => $address,
                    'network' => $network,
                    'isTestnet' => $this->isTestnet($currency)
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * API: Get all available donation currencies
     * Endpoint: /api.php/MultiCurrencyWallet/getAvailableCurrencies
     * @return array List of available currencies for donations
     */
    public function getAvailableCurrencies() {
        try {
            $currencies = [];
            
            // If multiple currencies are disabled, only return default
            if (!$this->enableMultipleCurrencies) {
                $defaultCurrency = $this->defaultCurrency;
                $currencies[] = [
                    'currency' => $defaultCurrency,
                    'isDefault' => true,
                    'isTestnet' => $this->isTestnet($defaultCurrency),
                    'network' => $this->getNetworkForCurrency($defaultCurrency)
                ];
            } else {
                // Check each supported currency if it's configured
                foreach ($this->supportedCurrencies as $currency) {
                    $address = $this->getAddressForCurrency($currency);
                    
                    // Only include currencies that have addresses configured
                    if ($address) {
                        $currencies[] = [
                            'currency' => $currency,
                            'isDefault' => ($currency === $this->defaultCurrency),
                            'isTestnet' => $this->isTestnet($currency),
                            'network' => $this->getNetworkForCurrency($currency)
                        ];
                    }
                }
            }
            
            return [
                'success' => true,
                'currencies' => $currencies
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * API: Check wallet balance for a specified cryptocurrency
     * Endpoint: /api.php/MultiCurrencyWallet/checkBalance
     * @param array $params Request parameters including currency
     * @return array Balance information or error
     */
    public function checkBalance($params = null) {
        try {
            // Get currency from parameters or use default
            $currency = isset($params['currency']) ? strtoupper($params['currency']) : $this->defaultCurrency;
            
            // Check if currency is supported
            if (!in_array($currency, $this->supportedCurrencies)) {
                return [
                    'success' => false,
                    'error' => "Unsupported currency: {$currency}"
                ];
            }
            
            // Get address for the currency
            $address = $this->getAddressForCurrency($currency);
            
            if (!$address) {
                return [
                    'success' => false,
                    'error' => "Wallet address not configured for {$currency}"
                ];
            }
            
            // Check balance based on currency
            switch ($currency) {
                case 'XLM':
                    $balance = $this->checkStellarBalance($address);
                    break;
                case 'ETH':
                    $balance = $this->checkEthereumBalance($address);
                    break;
                case 'BTC':
                    $balance = $this->checkBitcoinBalance($address);
                    break;
                default:
                    return [
                        'success' => false,
                        'error' => "Balance check not implemented for {$currency}"
                    ];
            }
            
            return $balance;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get wallet address for the specified cryptocurrency
     * @param string $currency Currency code (XLM, ETH, BTC)
     * @return string|null Wallet address or null if not configured
     */
    private function getAddressForCurrency($currency) {
        switch ($currency) {
            case 'XLM':
                return $this->config['stellar']['publicKey'];
            case 'ETH':
                return $this->config['ethereum']['address'];
            case 'BTC':
                return $this->config['bitcoin']['address'];
            default:
                return null;
        }
    }
    
    /**
     * Get network name for the specified cryptocurrency
     * @param string $currency Currency code (XLM, ETH, BTC)
     * @return string Network name
     */
    private function getNetworkForCurrency($currency) {
        switch ($currency) {
            case 'XLM':
                return $this->networks['stellar'] === 'public' ? 'Stellar Mainnet' : 'Stellar Testnet';
            case 'ETH':
                return $this->networks['ethereum'] === 'mainnet' ? 'Ethereum Mainnet' : 'Ethereum ' . ucfirst($this->networks['ethereum']);
            case 'BTC':
                return $this->networks['bitcoin'] === 'mainnet' ? 'Bitcoin Mainnet' : 'Bitcoin Testnet';
            default:
                return 'Unknown';
        }
    }
    
    /**
     * Check if the currency is configured to use testnet
     * @param string $currency Currency code (XLM, ETH, BTC)
     * @return bool True if using testnet
     */
    private function isTestnet($currency) {
        switch ($currency) {
            case 'XLM':
                return $this->networks['stellar'] !== 'public';
            case 'ETH':
                return $this->networks['ethereum'] !== 'mainnet';
            case 'BTC':
                return $this->networks['bitcoin'] !== 'mainnet';
            default:
                return true; // Default to testnet for safety
        }
    }
    
    /**
     * Check Stellar account balance
     * @param string $address Stellar account public key
     * @return array Balance information or error
     */
    private function checkStellarBalance($address) {
        try {
            // Get Horizon URL based on network
            $horizonUrl = $this->apiEndpoints['stellar'][$this->networks['stellar']];
            
            // Initialize Stellar SDK
            $sdk = new StellarSDK($horizonUrl);
            
            // Check account details
            $accountData = $sdk->accounts()->account($address);
            
            if (!$accountData) {
                throw new \Exception("Unable to fetch Stellar account data");
            }
            
            // Process balances
            $balances = [];
            $nativeBalance = '0';
            
            foreach ($accountData->getBalances() as $balance) {
                if ($balance->getAssetType() === 'native') {
                    $nativeBalance = $balance->getBalance();
                    $balances[] = [
                        'asset' => 'XLM',
                        'balance' => $nativeBalance,
                        'type' => 'native'
                    ];
                } else {
                    $balances[] = [
                        'asset' => $balance->getAssetCode(),
                        'issuer' => $balance->getAssetIssuer(),
                        'balance' => $balance->getBalance(),
                        'type' => $balance->getAssetType()
                    ];
                }
            }
            
            return [
                'success' => true,
                'balance' => [
                    'currency' => 'XLM',
                    'address' => $address,
                    'network' => $this->getNetworkForCurrency('XLM'),
                    'isTestnet' => $this->isTestnet('XLM'),
                    'amount' => $nativeBalance,
                    'detailedBalances' => $balances
                ]
            ];
        } catch (\Exception $e) {
            // Account might not be activated yet
            if (strpos($e->getMessage(), 'Resource Missing') !== false) {
                return [
                    'success' => true,
                    'balance' => [
                        'currency' => 'XLM',
                        'address' => $address,
                        'network' => $this->getNetworkForCurrency('XLM'),
                        'isTestnet' => $this->isTestnet('XLM'),
                        'amount' => '0',
                        'detailedBalances' => [
                            [
                                'asset' => 'XLM',
                                'balance' => '0',
                                'type' => 'native'
                            ]
                        ],
                        'status' => 'account_not_activated'
                    ]
                ];
            }
            
            return [
                'success' => false,
                'error' => "Error checking Stellar balance: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check Ethereum account balance
     * @param string $address Ethereum wallet address
     * @return array Balance information or error
     */
    private function checkEthereumBalance($address) {
        try {
            // Get network and API endpoint
            $network = $this->networks['ethereum'];
            $apiUrl = $this->apiEndpoints['ethereum'][$network];
            
            // Check if Infura project ID is configured
            if (strpos($apiUrl, 'infura.io') !== false && !$this->config['ethereum']['infuraProjectId']) {
                return [
                    'success' => false,
                    'error' => "Infura project ID is required for Ethereum balance check"
                ];
            }
            
            // For now, return a placeholder response
            // TODO: Implement actual ETH balance check using Web3 library
            return [
                'success' => true,
                'balance' => [
                    'currency' => 'ETH',
                    'address' => $address,
                    'network' => $this->getNetworkForCurrency('ETH'),
                    'isTestnet' => $this->isTestnet('ETH'),
                    'amount' => '0',
                    'status' => 'implementation_pending',
                    'message' => 'ETH balance check requires Web3 implementation'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Error checking Ethereum balance: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check Bitcoin account balance
     * @param string $address Bitcoin wallet address
     * @return array Balance information or error
     */
    private function checkBitcoinBalance($address) {
        try {
            // Get network and API endpoint
            $network = $this->networks['bitcoin'];
            $apiUrl = $this->apiEndpoints['bitcoin'][$network];
            
            // Construct API URL for address balance
            $url = "{$apiUrl}/address/{$address}";
            
            // Make API request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => "Failed to fetch Bitcoin balance (HTTP {$httpCode})"
                ];
            }
            
            // Parse response
            $data = json_decode($response, true);
            
            if (!$data) {
                return [
                    'success' => false,
                    'error' => "Invalid response from Bitcoin API"
                ];
            }
            
            // Extract balance in satoshis and convert to BTC
            $balanceSat = $data['chain_stats']['funded_txo_sum'] - $data['chain_stats']['spent_txo_sum'];
            $balanceBTC = $balanceSat / 100000000; // Convert satoshis to BTC
            
            return [
                'success' => true,
                'balance' => [
                    'currency' => 'BTC',
                    'address' => $address,
                    'network' => $this->getNetworkForCurrency('BTC'),
                    'isTestnet' => $this->isTestnet('BTC'),
                    'amount' => (string)$balanceBTC,
                    'amountSat' => (string)$balanceSat,
                    'receivedTx' => $data['chain_stats']['tx_count'],
                    'detailedStats' => [
                        'funded' => $data['chain_stats']['funded_txo_sum'],
                        'spent' => $data['chain_stats']['spent_txo_sum']
                    ]
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Error checking Bitcoin balance: " . $e->getMessage()
            ];
        }
    }
}