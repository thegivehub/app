<?php
/**
 * Wallets.php
 * Handles multiple Stellar wallet operations and serves as a collection manager
 * API endpoints are automatically created for public methods
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Collection.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Use Soneso Stellar SDK
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\Server;
use Soneso\StellarSDK\Asset;
use Soneso\StellarSDK\StellarSDK;

class Wallets extends Collection {
    private $stellarServer;
    private $network;
    public $isTestnet = true;
    private $horizonUrl;
    
    /**
     * Constructor
     * @param bool $useTestnet Whether to use testnet (default: true)
     */
    public function __construct() {
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
            $keypair = Keypair::random();
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
            $sourceKeypair = Keypair::fromSeed($sourceWallet['secretKey']);
            $sourceAccountId = $sourceKeypair->getPublicKey();
            
            // Load the source account
            $sourceAccount = $this->stellarServer->accounts()->account($sourceAccountId);
            
            // Create transaction builder
            $server = new \Soneso\StellarSDK\Server($this->horizonUrl);
            $transaction = (new \Soneso\StellarSDK\Transaction\TransactionBuilder($sourceAccount))
                ->addOperation(
                    \Soneso\StellarSDK\Operation\PaymentOperationBuilder::forNativeAsset(
                        $params['destinationAddress'],
                        (string)$amount
                    )
                );
            
            // Add memo if provided
            if (isset($params['memo']) && !empty($params['memo'])) {
                $memo = new \Soneso\StellarSDK\Memo\MemoText($params['memo']);
                $transaction = $transaction->addMemo($memo);
            }
            
            // Build and sign transaction
            $transaction = $transaction->build();
            $transaction->sign($sourceKeypair, $this->network);
            
            // Submit transaction
            $response = $server->submitTransaction($transaction);
            
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
            return [
                'success' => false,
                'error' => $e->getMessage()
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
}
