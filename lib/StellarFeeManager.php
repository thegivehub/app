<?php
/**
 * StellarFeeManager.php
 * A utility class for managing Stellar transaction fees dynamically
 */
require_once __DIR__ . '/../vendor/autoload.php';

// Use Soneso Stellar SDK
use Soneso\StellarSDK\Crypto\KeyPair;
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

    // Short cache duration (ms) used by the optimized-fee path
    private $optimizedCacheDuration;

    // Initialize cache for the optimized-fee fee_stats lookups
    private $optimizedStatsCache;

    // Hard cap (stroops) on per-operation fee for optimization
    private $maxFeePerOp;

    // HTTP timeout (seconds) for direct Horizon fee_stats calls
    private $feeStatsHttpTimeout;
    
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

        // Short cache for the optimized-fee fee_stats lookups so we don't hammer Horizon
        $this->optimizedCacheDuration = $config['optimizedCacheDuration'] ?? 10000; // 10s default
        $this->optimizedStatsCache = [
            'timestamp' => 0,
            'data' => null
        ];

        // Hard cap (in stroops) on the per-operation fee to avoid overpaying. 100000 = 0.01 XLM.
        $this->maxFeePerOp = $config['maxFeePerOp'] ?? 100000;

        // HTTP timeout (seconds) for direct Horizon fee_stats calls used by getOptimizedFee
        $this->feeStatsHttpTimeout = $config['feeStatsHttpTimeout'] ?? 3;
        
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
    public function createMultiSigTransaction($sourceSecrets, $innerTransaction, $threshold = 2, $signers = []) {
        try {
            // Get a high priority fee for multi-sig transactions
            $fee = $this->getRecommendedFee(['priorityLevel' => 'high', 'forceRefresh' => true]);
            
            $this->log("Creating multi-sig transaction with fee: $fee stroops");
            
            // Create keypairs from all source secrets
            $sourceKeypairs = [];
            foreach ((array)$sourceSecrets as $secret) {
                $sourceKeypairs[] = Keypair::fromSeed($secret);
            }
            
            // Get the source account ID from first keypair
            $sourceAccountId = $sourceKeypairs[0]->getAccountId();
            
            // Create transaction builder
            $builder = new TransactionBuilder($sourceAccountId);
            $builder->setBaseFee($fee);
            
            // Add operations from inner transaction if provided
            if ($innerTransaction) {
                foreach ($innerTransaction->getOperations() as $op) {
                    $builder->addOperation($op);
                }
            }
            
            // Set time bounds if provided in inner transaction
            if ($innerTransaction && $innerTransaction->getTimeBounds()) {
                $builder->setTimeBounds(
                    $innerTransaction->getTimeBounds()->getMinTime(),
                    $innerTransaction->getTimeBounds()->getMaxTime()
                );
            }
            
            // Add signers if provided
            if (!empty($signers)) {
                foreach ($signers as $signer) {
                    $builder->addSigner($signer['key'], $signer['weight']);
                }
            }
            
            // Set threshold if provided
            if ($threshold > 0) {
                $builder->setThreshold($threshold);
            }
            
            // Build the transaction
            $transaction = $builder->build();
            
            // Sign with all source keypairs
            foreach ($sourceKeypairs as $keypair) {
                $transaction->sign($keypair, $this->network);
            }
            
            return $transaction;
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
    /**
     * Validate signers array structure
     * 
     * @param array $signers Array of signers to validate
     * @return bool True if valid, false otherwise
     */
    public function validateSigners($signers) {
        if (!is_array($signers)) {
            return false;
        }
        
        foreach ($signers as $signer) {
            if (!isset($signer['key']) || !isset($signer['weight'])) {
                return false;
            }
            
            if (!is_string($signer['key']) || !preg_match('/^G[A-Z0-9]{55}$/', $signer['key'])) {
                return false;
            }
            
            if (!is_int($signer['weight']) || $signer['weight'] < 0 || $signer['weight'] > 255) {
                return false;
            }
        }
        
        return true;
    }

    public function estimateTransactionFee($operationCount, $options = []) {
        $baseFee = $this->getRecommendedFee($options);
        return $baseFee * max(1, $operationCount);
    }

    /**
     * Fetch raw fee stats directly from the Horizon /fee_stats endpoint.
     *
     * Unlike getFeeStats() (which uses the SDK and only exposes fee_charged
     * percentiles), this reads the full payload including last_ledger_base_fee
     * and ledger_capacity_usage, and is cached on a short window so the
     * optimizer can be called frequently without hammering Horizon.
     *
     * @param bool $forceRefresh Force refresh the short-lived cache
     * @return array|null Decoded fee_stats payload, or null on failure
     */
    private function fetchRawFeeStats($forceRefresh = false) {
        $now = round(microtime(true) * 1000);

        if (!$forceRefresh &&
            $this->optimizedStatsCache['data'] &&
            $now - $this->optimizedStatsCache['timestamp'] < $this->optimizedCacheDuration) {
            $this->log('Using cached raw fee stats');
            return $this->optimizedStatsCache['data'];
        }

        $url = rtrim($this->horizonUrl, '/') . '/fee_stats';

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->feeStatsHttpTimeout,
                CURLOPT_CONNECTTIMEOUT => $this->feeStatsHttpTimeout,
                CURLOPT_HTTPHEADER     => ['Accept: application/json']
            ]);

            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($body === false || $httpCode < 200 || $httpCode >= 300) {
                throw new \Exception("fee_stats request failed (HTTP $httpCode): $curlErr");
            }

            $data = json_decode($body, true);
            if (!is_array($data) || !isset($data['fee_charged'])) {
                throw new \Exception('Unexpected fee_stats payload');
            }

            $this->optimizedStatsCache = [
                'timestamp' => $now,
                'data'      => $data
            ];

            return $data;
        } catch (\Exception $error) {
            $this->log('Error fetching raw fee stats', $error->getMessage());

            // Return expired cache if available rather than failing hard
            if ($this->optimizedStatsCache['data']) {
                $this->log('Using expired raw fee stats due to error');
                return $this->optimizedStatsCache['data'];
            }

            return null;
        }
    }

    /**
     * Compute a network-aware, optimized total fee for a transaction.
     *
     * Reads recent ledger fee-charged percentiles from Horizon /fee_stats and
     * maps the requested priority to a percentile:
     *   - 'low'    -> p10  (cheapest; may take longer to confirm)
     *   - 'normal' -> p50  (median; balanced)
     *   - 'high'   -> p90  (priority; pay more to outbid congestion)
     * When the ledger is near capacity we nudge the chosen percentile up one
     * level so transactions still land during congestion.
     *
     * The chosen per-operation fee is clamped to [base fee, maxFeePerOp] and
     * multiplied by the operation count to produce the total fee. On any
     * network failure it falls back to the base fee (last_ledger_base_fee if
     * known, otherwise defaultBaseFee) so callers never block on Horizon.
     *
     * @param int    $opCount  Number of operations in the transaction (>= 1)
     * @param string $priority 'low' | 'normal' | 'high'
     * @return int Total optimized fee in stroops
     */
    public function getOptimizedFee($opCount = 1, $priority = 'normal') {
        $opCount = max(1, (int)$opCount);
        $priority = strtolower($priority);

        // Map priority -> percentile key in the fee_charged object
        $percentileMap = [
            'low'    => 'p10',
            'normal' => 'p50',
            'high'   => 'p90'
        ];
        $percentileKey = $percentileMap[$priority] ?? 'p50';

        $stats = $this->fetchRawFeeStats();

        // Determine the network base fee (sane minimum)
        $baseFee = $this->defaultBaseFee;
        if ($stats && isset($stats['last_ledger_base_fee'])) {
            $baseFee = max($baseFee, (int)$stats['last_ledger_base_fee']);
        }

        // Graceful fallback: no usable stats -> base fee per operation
        if (!$stats || !isset($stats['fee_charged'][$percentileKey])) {
            $this->log("getOptimizedFee falling back to base fee ($baseFee stroops/op)");
            $perOp = min(max($baseFee, $this->defaultBaseFee), $this->maxFeePerOp);
            return $perOp * $opCount;
        }

        // Bump the percentile up one tier when the ledger is near capacity so
        // we don't underbid during congestion.
        $capacity = isset($stats['ledger_capacity_usage'])
            ? (float)$stats['ledger_capacity_usage']
            : 0.0;
        if ($capacity >= 0.75) {
            $escalation = ['p10' => 'p50', 'p50' => 'p90', 'p90' => 'p90'];
            $percentileKey = $escalation[$percentileKey];
            $this->log("Ledger near capacity ($capacity); escalating to $percentileKey");
        }

        $perOp = (int)$stats['fee_charged'][$percentileKey];

        // Clamp to [base fee, maxFeePerOp]
        $perOp = max($perOp, $baseFee);
        $perOp = min($perOp, $this->maxFeePerOp);

        $totalFee = $perOp * $opCount;

        $this->log("Optimized fee: $totalFee stroops ($perOp/op x $opCount, priority: $priority, percentile: $percentileKey, capacity: $capacity)");
        return $totalFee;
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
    /**
     * Get signer weights from an account
     * 
     * @param string $accountId Stellar account ID
     * @return array Array of signers with their weights
     */
    public function getAccountSigners($accountId) {
        try {
            $account = $this->stellarServer->getAccount($accountId);
            $signers = [];
            
            foreach ($account->getSigners() as $signer) {
                $signers[] = [
                    'key' => $signer->getKey(),
                    'weight' => $signer->getWeight(),
                    'type' => $signer->getType()
                ];
            }
            
            return [
                'success' => true,
                'signers' => $signers,
                'thresholds' => [
                    'low' => $account->getThresholds()->getLowThreshold(),
                    'medium' => $account->getThresholds()->getMediumThreshold(),
                    'high' => $account->getThresholds()->getHighThreshold()
                ]
            ];
        } catch (\Exception $error) {
            $this->log('Error getting account signers', $error->getMessage());
            return [
                'success' => false,
                'error' => $error->getMessage()
            ];
        }
    }

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
            if (!empty($options['optimize'])) {
                // Use network-aware fee optimization (opt-in). Map the existing
                // priority vocabulary onto the optimizer's low/normal/high.
                $optimizePriority = [
                    'low'    => 'low',
                    'medium' => 'normal',
                    'high'   => 'high'
                ][$priorityLevel] ?? 'normal';

                $recommendedFee = $this->getOptimizedFee($operationCount, $optimizePriority);
            } else {
                // Get the recommended fee based on network conditions and priority
                $recommendedFee = $this->estimateTransactionFee($operationCount, [
                    'priorityLevel' => $priorityLevel,
                    'forceRefresh' => $options['forceRefresh'] ?? false
                ]);
            }
            
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
