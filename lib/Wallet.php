<?php
/**
 * Wallet.php
 * Handles multiple Stellar wallet operations and serves as a collection manager
 * API endpoints are automatically created for public methods
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Collection.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/soneso/stellar-php-sdk/Soneso/StellarSDK/StellarSDK.php';

// Use Soneso Stellar SDK
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\Server;
use Soneso\StellarSDK\Asset;
use Soneso\StellarSDK\StellarSDK;

class Wallet extends Collection {
    private $stellarServer;
    private $network;
    public $isTestnet = true;
    private $horizonUrl;
    
    /**
     * Constructor
     * @param bool $useTestnet Whether to use testnet (default: true)
     */
    public function __construct() {
        $this->collectionName = 'wallets';
        parent::__construct('wallets');
        $useTestnet = true;
        $this->isTestnet = $useTestnet;
        $this->horizonUrl = $useTestnet 
            ? 'https://horizon-testnet.stellar.org' 
            : 'https://horizon.stellar.org';
            
        // Initialize Stellar server
        $sdk = new StellarSDK($this->horizonUrl);
        $this->stellarServer = $sdk;
        $this->network = $useTestnet ? 'TESTNET' : 'PUBLIC';
    }

    /**
     * API: Create a new wallet for a user
     * Endpoint: /api/wallets/createWallet
     * @param array $params Request parameters with userId
     * @return array Created wallet details or error
     */
    public function createWallet($params) {
        try {
            // Ensure user is authenticated
            $uid = (isset($params) && isset($params['userId'])) ? $params['userId'] : $_REQUEST['userId'];
            if (!isset($uid)) {
                return [
                    'success' => false,
                    'error' => 'User Id is required'
                ];
            }

            // Check if user already has wallets
            $existingWallets = $this->collection->find([
                'userId' => new MongoDB\BSON\ObjectId($uid)
            ]);
            
            // Generate new Stellar keypair
            $keypair = KeyPair::random();
            $publicKey = $keypair->getPublicKey();
            $secretKey = $keypair->getSecretSeed();
            
            // Set default flag if this is the first wallet
            $isDefault = empty($existingWallets);
            
            // Create wallet document
            $wallet = [
                'userId' => new MongoDB\BSON\ObjectId($uid),
                'publicKey' => $publicKey,
                'secretKey' => $secretKey, // In production, this should be encrypted
                'network' => $this->isTestnet ? 'testnet' : 'public',
                'isDefault' => $isDefault,
                'currency' => 'XLM',
                'type' => 'user', // Add explicit type for consistency
                'label' => 'Stellar Wallet ' . (count($existingWallets) + 1),
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'lastAccessed' => new MongoDB\BSON\UTCDateTime(),
                'status' => 'active'
            ];
            
            // Insert wallet into database
            $result = $this->collection->insertOne($wallet);
            
            // If this is set as default, ensure no other wallets are default
            if ($isDefault && !empty($existingWallets)) {
                $this->collection->updateMany(
                    [
                        'userId' => new MongoDB\BSON\ObjectId($uid),
                        '_id' => ['$ne' => $result->getInsertedId()]
                    ],
                    ['$set' => ['isDefault' => false]]
                );
            }
            
            return [
                'success' => true,
                'wallet' => [
                    'id' => (string)$result->getInsertedId(),
                    'userId' => (string)$uid,
                    'publicKey' => $publicKey,
                    'balance' => '0',
                    'network' => $this->isTestnet ? 'testnet' : 'public',
                    'isDefault' => $isDefault,
                    'currency' => 'XLM',
                    'label' => $wallet['label'],
                    'createdAt' => $wallet['createdAt']->toDateTime()->format('c')
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
     * API: Get user's wallets
     * Endpoint: /api/wallets/getUserWallets
     * @param array $params Request parameters with userId
     * @return array List of user's wallets or error
     */
    public function getUserWallets($params) {
        try {
            // Ensure user is authenticated
            $uid = (isset($params) && isset($params['userId'])) ? $params['userId'] : $_REQUEST['userId'];
            if (!isset($uid)) {
                return [
                    'success' => false,
                    'error' => 'User Id is required'
                ];
            }

            // Get wallets from database
            $wallets = $this->collection->find([
                'userId' => new MongoDB\BSON\ObjectId($uid)
            ]);
            
            if (empty($wallets)) {
                return [
                    'success' => true,
                    'wallets' => []
                ];
            }
            
            $formattedWallets = [];
            foreach ($wallets as $wallet) {
                // Get current balance from Stellar network
                $balance = '0';
                try {
                    $accountResponse = $this->stellarServer->accounts()->account($wallet['publicKey']);
                    
                    // Process balances from account data
                    $balances = $accountResponse->getBalances();
                    if (!empty($balances)) {
                        foreach ($balances as $stellarBalance) {
                            if ($stellarBalance->getAssetType() === "native") {
                                $balance = $stellarBalance->getBalance();
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Account might not be activated yet
                    $balance = '0';
                }
                
                $formattedWallets[] = [
                    'id' => (string)$wallet['_id'],
                    'userId' => (string)$wallet['userId'],
                    'publicKey' => $wallet['publicKey'],
                    'balance' => $balance,
                    'network' => $this->isTestnet ? 'testnet' : 'public',
                    'isDefault' => $wallet['isDefault'] ?? false,
                    'currency' => $wallet['currency'] ?? 'XLM',
                    'label' => $wallet['label'] ?? 'Stellar Wallet',
                    'createdAt' => isset($wallet['createdAt'])
                        ? (is_object($wallet['createdAt']) && method_exists($wallet['createdAt'], 'toDateTime')
                            ? $wallet['createdAt']->toDateTime()->format('c')
                            : (is_string($wallet['createdAt']) ? $wallet['createdAt'] : null))
                        : null,
                    'status' => $wallet['status'] ?? 'active'
                ];
            }
            
            return [
                'success' => true,
                'wallets' => $formattedWallets
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * API: Set a wallet as default
     * Endpoint: /api/wallets/setDefaultWallet
     * @param array $params Request parameters with userId and walletId
     * @return array Success status or error
     */
    public function setDefaultWallet($params) {
        try {
            // Validate required parameters
            if (!isset($params['userId']) || !isset($params['walletId'])) {
                return [
                    'success' => false,
                    'error' => 'User ID and Wallet ID are required'
                ];
            }

            $userId = $params['userId'];
            $walletId = $params['walletId'];
            
            // Check if wallet exists and belongs to user
            $wallet = $this->collection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($walletId),
                'userId' => new MongoDB\BSON\ObjectId($userId)
            ]);
            
            if (!$wallet) {
                return [
                    'success' => false,
                    'error' => 'Wallet not found or does not belong to user'
                ];
            }
            
            // Update all user's wallets: set current as default, others as non-default
            $this->collection->updateMany(
                ['userId' => new MongoDB\BSON\ObjectId($userId)],
                ['$set' => ['isDefault' => false]]
            );
            
            $this->collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($walletId)],
                ['$set' => ['isDefault' => true]]
            );
            
            return [
                'success' => true,
                'message' => 'Default wallet updated successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * API: Get user's wallet details
     * Endpoint: /api/wallets/getUserWallet
     * @param array $params Request parameters with userId
     * @return array Wallet details or error
     */
    public function getUserWallet($params) {
        try {
            // Ensure user is authenticated
            $uid = (isset($params) && isset($params['userId'])) ? $params['userId'] : $_REQUEST['userId'];
            if (!isset($uid)) {
                return [
                    'success' => false,
                    'error' => 'User Id is required'
                ];
            }

            // Check if specific wallet ID is provided
            $walletId = isset($params['walletId']) ? $params['walletId'] : null;
            
            $query = ['userId' => new MongoDB\BSON\ObjectId($uid)];
            
            // If wallet ID is provided, use it
            if ($walletId) {
                $query['_id'] = new MongoDB\BSON\ObjectId($walletId);
            } else {
                // Otherwise, get the default wallet or the first one
                $query['isDefault'] = true;
            }
            
            // Get wallet from database
            $wallet = $this->collection->findOne($query);
            
            // If no default wallet found, get the first wallet
            if (!$wallet && !$walletId) {
                $wallet = $this->collection->findOne([
                    'userId' => new MongoDB\BSON\ObjectId($uid)
                ]);
            }
            
            if (!$wallet) {
                return [
                    'success' => false,
                    'error' => 'Wallet not found'
                ];
            }
            
            // Get current balance from Stellar network
            $balance = '0';
            try {
                $accountResponse = $this->stellarServer->accounts()->account($wallet['publicKey']);
                
                // Process balances from account data
                $balances = $accountResponse->getBalances();
                if (!empty($balances)) {
                    foreach ($balances as $stellarBalance) {
                        if ($stellarBalance->getAssetType() === "native") {
                            $balance = $stellarBalance->getBalance();
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Account might not be activated yet
                $balance = '0';
            }
            
            return [
                'success' => true,
                'wallet' => [
                    'id' => (string)$wallet['_id'],
                    'userId' => (string)$wallet['userId'],
                    'publicKey' => $wallet['publicKey'],
                    'balance' => $balance,
                    'network' => $this->isTestnet ? 'testnet' : 'public',
                    'isDefault' => $wallet['isDefault'] ?? false,
                    'currency' => $wallet['currency'] ?? 'XLM',
                    'label' => $wallet['label'] ?? 'Stellar Wallet',
                    'createdAt' => isset($wallet['createdAt'])
                        ? (is_object($wallet['createdAt']) && method_exists($wallet['createdAt'], 'toDateTime')
                            ? $wallet['createdAt']->toDateTime()->format('c')
                            : (is_string($wallet['createdAt']) ? $wallet['createdAt'] : null))
                        : null
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
     * API: Get transaction history for a user's wallet
     * Endpoint: /api/wallets/getTransactions
     * @param array $params Request parameters with userId and optionally walletId
     * @return array Transaction history or error
     */
    public function getTransactions($params) {
        try {
            if (!isset($params['userId'])) {
                return [
                    'success' => false,
                    'error' => 'User ID is required'
                ];
            }

            // Get wallet - by ID if provided, otherwise get default wallet
            $query = ['userId' => new MongoDB\BSON\ObjectId($params['userId'])];
            
            // If wallet ID is provided, use it
            if (isset($params['walletId'])) {
                $query['_id'] = new MongoDB\BSON\ObjectId($params['walletId']);
            } else {
                // Otherwise, get the default wallet or first wallet
                $query['isDefault'] = true;
            }
            
            $wallet = $this->collection->findOne($query);
            
            // If no default wallet found, get the first wallet for the user
            if (!$wallet && !isset($params['walletId'])) {
                $wallet = $this->collection->findOne([
                    'userId' => new MongoDB\BSON\ObjectId($params['userId'])
                ]);
            }
            
            if (!$wallet) {
                throw new \Exception('Wallet not found');
            }
            
            // Set up pagination
            $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
            $limit = isset($params['limit']) ? min(50, max(1, (int)$params['limit'])) : 10;
            $order = isset($params['order']) ? $params['order'] : 'desc';
            
            // Get transactions from Stellar network
            $transactionRequest = $this->stellarServer->transactions()->forAccount($wallet['publicKey']);
            
            // Apply pagination parameters
            $transactionRequest = $transactionRequest->limit($limit);
            $transactionRequest = $transactionRequest->order($order);
            
            // Add cursor for pagination
            if ($page > 1 && isset($params['cursor'])) {
                $transactionRequest = $transactionRequest->cursor($params['cursor']);
            }
            
            // Execute the request
            try {
                $transactionResponse = $transactionRequest->execute();
                
                $transactions = [
                    '_embedded' => [
                        'records' => $transactionResponse->getTransactions()
                    ],
                    '_links' => [
                        'next' => ['href' => $transactionResponse->getLinks()->getNext()]
                    ]
                ];
            } catch (\Exception $e) {
                throw new \Exception('Failed to fetch transactions: ' . $e->getMessage());
            }
            
            // Format transactions
            $formattedTransactions = [];
            foreach ($transactions['_embedded']['records'] as $tx) {
                // In the soneso SDK, each transaction object has different methods 
                $formattedTransactions[] = [
                    'id' => $tx->getId(),
                    'hash' => $tx->getHash(),
                    'ledger' => $tx->getLedger(),
                    'createdAt' => $tx->getCreatedAt(),
                    'fee' => $tx->getFeeCharged(),
                    'memo' => method_exists($tx, 'getMemo') && $tx->getMemo() ? $tx->getMemo()->getValue() : null,
                    'memoType' => method_exists($tx, 'getMemo') && $tx->getMemo() ? $tx->getMemo()->getType() : null,
                    'successful' => $tx->isSuccessful(),
                    'sourceAccount' => $tx->getSourceAccount()
                ];
            }
            
            // Update last accessed timestamp
            $this->collection->updateOne(
                ['_id' => $wallet['_id']],
                [
                    '$set' => [
                        'lastAccessed' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            return [
                'success' => true,
                'transactions' => $formattedTransactions,
                'walletId' => (string)$wallet['_id'],
                'walletLabel' => $wallet['label'] ?? 'Stellar Wallet',
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'hasMore' => !empty($transactions['_links']['next']['href']),
                    'nextCursor' => $transactions['_links']['next']['href'] ?? null
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
     * API: Get all wallets (admin only)
     * Endpoint: /api/wallets/getAllWallets
     * @param array $params Request parameters
     * @return array List of wallets
     */
    public function getAllWallets($params) {
        try {
            // Ensure admin access
            if (!isset($params['isAdmin']) || !$params['isAdmin']) {
                return [
                    'success' => false,
                    'error' => 'Admin access required'
                ];
            }

            // Get search parameters
            $search = $params['search'] ?? '';
            $page = max(1, intval($params['page'] ?? 1));
            $limit = min(50, max(1, intval($params['limit'] ?? 10)));
            $skip = ($page - 1) * $limit;

            // Build query
            $query = [];
            if ($search) {
                $query['$or'] = [
                    ['publicKey' => ['$regex' => $search, '$options' => 'i']]
                ];
            }

            // Get wallets with pagination
            $wallets = $this->collection->find(
                $query,
                [
                    'skip' => $skip,
                    'limit' => $limit,
                    'sort' => ['createdAt' => -1]
                ]
            );

            // Get total count
            $totalWallets = $this->collection->countDocuments($query);

            // Format wallet data
            $formattedWallets = [];
            foreach ($wallets as $wallet) {
                // Get user info if possible
                $user = null;
                if (isset($wallet['userId'])) {
                    $user = $this->db->getCollection('users')->findOne([
                        '_id' => $wallet['userId']
                    ]);
                }

                // Get balance from Stellar network
                $balance = '0';
                try {
                    $accountResponse = $this->stellarServer->accounts()->account($wallet['publicKey']);
                    
                    // Process balances from account data
                    $balances = $accountResponse->getBalances();
                    if (!empty($balances)) {
                        foreach ($balances as $stellarBalance) {
                            if ($stellarBalance->getAssetType() === "native") {
                                $balance = $stellarBalance->getBalance();
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Account might not be activated yet
                }

                $formattedWallets[] = [
                    'id' => (string)$wallet['_id'],
                    'userId' => isset($wallet['userId']) ? (string)$wallet['userId'] : null,
                    'publicKey' => $wallet['publicKey'],
                    'balance' => $balance,
                    'network' => $wallet['network'] ?? ($this->isTestnet ? 'testnet' : 'public'),
                    'status' => $wallet['status'] ?? 'active',
                    'createdAt' => isset($wallet['createdAt'])
                        ? (is_object($wallet['createdAt']) && method_exists($wallet['createdAt'], 'toDateTime')
                            ? $wallet['createdAt']->toDateTime()->format('c')
                            : (is_string($wallet['createdAt']) ? $wallet['createdAt'] : null))
                        : null,
                    'lastAccessed' => isset($wallet['lastAccessed'])
                        ? (is_object($wallet['lastAccessed']) && method_exists($wallet['lastAccessed'], 'toDateTime')
                            ? $wallet['lastAccessed']->toDateTime()->format('c')
                            : (is_string($wallet['lastAccessed']) ? $wallet['lastAccessed'] : null))
                        : null,
                    'user' => $user ? [
                        'email' => $user['email'] ?? 'N/A',
                        'name' => isset($user['personalInfo']) ? 
                            ($user['personalInfo']['firstName'] . ' ' . $user['personalInfo']['lastName']) : 
                            'N/A'
                    ] : null
                ];
            }

            return [
                'success' => true,
                'wallets' => $formattedWallets,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalWallets,
                    'pages' => ceil($totalWallets / $limit)
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
     * API: Send a payment from one wallet to another
     * Endpoint: /api/wallets/sendPayment
     * @param array $params Request parameters with sourceWalletId, destinationAddress, amount, memo, etc.
     * @return array Result of the payment operation
     */
    public function sendPayment($params) {
        try {
            // Validate required parameters
            if (!isset($params['sourceWalletId'])) {
                return [
                    'success' => false,
                    'error' => 'Source wallet ID is required'
                ];
            }
            
            if (!isset($params['destinationAddress'])) {
                return [
                    'success' => false,
                    'error' => 'Destination address is required'
                ];
            }
            
            if (!isset($params['amount']) || floatval($params['amount']) <= 0) {
                return [
                    'success' => false,
                    'error' => 'Valid amount is required'
                ];
            }

            // Get source wallet
            $sourceWallet = $this->collection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($params['sourceWalletId'])
            ]);
            
            if (!$sourceWallet) {
                return [
                    'success' => false,
                    'error' => 'Source wallet not found'
                ];
            }
            
            // Verify destination address format
            if (!preg_match('/^G[A-Z0-9]{55}$/', $params['destinationAddress'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid Stellar destination address format'
                ];
            }
            
            // Get current balance from Stellar network
            $balance = '0';
            try {
                $accountResponse = $this->stellarServer->accounts()->account($sourceWallet['publicKey']);
                
                // Process balances from account data
                $balances = $accountResponse->getBalances();
                if (!empty($balances)) {
                    foreach ($balances as $stellarBalance) {
                        if ($stellarBalance->getAssetType() === "native") {
                            $balance = $stellarBalance->getBalance();
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => 'Source account not found on Stellar network'
                ];
            }
            
            // Check if balance is sufficient
            $amount = floatval($params['amount']);
            $reserveAmount = 1.0; // 1 XLM minimum reserve
            
            if (floatval($balance) < ($amount + $reserveAmount)) {
                return [
                    'success' => false,
                    'error' => 'Insufficient balance. Remember you need to maintain a minimum reserve of 1 XLM'
                ];
            }
            
            // Create keypair from secret key
            $sourceKeyPair = KeyPair::fromSeed($sourceWallet['secretKey']);
            $sourceAccountId = $sourceKeyPair->getAccountId();
            
            // Load the source account
            $sourceAccount = $this->stellarServer->accounts()->account($sourceAccountId);
            
            // Create transaction builder using the StellarSDK instead of direct Server class
            // The Server class was moved in the new Soneso SDK
            $transaction = (new \Soneso\StellarSDK\TransactionBuilder($sourceAccount))
                ->addOperation(
                    \Soneso\StellarSDK\PaymentOperationBuilder::forNativeAsset(
                        $params['destinationAddress'],
                        (string)$amount
                    )
                );
            
            // Add memo if provided
            if (isset($params['memo']) && !empty($params['memo'])) {
                $memo = \Soneso\StellarSDK\Memo::text($params['memo']);
                $transaction = $transaction->addMemo($memo);
            }
            
            // Build and sign transaction
            $transaction = $transaction->build();
            $transaction->sign($sourceKeyPair, $this->network === 'TESTNET' ? \Soneso\StellarSDK\Network::testnet() : \Soneso\StellarSDK\Network::public());
            
            // Submit transaction
            $response = $this->stellarServer->submitTransaction($transaction);
            
            // Create transaction record
            $transactionRecord = [
                'source' => $sourceWallet['publicKey'],
                'destination' => $params['destinationAddress'],
                'amount' => $amount,
                'walletId' => new MongoDB\BSON\ObjectId($params['sourceWalletId']),
                'userId' => $sourceWallet['userId'],
                'memo' => $params['memo'] ?? '',
                'txHash' => $response->getHash(),
                'fee' => $response->getFeeCharged(),
                'status' => 'completed',
                'type' => 'payment',
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Save to blockchain_transactions collection if it exists
            try {
                $this->db->getCollection('blockchain_transactions')->insertOne($transactionRecord);
            } catch (\Exception $e) {
                // Just log the error but don't fail the transaction
                error_log("Failed to save transaction record: " . $e->getMessage());
            }
            
            return [
                'success' => true,
                'transaction' => [
                    'id' => (string)($transactionRecord['_id'] ?? ''),
                    'hash' => $response->getHash(),
                    'source' => $sourceWallet['publicKey'],
                    'destination' => $params['destinationAddress'],
                    'amount' => $amount,
                    'fee' => $response->getFeeCharged(),
                    'memo' => $params['memo'] ?? '',
                    'createdAt' => new \DateTime()
                ]
            ];
            
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $trace = $e->getTraceAsString();
            
            // Log the detailed error for debugging
            error_log("Payment error: " . $errorMessage);
            error_log("Stack trace: " . $trace);
            
            // Return a more detailed error response
            return [
                'success' => false,
                'error' => $errorMessage,
                'details' => [
                    'errorType' => get_class($e),
                    'sourceWalletId' => $params['sourceWalletId'] ?? 'not provided',
                    'destinationAddress' => $params['destinationAddress'] ?? 'not provided',
                    'amountRequested' => $params['amount'] ?? 'not provided'
                ]
            ];
        }
    }

    /**
     * Check account balance on Stellar network
     * @param string $publicKey Stellar account public key
     * @return array Account balance information
     */
    private function checkStellarAccountBalance($publicKey) {
        try {
            $accountResponse = $this->stellarServer->accounts()->account($publicKey);
            
            $balances = [];
            foreach ($accountResponse->getBalances() as $balance) {
                if ($balance->getAssetType() === 'native') {
                    $balances[] = [
                        'asset' => 'XLM',
                        'balance' => $balance->getBalance(),
                        'asset_type' => 'native'
                    ];
                } else {
                    $balances[] = [
                        'asset' => $balance->getAssetCode(),
                        'issuer' => $balance->getAssetIssuer(),
                        'balance' => $balance->getBalance(),
                        'asset_type' => $balance->getAssetType()
                    ];
                }
            }
            
            return [
                'success' => true,
                'account_id' => $publicKey,
                'balances' => $balances,
                'sequence' => $accountResponse->getSequenceNumber(),
                'last_modified_time' => $accountResponse->getLastModifiedTime(),
                'subentry_count' => $accountResponse->getSubentryCount()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Fund a testnet account using Stellar's Friendbot service
     * @param string $publicKey The public key of the account to fund
     * @return array Result of the funding operation
     */
    public function fundTestnetAccount($publicKey) {
        try {
            // Verify we are on testnet
            if (!$this->isTestnet) {
                throw new Exception("Funding is only available on testnet");
            }

            // Validate public key format
            if (!preg_match('/^G[A-Z0-9]{55}$/', $publicKey['publicKey'])) {
                throw new Exception("Invalid Stellar public key format");
            }

            // Call Friendbot to fund the account
            $friendbotUrl = "https://friendbot.stellar.org?addr=" . urlencode($publicKey['publicKey']);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $friendbotUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $error = json_decode($response, true);
                throw new Exception("Failed to fund account: " . 
                    ($error['detail'] ?? $error['title'] ?? "HTTP Error {$httpCode}"));
            }

            // Get the funded account details
            $accountDetails = $this->checkStellarAccountBalance($publicKey['publicKey']);
            
            if (!$accountDetails['success']) {
                throw new Exception("Failed to verify account funding: " . $accountDetails['error']);
            }

            return [
                'success' => true,
                'message' => 'Account funded successfully',
                'account' => [
                    'publicKey' => $publicKey['publicKey'],
                    'balances' => $accountDetails['balances']
                ]
            ];

        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * API: Create a new wallet for a campaign
     * Endpoint: /api.php/wallet/createCampaignWallet
     * @param array $params Request parameters with campaignId
     * @return array Created wallet details or error
     */
    public function createCampaignWallet($params) {
        try {
            // Ensure campaign ID is provided
            $campaignId = (isset($params) && isset($params['campaignId'])) ? $params['campaignId'] : $_REQUEST['campaignId'];
            if (!isset($campaignId)) {
                return [
                    'success' => false,
                    'error' => 'Campaign ID is required'
                ];
            }

            // Get campaign details to validate and get the title
            $db = new Database();
            $campaignsCollection = $db->getCollection('campaigns');
            try {
                $campaign = $campaignsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaignId)]);
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => 'Invalid campaign ID format'
                ];
            }

            if (!$campaign) {
                return [
                    'success' => false,
                    'error' => 'Campaign not found'
                ];
            }

            $campaignTitle = $campaign['title'] ?? "Untitled Campaign";

            // Check if campaign already has a wallet
            if (isset($campaign['walletId']) && !empty($campaign['walletId'])) {
                // Find the existing wallet
                $existingWallet = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaign['walletId'])]);
                
                if ($existingWallet) {
                    return [
                        'success' => true,
                        'wallet' => [
                            'id' => (string)$existingWallet['_id'],
                            'campaignId' => (string)$campaignId,
                            'publicKey' => $existingWallet['publicKey'],
                            'balance' => '0', // We'll get the real balance later
                            'network' => $this->isTestnet ? 'testnet' : 'public',
                            'currency' => 'XLM',
                            'label' => $existingWallet['label'] ?? "Campaign Wallet: {$campaignTitle}",
                            'message' => 'Campaign already has a wallet',
                            'createdAt' => isset($existingWallet['createdAt'])
                                ? (is_object($existingWallet['createdAt']) && method_exists($existingWallet['createdAt'], 'toDateTime')
                                    ? $existingWallet['createdAt']->toDateTime()->format('c')
                                    : (is_string($existingWallet['createdAt']) ? $existingWallet['createdAt'] : null))
                                : null
                        ]
                    ];
                }
            }

            // Generate new Stellar keypair
            $keypair = KeyPair::random();
            $publicKey = $keypair->getAccountId();
            $secretKey = $keypair->getSecretSeed();
            
            // Create wallet document
            $wallet = [
                'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
                'publicKey' => $publicKey,
                'secretKey' => $secretKey, // In production, this should be encrypted
                'network' => $this->isTestnet ? 'testnet' : 'public',
                'type' => 'campaign',
                'currency' => 'XLM',
                'label' => "Campaign Wallet: {$campaignTitle}",
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'lastAccessed' => new MongoDB\BSON\UTCDateTime(),
                'status' => 'active'
                // Note: userId is no longer required for campaign wallets thanks to schema update
            ];
            
            // Add debug output
            error_log("DEBUG: Attempting to insert wallet for campaign: " . $campaignId);
            error_log("DEBUG: Wallet data: " . json_encode($wallet));
            
            try {
                // Insert wallet into database
                $result = $this->collection->insertOne($wallet);
            } catch (\Exception $e) {
                error_log("DEBUG: MongoDB insert error: " . $e->getMessage());
                throw $e;
            }
            
            // Check if result is an array (error) or MongoDB\InsertOneResult
            if (is_array($result) && isset($result['success']) && $result['success'] === false) {
                error_log("DEBUG: Insert error result: " . json_encode($result));
                throw new \Exception($result['error'] ?? "Failed to insert wallet");
            }
            
            // Update campaign with wallet information
            $walletId = (string)$result->getInsertedId();
            $campaignsCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($campaignId)],
                [
                    '$set' => [
                        'walletId' => $walletId,
                        'stellarAddress' => $publicKey,
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            return [
                'success' => true,
                'wallet' => [
                    'id' => $walletId,
                    'campaignId' => $campaignId,
                    'publicKey' => $publicKey,
                    'balance' => '0',
                    'network' => $this->isTestnet ? 'testnet' : 'public',
                    'currency' => 'XLM',
                    'label' => $wallet['label'],
                    'createdAt' => $wallet['createdAt']->toDateTime()->format('c')
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
     * API: Get a campaign's wallet
     * Endpoint: /api.php/wallet/getCampaignWallet
     * @param array $params Request parameters with campaignId
     * @return array Wallet details or error
     */
    public function getCampaignWallet($params) {
        try {
            // Ensure campaign ID is provided
            $campaignId = (isset($params) && isset($params['campaignId'])) ? $params['campaignId'] : $_REQUEST['campaignId'];
            if (!isset($campaignId)) {
                return [
                    'success' => false,
                    'error' => 'Campaign ID is required'
                ];
            }

            // Get campaign details to validate
            $db = new Database();
            $campaignsCollection = $db->getCollection('campaigns');
            try {
                $campaign = $campaignsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaignId)]);
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => 'Invalid campaign ID format'
                ];
            }

            if (!$campaign) {
                return [
                    'success' => false,
                    'error' => 'Campaign not found'
                ];
            }

            // Check if the campaign has a wallet
            if (!isset($campaign['walletId']) || empty($campaign['walletId'])) {
                return [
                    'success' => false,
                    'error' => 'Campaign does not have a wallet',
                    'createUrl' => '/api.php/wallet/createCampaignWallet?campaignId=' . urlencode($campaignId)
                ];
            }

            // Get wallet details
            try {
                $wallet = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaign['walletId'])]);
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => 'Invalid wallet ID format'
                ];
            }

            if (!$wallet) {
                return [
                    'success' => false,
                    'error' => 'Wallet not found even though campaign has a walletId',
                    'walletId' => $campaign['walletId'],
                    'createUrl' => '/api.php/wallet/createCampaignWallet?campaignId=' . urlencode($campaignId)
                ];
            }

            // Get current balance from Stellar network
            $balance = '0';
            try {
                $accountResponse = $this->stellarServer->accounts()->account($wallet['publicKey']);
                
                // Process balances from account data
                $balances = $accountResponse->getBalances();
                if (!empty($balances)) {
                    foreach ($balances as $stellarBalance) {
                        if ($stellarBalance->getAssetType() === "native") {
                            $balance = $stellarBalance->getBalance();
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Account might not be activated yet
                $balance = '0';
            }
            
            return [
                'success' => true,
                'wallet' => [
                    'id' => (string)$wallet['_id'],
                    'campaignId' => (string)$wallet['campaignId'],
                    'publicKey' => $wallet['publicKey'],
                    'balance' => $balance,
                    'network' => $this->isTestnet ? 'testnet' : 'public',
                    'currency' => $wallet['currency'] ?? 'XLM',
                    'label' => $wallet['label'] ?? 'Campaign Wallet',
                    'createdAt' => isset($wallet['createdAt'])
                        ? (is_object($wallet['createdAt']) && method_exists($wallet['createdAt'], 'toDateTime')
                            ? $wallet['createdAt']->toDateTime()->format('c')
                            : (is_string($wallet['createdAt']) ? $wallet['createdAt'] : null))
                        : null,
                    'status' => $wallet['status'] ?? 'active'
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
     * API: Get all campaign wallets
     * Endpoint: /api.php/wallet/getAllCampaignWallets
     * @param array $params Request parameters
     * @return array List of campaign wallets
     */
    public function getAllCampaignWallets($params) {
        try {
            // Setup pagination
            $page = isset($params['page']) ? max(1, intval($params['page'])) : 1;
            $limit = isset($params['limit']) ? min(50, max(1, intval($params['limit']))) : 10;
            $skip = ($page - 1) * $limit;

            // Query for campaign wallets
            $query = [
                'type' => 'campaign'
            ];

            // Get wallets from database with pagination
            $wallets = $this->collection->find(
                $query,
                [
                    'skip' => $skip,
                    'limit' => $limit,
                    'sort' => ['createdAt' => -1]
                ]
            );
            
            // Get total count
            $totalWallets = $this->collection->countDocuments($query);
            
            if (empty($wallets)) {
                return [
                    'success' => true,
                    'wallets' => [],
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => 0,
                        'pages' => 0
                    ]
                ];
            }
            
            $formattedWallets = [];
            foreach ($wallets as $wallet) {
                // Get campaign details if possible
                $campaign = null;
                if (isset($wallet['campaignId'])) {
                    $db = new Database();
                    $campaignsCollection = $db->getCollection('campaigns');
                    $campaign = $campaignsCollection->findOne([
                        '_id' => $wallet['campaignId']
                    ]);
                }
                
                // Get current balance from Stellar network
                $balance = '0';
                try {
                    $accountResponse = $this->stellarServer->accounts()->account($wallet['publicKey']);
                    
                    // Process balances from account data
                    $balances = $accountResponse->getBalances();
                    if (!empty($balances)) {
                        foreach ($balances as $stellarBalance) {
                            if ($stellarBalance->getAssetType() === "native") {
                                $balance = $stellarBalance->getBalance();
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Account might not be activated yet
                    $balance = '0';
                }
                
                $formattedWallets[] = [
                    'id' => (string)$wallet['_id'],
                    'campaignId' => isset($wallet['campaignId']) ? (string)$wallet['campaignId'] : null,
                    'publicKey' => $wallet['publicKey'],
                    'balance' => $balance,
                    'network' => $this->isTestnet ? 'testnet' : 'public',
                    'currency' => $wallet['currency'] ?? 'XLM',
                    'label' => $wallet['label'] ?? 'Campaign Wallet',
                    'createdAt' => isset($wallet['createdAt'])
                        ? (is_object($wallet['createdAt']) && method_exists($wallet['createdAt'], 'toDateTime')
                            ? $wallet['createdAt']->toDateTime()->format('c')
                            : (is_string($wallet['createdAt']) ? $wallet['createdAt'] : null))
                        : null,
                    'status' => $wallet['status'] ?? 'active',
                    'campaign' => $campaign ? [
                        'title' => $campaign['title'] ?? 'Unknown Campaign',
                        'status' => $campaign['status'] ?? 'unknown',
                        'fundingTarget' => isset($campaign['funding']) ? ($campaign['funding']['targetAmount'] ?? 0) : 0,
                        'fundingRaised' => isset($campaign['funding']) ? ($campaign['funding']['raisedAmount'] ?? 0) : 0
                    ] : null
                ];
            }
            
            return [
                'success' => true,
                'wallets' => $formattedWallets,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalWallets,
                    'pages' => ceil($totalWallets / $limit)
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
     * API: Fund a campaign wallet with testnet funds
     * Endpoint: /api.php/wallet/fundCampaignWallet
     * @param array $params Request parameters with campaignId
     * @return array Result of the funding operation
     */
    public function fundCampaignWallet($params) {
        try {
            // Verify we are on testnet
            if (!$this->isTestnet) {
                throw new Exception("Funding is only available on testnet");
            }

            // Ensure campaign ID is provided
            $campaignId = (isset($params) && isset($params['campaignId'])) ? $params['campaignId'] : $_REQUEST['campaignId'];
            if (!isset($campaignId)) {
                return [
                    'success' => false,
                    'error' => 'Campaign ID is required'
                ];
            }

            // Get campaign details
            $db = new Database();
            $campaignsCollection = $db->getCollection('campaigns');
            try {
                $campaign = $campaignsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaignId)]);
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => 'Invalid campaign ID format'
                ];
            }

            if (!$campaign) {
                return [
                    'success' => false,
                    'error' => 'Campaign not found'
                ];
            }

            // Check if the campaign has a wallet
            if (!isset($campaign['walletId']) || empty($campaign['walletId'])) {
                // Try to create a wallet first
                $createResult = $this->createCampaignWallet(['campaignId' => $campaignId]);
                if (!$createResult['success']) {
                    return [
                        'success' => false,
                        'error' => 'Campaign does not have a wallet and could not create one: ' . ($createResult['error'] ?? 'Unknown error')
                    ];
                }
                
                // Update the campaign variable with the new wallet
                $campaign = $campaignsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaignId)]);
                if (!$campaign || !isset($campaign['walletId']) || empty($campaign['walletId'])) {
                    return [
                        'success' => false,
                        'error' => 'Failed to get campaign wallet after creation'
                    ];
                }
            }

            // Get wallet details
            $wallet = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaign['walletId'])]);
            if (!$wallet) {
                return [
                    'success' => false,
                    'error' => 'Wallet not found even though campaign has a walletId'
                ];
            }

            // Check if wallet has a public key
            if (!isset($wallet['publicKey']) || empty($wallet['publicKey'])) {
                return [
                    'success' => false,
                    'error' => 'Wallet does not have a public key'
                ];
            }

            // Call Friendbot to fund the account
            $publicKey = $wallet['publicKey'];
            $friendbotUrl = "https://friendbot.stellar.org?addr=" . urlencode($publicKey);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $friendbotUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $error = json_decode($response, true);
                throw new Exception("Failed to fund account: " . 
                    ($error['detail'] ?? $error['title'] ?? "HTTP Error {$httpCode}"));
            }

            // Get the funded account details
            $accountDetails = $this->checkStellarAccountBalance($publicKey);
            
            if (!$accountDetails['success']) {
                throw new Exception("Failed to verify account funding: " . $accountDetails['error']);
            }

            return [
                'success' => true,
                'message' => 'Campaign wallet funded successfully',
                'wallet' => [
                    'id' => (string)$wallet['_id'],
                    'campaignId' => $campaignId,
                    'publicKey' => $publicKey,
                    'balance' => $accountDetails['balances'][0]['balance'] ?? '0',
                    'network' => $this->isTestnet ? 'testnet' : 'public',
                    'all_balances' => $accountDetails['balances']
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
     * API: Get a wallet by ID or public key
     * Endpoint: /api.php/wallet/getWalletDetails
     * @param array $params Request parameters with walletId or publicKey
     * @return array Wallet details or error
     */
    public function getWalletDetails($params) {
        try {
            // Check if wallet ID is provided
            if (isset($params['walletId']) && !empty($params['walletId'])) {
                try {
                    $wallet = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($params['walletId'])]);
                } catch (\Exception $e) {
                    return [
                        'success' => false,
                        'error' => 'Invalid wallet ID format'
                    ];
                }
            }
            // Or check if public key is provided
            else if (isset($params['publicKey']) && !empty($params['publicKey'])) {
                $wallet = $this->collection->findOne(['publicKey' => $params['publicKey']]);
            }
            else {
                return [
                    'success' => false,
                    'error' => 'Either walletId or publicKey is required'
                ];
            }
            
            if (!$wallet) {
                return [
                    'success' => false,
                    'error' => 'Wallet not found'
                ];
            }
            
            // Get current balance from Stellar network
            $balance = '0';
            try {
                $accountResponse = $this->stellarServer->accounts()->account($wallet['publicKey']);
                
                // Process balances from account data
                $balances = $accountResponse->getBalances();
                if (!empty($balances)) {
                    foreach ($balances as $stellarBalance) {
                        if ($stellarBalance->getAssetType() === "native") {
                            $balance = $stellarBalance->getBalance();
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Account might not be activated yet
                $balance = '0';
            }
            
            $result = [
                'success' => true,
                'wallet' => [
                    'id' => (string)$wallet['_id'],
                    'publicKey' => $wallet['publicKey'],
                    'balance' => $balance,
                    'network' => $this->isTestnet ? 'testnet' : 'public',
                    'currency' => $wallet['currency'] ?? 'XLM',
                    'label' => $wallet['label'] ?? 'Wallet',
                    'createdAt' => isset($wallet['createdAt'])
                        ? (is_object($wallet['createdAt']) && method_exists($wallet['createdAt'], 'toDateTime')
                            ? $wallet['createdAt']->toDateTime()->format('c')
                            : (is_string($wallet['createdAt']) ? $wallet['createdAt'] : null))
                        : null,
                    'type' => $wallet['type'] ?? 'user',
                    'status' => $wallet['status'] ?? 'active'
                ]
            ];
            
            // Add campaign info if this is a campaign wallet
            if (isset($wallet['campaignId'])) {
                $result['wallet']['campaignId'] = (string)$wallet['campaignId'];
                
                // Try to get campaign details
                try {
                    $db = new Database();
                    $campaignsCollection = $db->getCollection('campaigns');
                    $campaign = $campaignsCollection->findOne(['_id' => $wallet['campaignId']]);
                    
                    if ($campaign) {
                        $result['wallet']['campaign'] = [
                            'title' => $campaign['title'] ?? 'Unknown Campaign',
                            'status' => $campaign['status'] ?? 'unknown',
                            'fundingTarget' => isset($campaign['funding']) ? ($campaign['funding']['targetAmount'] ?? 0) : 0,
                            'fundingRaised' => isset($campaign['funding']) ? ($campaign['funding']['raisedAmount'] ?? 0) : 0
                        ];
                    }
                } catch (\Exception $e) {
                    // Ignore campaign lookup errors
                }
            }
            
            // Add user info if this is a user wallet
            if (isset($wallet['userId'])) {
                $result['wallet']['userId'] = (string)$wallet['userId'];
                
                // Try to get user details
                try {
                    $db = new Database();
                    $usersCollection = $db->getCollection('users');
                    $user = $usersCollection->findOne(['_id' => $wallet['userId']]);
                    
                    if ($user) {
                        $result['wallet']['user'] = [
                            'email' => $user['email'] ?? 'unknown',
                            'username' => $user['username'] ?? 'Unknown User'
                        ];
                    }
                } catch (\Exception $e) {
                    // Ignore user lookup errors
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * API: Ensure all campaigns have wallets
     * Endpoint: /api.php/wallet/ensureAllCampaignsHaveWallets
     * @param array $params Request parameters
     * @return array Result of the operation
     */
    public function ensureAllCampaignsHaveWallets($params) {
        try {
            // Only allow admins to run this
            if (!(isset($params['isAdmin']) && $params['isAdmin'])) {
                return [
                    'success' => false,
                    'error' => 'Admin access required'
                ];
            }

            $db = new Database();
            $campaignsCollection = $db->getCollection('campaigns');
            
            // Get all campaigns that don't have a walletId
            $campaigns = $campaignsCollection->find([
                '$or' => [
                    ['walletId' => ['$exists' => false]],
                    ['walletId' => null],
                    ['walletId' => '']
                ]
            ]);
            
            $totalCampaigns = 0;
            $createdWallets = 0;
            $failedWallets = 0;
            $results = [];
            
            foreach ($campaigns as $campaign) {
                $totalCampaigns++;
                $campaignId = (string)$campaign['_id'];
                $campaignTitle = $campaign['title'] ?? "Untitled Campaign";
                
                $createResult = $this->createCampaignWallet(['campaignId' => $campaignId]);
                
                if ($createResult['success']) {
                    $createdWallets++;
                    $results[] = [
                        'campaignId' => $campaignId,
                        'campaignTitle' => $campaignTitle,
                        'walletId' => $createResult['wallet']['id'],
                        'publicKey' => $createResult['wallet']['publicKey'],
                        'success' => true
                    ];
                } else {
                    $failedWallets++;
                    $results[] = [
                        'campaignId' => $campaignId,
                        'campaignTitle' => $campaignTitle,
                        'error' => $createResult['error'],
                        'success' => false
                    ];
                }
            }
            
            return [
                'success' => true,
                'summary' => [
                    'totalProcessed' => $totalCampaigns,
                    'walletsCreated' => $createdWallets,
                    'walletsFailed' => $failedWallets,
                    'message' => $totalCampaigns === 0 ? 'All campaigns already have wallets' : null
                ],
                'results' => $results
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
