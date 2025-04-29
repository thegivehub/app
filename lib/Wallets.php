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
use Soneso\StellarSDK\Keypair;
use Soneso\StellarSDK\Server;
use Soneso\StellarSDK\Asset;
use Soneso\StellarSDK\StellarSDK;

class Wallets extends Collection {
    private $stellarServer;
    private $network;
    private $isTestnet;
    private $horizonUrl;
    
    /**
     * Constructor
     * @param bool $useTestnet Whether to use testnet (default: true)
     */
    public function __construct($useTestnet = true) {
        parent::__construct('wallets');
        
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
     * API: Get user's wallet details
     * Endpoint: /api/wallets/getUserWallet
     * @param array $params Request parameters with userId
     * @return array Wallet details or error
     */
    public function getUserWallet($params) {
        try {
            // Ensure user is authenticated
            if (!isset($params['userId'])) {
                return [
                    'success' => false,
                    'error' => 'User ID is required'
                ];
            }

            // Get wallet from database
            $wallet = $this->collection->findOne([
                'userId' => new MongoDB\BSON\ObjectId($params['userId'])
            ]);
            
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
                    'createdAt' => $wallet['createdAt']->toDateTime()->format('c'),
                    'lastAccessed' => $wallet['lastAccessed']->toDateTime()->format('c')
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
     * @param array $params Request parameters with userId
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

            // Get user's wallet
            $wallet = $this->collection->findOne([
                'userId' => new MongoDB\BSON\ObjectId($params['userId'])
            ]);
            
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
}
