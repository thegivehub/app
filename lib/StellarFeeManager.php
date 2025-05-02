<?php
/**
 * StellarFeeManager.php
 * A utility class for managing Stellar transaction fees dynamically
 */
require_once __DIR__ . '/../vendor/autoload.php';

// Use Soneso Stellar SDK
use Soneso\StellarSDK\Keypair;
use Soneso\StellarSDK\Server;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\StellarSDK;
use Soneso\StellarSDK\TransactionBuilder;
use Soneso\StellarSDK\FeeBumpTransaction;
use Soneso\StellarSDK\FeeBumpTransactionBuilder;

class StellarFeeManager {
    // Default base fee (in stroops, 1 XLM = 10,000,000 stroops)
    private $defaultBaseFee;
    
    // Fee multipliers for different network conditions
    private $feeMultipliers;
    
    // Cache duration in milliseconds
    private $cacheDuration;
    
    // Initialize cache for fee stats
    private $feeStatsCache;
    
    // Horizon server connection URL
    private $horizonUrl;
    
    // Stellar SDK instance
    private $stellarServer;
    
    // Network instance
    private $network;
    
    // Testnet flag
    private $isTestnet;
    
    // Set up logging
    private $enableLogging;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration options
     */
    public function __construct($config = []) {
        // Default base fee (in stroops)
        $this->defaultBaseFee = $config['defaultBaseFee'] ?? 100;
        
        // Fee multipliers for different network conditions
        $this->feeMultipliers = [
            'low' => $config['lowMultiplier'] ?? 1.0,       // Normal network conditions
            'medium' => $config['mediumMultiplier'] ?? 1.5,  // Moderate congestion
            'high' => $config['highMultiplier'] ?? 2.0,      // High congestion
            'critical' => $config['criticalMultiplier'] ?? 3.0 // Severe congestion
        ];
        
        // Cache duration in milliseconds
        $this->cacheDuration = $config['cacheDuration'] ?? 60000; // 1 minute default
        
        // Initialize cache for fee stats
        $this->feeStatsCache = [
            'timestamp' => 0,
            'data' => null
        ];
        
        // Initialize Horizon server connection
        $this->isTestnet = $config['useTestnet'] ?? true;
        $this->horizonUrl = $config['horizonUrl'] ?? 
            ($this->isTestnet ? 'https://horizon-testnet.stellar.org' : 'https://horizon.stellar.org');
        
        // Initialize Stellar SDK
        $this->stellarServer = new StellarSDK($this->horizonUrl);
        $this->network = $this->isTestnet ? Network::testnet() : Network::public();
        
        // Set up logging
        $this->enableLogging = $config['enableLogging'] ?? false;
    }
    
    /**
     * Log messages if logging is enabled
     * 
     * @param string $message Message to log
     * @param mixed $data Optional data to log
     */
    private function log($message, $data = null) {
        if ($this->enableLogging) {
            if ($data) {
                error_log("[StellarFeeManager] $message " . json_encode($data));
            } else {
                error_log("[StellarFeeManager] $message");
            }
        }
    }
    
    /**
     * Get current fee stats from Horizon
     * 
     * @param bool $forceRefresh Force refresh the cache
     * @return array Fee statistics
     */
    public function getFeeStats($forceRefresh = false) {
        $now = round(microtime(true) * 1000);
        
        // Check if we have a cached value that's still valid
        if (!$forceRefresh && 
            $this->feeStatsCache['data'] && 
            $now - $this->feeStatsCache['timestamp'] < $this->cacheDuration) {
            $this->log('Using cached fee stats');
            return $this->feeStatsCache['data'];
        }
        
        try {
            $this->log('Fetching fee stats from Horizon');
            
            // Use the Stellar SDK to fetch fee stats
            $feeStats = $this->stellarServer->getFeeStats();
            
            // Convert the response to an associative array format similar to the JS version
            $feeStatsArray = [
                'fee_charged' => [
                    'max' => $feeStats->getMaxFee(),
                    'min' => $feeStats->getMinFee(),
                    'mode' => $feeStats->getModeFee(),
                    'p10' => $feeStats->getFeePercentile(10),
                    'p50' => $feeStats->getFeePercentile(50),
                    'p90' => $feeStats->getFeePercentile(90),
                    'p95' => $feeStats->getFeePercentile(95),
                    'p99' => $feeStats->getFeePercentile(99)
                ]
            ];
            
            // Update cache
            $this->feeStatsCache = [
                'timestamp' => $now,
                'data' => $feeStatsArray
            ];
            
            return $feeStatsArray;
        } catch (\Exception $error) {
            $this->log('Error fetching fee stats', $error->getMessage());
            
            // If we have cached data, return it despite being expired
            if ($this->feeStatsCache['data']) {
                $this->log('Using expired cached fee stats due to error');
                return $this->feeStatsCache['data'];
            }
            
            // Otherwise, return a default structure
            return [
                'fee_charged' => [
                    'max' => $this->defaultBaseFee * 2,
                    'min' => $this->defaultBaseFee,
                    'mode' => $this->defaultBaseFee,
                    'p10' => $this->defaultBaseFee,
                    'p50' => $this->defaultBaseFee,
                    'p90' => $this->defaultBaseFee * 2,
                    'p95' => $this->defaultBaseFee * 2,
                    'p99' => $this->defaultBaseFee * 3
                ]
            ];
        }
    }
    
    /**
     * Analyze network congestion based on fee stats
     * 
     * @param array $feeStats Fee statistics from Horizon
     * @return string Congestion level: 'low', 'medium', 'high', or 'critical'
     */
    public function analyzeCongestion($feeStats) {
        // If no fee stats, assume low congestion
        if (!$feeStats || !isset($feeStats['fee_charged'])) {
            return 'low';
        }
        
        $p10 = (int)$feeStats['fee_charged']['p10'];
        $p50 = (int)$feeStats['fee_charged']['p50'];
        $p90 = (int)$feeStats['fee_charged']['p90'];
        
        // Calculate congestion based on fee percentiles
        if ($p90 > $p10 * 5) {
            // Severe congestion - p90 is 5x+ higher than p10
            return 'critical';
        } else if ($p90 > $p10 * 3) {
            // High congestion - p90 is 3-5x higher than p10
            return 'high';
        } else if ($p90 > $p10 * 1.5) {
            // Moderate congestion - p90 is 1.5-3x higher than p10
            return 'medium';
        } else {
            // Low congestion - fees are relatively uniform
            return 'low';
        }
    }
    
    /**
     * Get recommended fee based on network conditions
     * 
     * @param array $options Optional parameters
     * @return int Recommended fee in stroops
     */
    public function getRecommendedFee($options = []) {
        $forceRefresh = $options['forceRefresh'] ?? false;
        $priorityLevel = $options['priorityLevel'] ?? 'medium';
        
        try {
            // Get current fee stats
            $feeStats = $this->getFeeStats($forceRefresh);
            
            // Analyze network congestion
            $congestion = $this->analyzeCongestion($feeStats);
            $this->log("Network congestion level: $congestion");
            
            // Get base fee based on priority and congestion
            $baseFee = $this->defaultBaseFee;
            
            switch ($priorityLevel) {
                case 'low':
                    // Use p10 (lower percentile) for low priority
                    $baseFee = (int)$feeStats['fee_charged']['p10'];
                    break;
                case 'high':
                    // Use p90 (higher percentile) for high priority
                    $baseFee = (int)$feeStats['fee_charged']['p90'];
                    break;
                case 'medium':
                default:
                    // Use p50 (median) for medium priority
                    $baseFee = (int)$feeStats['fee_charged']['p50'];
                    break;
            }
            
            // Apply multiplier based on congestion
            $multiplier = $this->feeMultipliers[$congestion];
            $recommendedFee = ceil($baseFee * $multiplier);
            
            // Ensure fee is at least the minimum base fee
            $recommendedFee = max($recommendedFee, $this->defaultBaseFee);
            
            $this->log("Recommended fee: $recommendedFee stroops (priority: $priorityLevel, congestion: $congestion)");
            return $recommendedFee;
        } catch (Exception $error) {
            $this->log('Error getting recommended fee', $error->getMessage());
            
            // In case of error, use default base fee with priority multiplier
            $priorityMultipliers = [
                'low' => 1.0,
                'medium' => 1.5,
                'high' => 2.0
            ];
            
            $multiplier = $priorityMultipliers[$priorityLevel] ?? 1.5;
            return ceil($this->defaultBaseFee * $multiplier);
        }
    }
    
    /**
     * Create a fee bump transaction to increase the fee on a pending transaction
     * Using the soneso Stellar SDK
     * 
     * @param string $sourceSecret Secret key of the fee source account
     * @param object $innerTransaction Original transaction to bump
     * @return FeeBumpTransaction Fee bump transaction
     */
    public function createFeeBumpTransaction($sourceSecret, $innerTransaction) {
        try {
            // Get a high priority fee as we're bumping an existing transaction
            $bumpFee = $this->getRecommendedFee(['priorityLevel' => 'high', 'forceRefresh' => true]);
            
            $this->log("Creating fee bump transaction with fee: $bumpFee stroops");
            
            // Create keypair from the secret
            $sourceKeypair = Keypair::fromSeed($sourceSecret);
            
            // Build fee bump transaction
            $feeBumpTxBuilder = new FeeBumpTransactionBuilder($innerTransaction);
            $feeBumpTxBuilder->setBaseFee($bumpFee);
            $feeBumpTxBuilder->setFeeAccount($sourceKeypair->getAccountId());
            
            // Build the transaction
            $feeBumpTransaction = $feeBumpTxBuilder->build();
            
            // Sign the transaction
            $feeBumpTransaction->sign($sourceKeypair, $this->network);
            
            return $feeBumpTransaction;
        } catch (\Exception $error) {
            $this->log('Error creating fee bump transaction', $error->getMessage());
            throw $error;
        }
    }
    
    /**
     * Estimate the total fee for a transaction with a given number of operations
     * 
     * @param int $operationCount Number of operations in the transaction
     * @param array $options Optional parameters (same as getRecommendedFee)
     * @return int Estimated total fee in stroops
     */
    public function estimateTransactionFee($operationCount, $options = []) {
        $baseFee = $this->getRecommendedFee($options);
        return $baseFee * max(1, $operationCount);
    }
    
    /**
     * Check if a transaction failed due to fee-related issues
     * 
     * @param \Exception $error Exception from transaction submission
     * @return bool Whether the error is fee-related
     */
    public function isFeeRelatedError($error) {
        if (!$error) {
            return false;
        }
        
        // Get the error message and check for fee-related keywords
        $errorMessage = $error->getMessage();
        
        // Common fee-related error messages from Horizon
        return (
            strpos($errorMessage, 'tx_insufficient_fee') !== false ||
            strpos($errorMessage, 'fee_bump_inner_failed') !== false ||
            strpos($errorMessage, 'tx_too_late') !== false ||
            strpos($errorMessage, 'insufficient fee') !== false ||
            strpos($errorMessage, 'fee would exceed') !== false
        );
    }
    
    /**
     * Get fee statistics for reporting
     * 
     * @return array Fee statistics for reporting
     */
    public function getFeeStatistics() {
        $feeStats = $this->getFeeStats(true);
        $congestion = $this->analyzeCongestion($feeStats);
        
        return [
            'timestamp' => date('c'),
            'congestion' => $congestion,
            'networkType' => $this->isTestnet ? 'testnet' : 'public',
            'feeStats' => [
                'min' => (int)$feeStats['fee_charged']['min'],
                'max' => (int)$feeStats['fee_charged']['max'],
                'median' => (int)$feeStats['fee_charged']['p50'],
                'p90' => (int)$feeStats['fee_charged']['p90']
            ],
            'recommendedFees' => [
                'low' => $this->getRecommendedFee(['priorityLevel' => 'low']),
                'medium' => $this->getRecommendedFee(['priorityLevel' => 'medium']),
                'high' => $this->getRecommendedFee(['priorityLevel' => 'high'])
            ]
        ];
    }
    
    /**
     * Create a transaction with recommended fee
     * 
     * @param \Soneso\StellarSDK\TransactionBuilder $transactionBuilder Transaction builder
     * @param array $options Options including priorityLevel and operationCount
     * @return \Soneso\StellarSDK\Transaction Transaction with recommended fee
     */
    public function createTransactionWithRecommendedFee($transactionBuilder, $options = []) {
        $priorityLevel = $options['priorityLevel'] ?? 'medium';
        $operationCount = $options['operationCount'] ?? 1;
        
        try {
            // Get the recommended fee based on network conditions and priority
            $recommendedFee = $this->estimateTransactionFee($operationCount, [
                'priorityLevel' => $priorityLevel,
                'forceRefresh' => $options['forceRefresh'] ?? false
            ]);
            
            $this->log("Setting transaction fee to $recommendedFee stroops (priority: $priorityLevel)");
            
            // Set the fee on the transaction builder
            $transactionBuilder->setBaseFee($recommendedFee);
            
            // Build and return the transaction
            return $transactionBuilder->build();
        } catch (\Exception $error) {
            $this->log('Error creating transaction with recommended fee', $error->getMessage());
            throw $error;
        }
    }
}
}