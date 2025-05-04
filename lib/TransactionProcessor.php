<?php
// lib/TransactionProcessor.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Collection.php';

// Import the necessary Soneso Stellar SDK classes
use Soneso\StellarSDK\StellarSDK;
use Soneso\StellarSDK\Crypto\KeyPair;  // Fix: Use the correct namespace for KeyPair
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\Asset;
use Soneso\StellarSDK\Memo;  // Fix: Use Memo instead of MemoText
use Soneso\StellarSDK\MuxedAccount; // Add MuxedAccount for payment operations
use Soneso\StellarSDK\TransactionBuilder;
use Soneso\StellarSDK\PaymentOperation;
use Soneso\StellarSDK\CreateAccountOperation;
use Soneso\StellarSDK\PaymentOperationBuilder;
use Soneso\StellarSDK\AssetTypeCreditAlphanum4;
use Soneso\StellarSDK\ChangeTrustOperationBuilder;
use Soneso\StellarSDK\CreateAccountOperationBuilder;
use Soneso\StellarSDK\LedgerBounds;
use Soneso\StellarSDK\ManageDataOperationBuilder;
use Soneso\StellarSDK\ManageSellOfferOperationBuilder;
use Soneso\StellarSDK\PathPaymentStrictReceiveOperationBuilder;
use Soneso\StellarSDK\PathPaymentStrictSendOperationBuilder;
use Soneso\StellarSDK\Responses\Operations\CreateAccountOperationResponse;
use Soneso\StellarSDK\Responses\Operations\PaymentOperationResponse;
use Soneso\StellarSDK\TimeBounds;
use Soneso\StellarSDK\Transaction;
use Soneso\StellarSDK\TransactionPreconditions;
use Soneso\StellarSDK\Util\FriendBot;


/**
 * TransactionProcessor Collection
 * Handles transaction processing and management
 */
class TransactionProcessor extends Collection {
    private $stellarSdk;
    private $horizonUrl;
    private $network;
    private $baseFee;
    private $lastError;
    private $useTestnet;
    
    public function __construct($useTestnet = true) {
        parent::__construct();
        $useTestnet = true;
        error_log("In TransactionProcessor");
        try {
            // Set network based on environment
            $this->useTestnet = $useTestnet;
            $this->horizonUrl = $useTestnet 
                ? 'https://horizon-testnet.stellar.org' 
                : 'https://horizon.stellar.org';
                
            $this->network = $useTestnet
                ? Network::testnet()
                : Network::public();
            
            // Add debug logging
            error_log("TransactionProcessor: Initializing StellarSDK with URL: " . $this->horizonUrl);
            
            // Initialize Stellar SDK with the horizon URL
            $this->stellarSdk = new StellarSDK($this->horizonUrl);
            
            // Set base fee (default is 100 stroops = 0.00001 XLM)
            $this->baseFee = 100;
            
            error_log("TransactionProcessor: Successfully initialized StellarSDK");
        } catch (\Exception $e) {
            error_log("TransactionProcessor: Failed to initialize StellarSDK: " . $e->getMessage());
            throw $e; // Re-throw the exception to ensure proper error reporting
        }
    }
    
    /**
     * Get the last error message
     * @return string|null
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Process a donation transaction
     * @param array $params Parameters for the donation
     * @return array Result of the transaction
     */
    public function processDonation($params) {
        try {
            $sdk = StellarSDK::getTestNetInstance();

            // Required parameters
            $donorId = $params['donorId'] ?? null;
            $campaignId = $params['campaignId'] ?? null;
            $amount = $params['amount'] ?? null;
            $walletId = $params['walletId'] ?? null;
            $isAnonymous = $params['isAnonymous'] ?? false;
            $message = $params['message'] ?? '';
            $isRecurring = $params['recurring'] ?? false;
            $frequency = $params['frequency'] ?? 'monthly';
            
            // Validate required parameters
            if (!$donorId || !$campaignId || !$amount || !$walletId) {
                $missingParams = [];
                if (!$donorId) $missingParams[] = "donorId";
                if (!$campaignId) $missingParams[] = "campaignId";
                if (!$amount) $missingParams[] = "amount";
                if (!$walletId) $missingParams[] = "walletId";

                throw new Exception('Missing required parameters: '.join(', ', $missingParams));
            }
            
            // Get wallet secret key from database
            $db = new Database();
            $walletsCollection = $db->getCollection('wallets');
            $wallet = $walletsCollection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($walletId),
                'userId' => new MongoDB\BSON\ObjectId($donorId)
            ]);
            
            if (!$wallet) {
                throw new Exception("Wallet not found or does not belong to the donor");
            }
            
            $sourceSecret = $wallet['secretKey'] ?? null;
            
            if (!$sourceSecret) {
                throw new Exception("Wallet secret key not available");
            }
            
            // Get campaign details from MongoDB using the campaigns collection
            $db = new Database();
            $campaignsCollection = $db->getCollection('campaigns');
            
            // Try to get campaign by ID
            try {
                $campaign = $campaignsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaignId)]);
            } catch (\Exception $e) {
                error_log("Error parsing campaign ID: " . $e->getMessage());
                throw new Exception("Invalid campaign ID format: {$campaignId}");
            }
            
            if (!$campaign) {
                // Log more details about the campaign lookup attempt
                error_log("Campaign not found - ID: {$campaignId}");
                error_log("MongoDB query: ['_id' => ObjectId('{$campaignId}')]");
                
                // Try a broader search to debug
                $campaign = $campaignsCollection->findOne([], ['limit' => 1]);
                if ($campaign) {
                    error_log("However, at least one campaign exists in the collection with ID: " . $campaign['_id']);
                } else {
                    error_log("No campaigns found in the collection at all");
                }
                
                throw new Exception("Campaign not found: {$campaignId}");
            }
            
            // Get campaign's wallet information and Stellar address
            $destAddress = $campaignAddress = $campaign['stellarAddress'] ?? null;
            $campaignWalletId = $campaign['walletId'] ?? null;

            error_log(">>>> Dest Address: ".$destAddress);
            error_log(">>>> WalletId: ".$campaignWalletId);

            // Ensure the campaign has a wallet and Stellar address
            if (!$campaignAddress || !$campaignWalletId) {
                // Try to create a wallet for this campaign
                $campaignWallet = $this->ensureCampaignHasWallet($campaign);
                if ($campaignWallet) {
                    $campaignAddress = $campaignWallet['publicKey'];
                    $campaignWalletId = (string)$campaignWallet['_id'];
                    error_log("Created new wallet for campaign: $campaignId, Address: $campaignAddress");
                } else {
                    throw new Exception("Campaign does not have a Stellar address and could not create one");
                }
            }
            
            // Create transaction record
            $transactionRecord = [
                'userId' => new MongoDB\BSON\ObjectId($donorId),
                'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
                'amount' => [
                    'value' => (float)$amount,
                    'currency' => 'XLM' // Native Stellar asset
                ],
                'transaction' => [
                    'status' => 'pending'
                ],
                'type' => $isRecurring ? 'recurring' : 'one-time',
                'status' => 'pending',
                'visibility' => $isAnonymous ? 'anonymous' : 'public',
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];

            $senderKeyPair = KeyPair::fromSeed($sourceSecret);
            $senderAccountId = $senderKeyPair->getAccountId();
            $sender = $sdk->requestAccount($senderKeyPair->getAccountId());

            $destAddress = "GD7LKMPY76XFFGXWX2ASBSCPJLZPHA3UMJIZAPM6N5EYFDH45CSLY6XS";

            $paymentOperation = (new PaymentOperationBuilder($destAddress, Asset::native(), $amount))->build();
            
            
            // Create Stellar keypair from secret
            // $sourceKeypair = Keypair::fromSeed($sourceSecret);
            // $sourceAccount = $this->stellarSdk->accounts()->account($sourceAccountId);
            
            // Prepare memo text
            $memoText = "campaign:{$campaignId}";
            if (!$isAnonymous) {
                $memoText .= ",donor:{$donorId}";
            }
            
            // Add memo (limit to 28 characters)
            if (strlen($memoText) > 28) {
                $memoText = substr($memoText, 0, 28);
            }
            
            // Create transaction builder
            $transaction = (new TransactionBuilder($sender))
                ->setMaxOperationFee($this->baseFee) // Using setMaxOperationFee instead of setBaseFee
                ->addMemo(Memo::text($memoText))
                ->addOperation(
                    // Add payment operation with native XLM asset
                    //(new PaymentOperation(MuxedAccount::fromAccountId($campaignAddress), Asset::native(), $amount))
                    $paymentOperation
                )
                ->build();
            error_log(">>> Stellar transaction pre-sign: ".json_encode($transaction));
            
            // If recurring, add metadata to transaction record
            if ($isRecurring) {
                // Calculate next payment date
                $nextPaymentDate = new DateTime();
                switch ($frequency) {
                    case 'weekly':
                        $nextPaymentDate->modify('+1 week');
                        break;
                    case 'monthly':
                        $nextPaymentDate->modify('+1 month');
                        break;
                    case 'quarterly':
                        $nextPaymentDate->modify('+3 months');
                        break;
                    case 'annually':
                        $nextPaymentDate->modify('+1 year');
                        break;
                    default:
                        $nextPaymentDate->modify('+1 month'); // Default to monthly
                }
                
                $transactionRecord['recurringDetails'] = [
                    'frequency' => $frequency,
                    'startDate' => new MongoDB\BSON\UTCDateTime(),
                    'nextProcessing' => new MongoDB\BSON\UTCDateTime($nextPaymentDate->getTimestamp() * 1000),
                    'status' => 'active',
                    'totalProcessed' => 1
                ];
            }
            error_log(">>>> transactionRecord: ".print_r($transactionRecord, true));
            
            // Sign and submit transaction
            $x = $transaction->sign($senderKeyPair, $this->network);
            error_log("==== Post sign ".print_r($x, true)."\n"); 
            try {
                $response = $sdk->submitTransaction($transaction);
                
                if ($response->isSuccessful()) {
                    error_log("!!!!!!! Stellar transaction successful!!!");
                }
                error_log(">>> Stellar response: ".json_encode($response));
                // Handle successful transaction
                $hash = $response->getHash();
                
                // Update transaction record with success details
                $transactionRecord['transaction']['txHash'] = $hash;
                $transactionRecord['transaction']['stellarAddress'] = $campaignAddress;
                $transactionRecord['transaction']['status'] = 'completed';
                $transactionRecord['transaction']['timestamp'] = new MongoDB\BSON\UTCDateTime();
                $transactionRecord['status'] = 'completed';
                
                // Save transaction record to database
                $db = new Database();
                $donationsCollection = $db->getCollection('donations');
                $result = $donationsCollection->insertOne($transactionRecord);
                
                // Update campaign funding stats
                $this->updateCampaignFunding($campaignId, (float)$amount);
                
                // Update donor record
                $this->updateDonorRecord($donorId, (float)$amount, $campaignId, $isRecurring, $transactionRecord);
                
                return [
                    'success' => true,
                    'transactionHash' => $hash,
                    'transactionId' => $result['id'] ?? null
                ];
            } catch (\Exception $e) {
                $responseBody = $e->getHttpResponse()->getBody()->__toString();
                $errorData = json_decode($responseBody, true);

                if (isset($errorData['extras']) && isset($errorData['extras']['result_codes'])) {
                    $resultCodes = $errorData['extras']['result_codes'];
                    error_log(">>>>> Result codes: " . json_encode($resultCodes));
                }
                // Handle transaction submission error
                $errorMessage = $e->getMessage();
                $this->lastError = $errorMessage;
                
                // Update transaction record with failure details
                $transactionRecord['transaction']['status'] = 'failed';
                $transactionRecord['transaction']['error'] = $errorMessage;
                $transactionRecord['status'] = 'failed';
                
                // Save failed transaction record
                $db = new Database();
                $donationsCollection = $db->getCollection('donations');
                $donationsCollection->insertOne($transactionRecord);
                
                throw new Exception("Transaction submission failed: {$errorMessage}");
            }
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update campaign funding stats
     * @param string $campaignId Campaign ID
     * @param float $amount Donation amount
     * @return void
     */
    private function updateCampaignFunding($campaignId, $amount) {
        try {
            $db = new Database();
            $campaignsCollection = $db->getCollection('campaigns');
            
            // Update campaign funding stats
            $campaignsCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($campaignId)],
                [
                    '$inc' => [
                        'funding.raisedAmount' => $amount,
                        'funding.donorCount' => 1
                    ],
                    '$set' => [
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            // Check for milestone activation
            $campaign = $campaignsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaignId)]);
            
            if ($campaign && isset($campaign['timeline']) && isset($campaign['timeline']['milestones'])) {
                foreach ($campaign['timeline']['milestones'] as $milestone) {
                    // If milestone is pending and has a funding target that's been reached
                    if ($milestone['status'] === 'pending' && 
                        isset($milestone['fundingTarget']) && 
                        $campaign['funding']['raisedAmount'] >= $milestone['fundingTarget']) {
                        
                        // Update milestone status
                        $campaignsCollection->updateOne(
                            [
                                '_id' => new MongoDB\BSON\ObjectId($campaignId),
                                'timeline.milestones._id' => $milestone['_id']
                            ],
                            [
                                '$set' => [
                                    'timeline.milestones.$.status' => 'active',
                                    'timeline.milestones.$.activatedDate' => new MongoDB\BSON\UTCDateTime()
                                ]
                            ]
                        );
                        
                        // Create milestone notification
                        $this->createMilestoneNotification($campaignId, $milestone['_id']);
                    }
                }
            }
        } catch (\Exception $e) {
            // Log error, but don't fail the overall transaction
            error_log("Error updating campaign funding: " . $e->getMessage());
        }
    }
    
    /**
     * Create notification for milestone activation
     * @param string $campaignId Campaign ID
     * @param string $milestoneId Milestone ID
     * @return void
     */
    private function createMilestoneNotification($campaignId, $milestoneId) {
        try {
            $db = new Database();
            
            // Get campaign details
            $campaignsCollection = $db->getCollection('campaigns');
            $campaign = $campaignsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaignId)]);
            
            if (!$campaign) return;
            
            // Find the specific milestone
            $milestone = null;
            foreach ($campaign['timeline']['milestones'] as $m) {
                if ($m['_id'] == $milestoneId) {
                    $milestone = $m;
                    break;
                }
            }
            
            if (!$milestone) return;
            
            // Create notification
            $notificationsCollection = $db->getCollection('notifications');
            $notification = [
                'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
                'userId' => $campaign['creator'],
                'type' => 'milestone_activated',
                'title' => 'Milestone Activated',
                'message' => "Milestone \"" . $milestone['title'] . "\" has been activated in your campaign \"" . $campaign['title'] . "\"",
                'data' => [
                    'milestoneId' => $milestone['_id'],
                    'milestoneTitle' => $milestone['title'],
                    'campaignTitle' => $campaign['title']
                ],
                'read' => false,
                'createdAt' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $notificationsCollection->insertOne($notification);
        } catch (\Exception $e) {
            // Log error but don't fail
            error_log("Error creating milestone notification: " . $e->getMessage());
        }
    }
    
    /**
     * Update donor record with donation information
     * @param string $donorId Donor ID
     * @param float $amount Donation amount
     * @param string $campaignId Campaign ID
     * @param bool $isRecurring Whether this is a recurring donation
     * @param array $transactionRecord Full transaction record
     * @return void
     */
    private function updateDonorRecord($donorId, $amount, $campaignId, $isRecurring, $transactionRecord) {
        try {
            $db = new Database();
            $donorsCollection = $db->getCollection('donors');
            
            // Get donor record
            $donor = $donorsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($donorId)]);
            
            if (!$donor) {
                // If donor doesn't exist in donors collection, try to get from users collection
                $usersCollection = $db->getCollection('users');
                $user = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($donorId)]);
                
                if (!$user) {
                    error_log("Donor not found for ID: {$donorId}");
                    return;
                }
                
                // Create new donor record
                $donor = [
                    '_id' => new MongoDB\BSON\ObjectId($donorId),
                    'email' => $user['email'] ?? '',
                    'name' => isset($user['personalInfo']) ? ($user['personalInfo']['firstName'] . ' ' . $user['personalInfo']['lastName']) : $user['username'],
                    'status' => 'active',
                    'donationType' => $isRecurring ? 'recurring' : 'one-time',
                    'totalDonated' => 0,
                    'donationHistory' => [],
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'lastActive' => new MongoDB\BSON\UTCDateTime()
                ];
                
                $donorsCollection->insertOne($donor);
            }
            
            // Prepare the update
            $update = [
                '$inc' => ['totalDonated' => $amount],
                '$set' => [
                    'lastDonation' => new MongoDB\BSON\UTCDateTime(),
                    'lastActive' => new MongoDB\BSON\UTCDateTime(),
                    'status' => 'active'
                ],
                '$push' => [
                    'donationHistory' => [
                        'amount' => $amount,
                        'date' => new MongoDB\BSON\UTCDateTime(),
                        'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
                        'recurring' => $isRecurring
                    ]
                ]
            ];
            
            // Add recurring details if applicable
            if ($isRecurring && isset($transactionRecord['recurringDetails'])) {
                $update['$set']['donationType'] = 'recurring';
                $update['$set']['recurringDetails'] = $transactionRecord['recurringDetails'];
            }
            
            // Update the donor record
            $donorsCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($donorId)],
                $update
            );
        } catch (\Exception $e) {
            // Log error but don't fail
            error_log("Error updating donor record: " . $e->getMessage());
        }
    }
    
    /**
     * Process milestone funding release
     * @param array $params Release parameters
     * @return array Result of the release
     */
    public function releaseMilestoneFunding($params) {
        try {
            // Required parameters
            $campaignId = $params['campaignId'] ?? null;
            $milestoneId = $params['milestoneId'] ?? null;
            $authorizedBy = $params['authorizedBy'] ?? null;
            $amount = $params['amount'] ?? null;
            
            // Validate required parameters
            if (!$campaignId || !$milestoneId || !$authorizedBy) {
                throw new Exception("Missing required parameters: campaignId, milestoneId, authorizedBy");
            }
            
            $db = new Database();
            
            // Get campaign details
            $campaignsCollection = $db->getCollection('campaigns');
            $campaign = $campaignsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaignId)]);
            
            if (!$campaign) {
                throw new Exception("Campaign not found: {$campaignId}");
            }
            
            // Find the specific milestone
            $milestone = null;
            foreach ($campaign['timeline']['milestones'] as $m) {
                if ($m['_id'] == $milestoneId) {
                    $milestone = $m;
                    break;
                }
            }
            
            if (!$milestone) {
                throw new Exception("Milestone not found: {$milestoneId}");
            }
            
            // Verify milestone status is active
            if ($milestone['status'] !== 'active') {
                throw new Exception("Milestone is not active: {$milestoneId} (status: {$milestone['status']})");
            }
            
            // Verify user has authorization
            $isCreator = $campaign['creator'] == $authorizedBy;
            $isAdmin = $this->isUserAdmin($authorizedBy);
            
            if (!$isCreator && !$isAdmin) {
                throw new Exception("User not authorized to release milestone funds: {$authorizedBy}");
            }
            
            // Get escrow details
            $escrowsCollection = $db->getCollection('escrows');
            $escrow = $escrowsCollection->findOne([
                'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
                'milestones.id' => $milestoneId
            ]);
            
            if (!$escrow) {
                throw new Exception("Escrow not found for milestone: {$milestoneId}");
            }
            
            // Find milestone in escrow
            $escrowMilestone = null;
            foreach ($escrow['milestones'] as $m) {
                if ($m['id'] === $milestoneId) {
                    $escrowMilestone = $m;
                    break;
                }
            }
            
            if (!$escrowMilestone) {
                throw new Exception("Milestone not found in escrow: {$milestoneId}");
            }
            
            // Validate amount
            $releaseAmount = $amount ?? $escrowMilestone['amount'];
            if (floatval($releaseAmount) <= 0) {
                throw new Exception("Invalid release amount: {$releaseAmount}");
            }
            
            // Create Stellar keypair from escrow secret
            $escrowKeypair = Keypair::fromSeed($escrow['escrowSecretKey']);
            $escrowAccountId = $escrowKeypair->getAccountId();
            $escrowAccount = $this->stellarSdk->accounts()->account($escrowAccountId);
            
            // Create transaction with memo and payment operation
            $transaction = (new TransactionBuilder($escrowAccount))
                ->setMaxOperationFee($this->baseFee) // Using setMaxOperationFee instead of setBaseFee
                ->addMemo(Memo::text("milestone:{$campaignId},{$milestoneId}"))
                ->addOperation(
                    // Payment operation to send XLM from escrow to campaign account
                    (new PaymentOperation(MuxedAccount::fromAccountId($campaign['stellarAddress']), Asset::native(), $releaseAmount))
                )
                ->build();
            
            // Sign and submit transaction
            $transaction->sign($escrowKeypair, $this->network);
            
            try {
                $response = $this->stellarSdk->submitTransaction($transaction);
                
                // Handle successful transaction
                $hash = $response->getHash();
                
                // Update milestone status in campaign
                $campaignsCollection->updateOne(
                    [
                        '_id' => new MongoDB\BSON\ObjectId($campaignId),
                        'timeline.milestones._id' => $milestone['_id']
                    ],
                    [
                        '$set' => [
                            'timeline.milestones.$.status' => 'completed',
                            'timeline.milestones.$.completedDate' => new MongoDB\BSON\UTCDateTime()
                        ]
                    ]
                );
                
                // Update escrow milestone status
                $escrowsCollection->updateOne(
                    [
                        '_id' => $escrow['_id'],
                        'milestones.id' => $milestoneId
                    ],
                    [
                        '$set' => [
                            'milestones.$.status' => 'completed',
                            'milestones.$.releasedDate' => new MongoDB\BSON\UTCDateTime(),
                            'milestones.$.releasedBy' => new MongoDB\BSON\ObjectId($authorizedBy),
                            'milestones.$.transactionHash' => $hash
                        ]
                    ]
                );
                
                // Create transaction record
                $transactionsCollection = $db->getCollection('transactions');
                $transactionRecord = [
                    'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
                    'milestoneId' => $milestone['_id'],
                    'amount' => [
                        'value' => floatval($releaseAmount),
                        'currency' => 'XLM'
                    ],
                    'transaction' => [
                        'txHash' => $hash,
                        'stellarAddress' => $campaign['stellarAddress'],
                        'status' => 'completed',
                        'timestamp' => new MongoDB\BSON\UTCDateTime()
                    ],
                    'type' => 'milestone',
                    'status' => 'completed',
                    'authorizedBy' => new MongoDB\BSON\ObjectId($authorizedBy),
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ];
                
                $transactionsCollection->insertOne($transactionRecord);
                
                // Create milestone completion notification
                $this->createMilestoneCompletionNotification($campaignId, $milestone['_id']);
                
                return [
                    'success' => true,
                    'transactionHash' => $hash,
                    'milestone' => [
                        'id' => $milestone['_id'],
                        'status' => 'completed',
                        'releasedAmount' => floatval($releaseAmount)
                    ]
                ];
            } catch (\Exception $e) {
                // Handle transaction submission error
                $errorMessage = $e->getMessage();
                $this->lastError = $errorMessage;
                
                throw new Exception("Transaction submission failed: {$errorMessage}");
            }
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create milestone completion notification
     * @param string $campaignId Campaign ID
     * @param string $milestoneId Milestone ID
     */
    private function createMilestoneCompletionNotification($campaignId, $milestoneId) {
        try {
            $db = new Database();
            
            // Get campaign details
            $campaignsCollection = $db->getCollection('campaigns');
            $campaign = $campaignsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaignId)]);
            
            if (!$campaign) return;
            
            // Find the specific milestone
            $milestone = null;
            foreach ($campaign['timeline']['milestones'] as $m) {
                if ($m['_id'] == $milestoneId) {
                    $milestone = $m;
                    break;
                }
            }
            
            if (!$milestone) return;
            
            // Create notification for campaign creator
            $notificationsCollection = $db->getCollection('notifications');
            $notification = [
                'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
                'userId' => $campaign['creator'],
                'type' => 'milestone_completed',
                'title' => 'Milestone Completed',
                'message' => "Milestone \"" . $milestone['title'] . "\" has been completed in your campaign \"" . $campaign['title'] . "\"",
                'data' => [
                    'milestoneId' => $milestone['_id'],
                    'milestoneTitle' => $milestone['title'],
                    'campaignTitle' => $campaign['title']
                ],
                'read' => false,
                'createdAt' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $notificationsCollection->insertOne($notification);
            
            // Notify all donors who contributed to this campaign
            $donationsCollection = $db->getCollection('donations');
            $donors = $donationsCollection->distinct('userId', [
                'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
                'status' => 'completed',
                'visibility' => 'public'  // Only notify donors who made public donations
            ]);
            
            foreach ($donors as $donorId) {
                // Skip campaign creator, they've already been notified
                if ($donorId == $campaign['creator']) continue;
                
                $donorNotification = [
                    'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
                    'userId' => $donorId,
                    'type' => 'milestone_completed',
                    'title' => 'Milestone Completed',
                    'message' => "A milestone \"" . $milestone['title'] . "\" has been completed in the campaign \"" . $campaign['title'] . "\" that you supported",
                    'data' => [
                        'milestoneId' => $milestone['_id'],
                        'milestoneTitle' => $milestone['title'],
                        'campaignTitle' => $campaign['title']
                    ],
                    'read' => false,
                    'createdAt' => new MongoDB\BSON\UTCDateTime()
                ];
                
                $notificationsCollection->insertOne($donorNotification);
            }
        } catch (\Exception $e) {
            // Log error but don't fail
            error_log("Error creating milestone completion notification: " . $e->getMessage());
        }
    }
    
    /**
     * Check if a user has admin privileges
     * @param string $userId User ID to check
     * @return bool Whether the user is an admin
     */
    private function isUserAdmin($userId) {
        try {
            $db = new Database();
            $usersCollection = $db->getCollection('users');
            
            $user = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
            
            if (!$user) return false;
            
            return isset($user['roles']) && in_array('admin', $user['roles']);
        } catch (\Exception $e) {
            // Log error but assume not admin
            error_log("Error checking admin status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set up a milestone escrow account
     * @param array $params Escrow parameters
     * @return array Result of the escrow setup
     */
    public function createMilestoneEscrow($params) {
        try {
            // Required parameters
            $campaignId = $params['campaignId'] ?? null;
            $sourceSecret = $params['sourceSecret'] ?? null;
            $milestones = $params['milestones'] ?? [];
            $initialFunding = $params['initialFunding'] ?? "1"; // Minimum 1 XLM
            
            // Validate required parameters
            if (!$campaignId || !$sourceSecret) {
                throw new Exception("Missing required parameters: campaignId, sourceSecret");
            }
            
            if (empty($milestones)) {
                throw new Exception("At least one milestone must be specified");
            }
            
            // Create new Stellar account for escrow
            $escrowKeypair = Keypair::random();
            $escrowPublicKey = $escrowKeypair->getAccountId();
            $escrowSecretKey = $escrowKeypair->getSecretSeed();
            
            // Create source keypair
            $sourceKeypair = Keypair::fromSeed($sourceSecret);
            $sourceAccountId = $sourceKeypair->getAccountId();
            $sourceAccount = $this->stellarSdk->accounts()->account($sourceAccountId);
            
            // Create transaction to fund escrow account
            $transaction = (new TransactionBuilder($sourceAccount))
                ->setMaxOperationFee($this->baseFee) // Using setMaxOperationFee instead of setBaseFee
                ->addMemo(Memo::text("escrow:{$campaignId}"))
                ->addOperation(
                    // Create account operation to fund the escrow account
                    (new CreateAccountOperation($escrowPublicKey, $initialFunding))
                )
                ->build();
            
            // Sign and submit transaction
            $transaction->sign($sourceKeypair, $this->network);
            
            try {
                $response = $this->stellarSdk->submitTransaction($transaction);
                $hash = $response->getHash();
                
                // Prepare milestones metadata
                $milestonesWithDates = [];
                foreach ($milestones as $index => $milestone) {
                    // Calculate release date if provided
                    $releaseDate = null;
                    if (isset($milestone['releaseDate'])) {
                        $releaseDate = new DateTime($milestone['releaseDate']);
                    } elseif (isset($milestone['releaseDays'])) {
                        $releaseDate = new DateTime();
                        $releaseDate->modify("+{$milestone['releaseDays']} days");
                    }
                    
                    $milestonesWithDates[] = [
                        'id' => $milestone['id'] ?? "milestone-" . ($index + 1),
                        'title' => $milestone['title'] ?? "Milestone " . ($index + 1),
                        'amount' => $milestone['amount'] ?? "0",
                        'releaseDate' => $releaseDate ? new MongoDB\BSON\UTCDateTime($releaseDate->getTimestamp() * 1000) : null,
                        'conditions' => $milestone['conditions'] ?? [],
                        'status' => 'pending'
                    ];
                }
                
                // Create escrow record in database
                $db = new Database();
                $escrowsCollection = $db->getCollection('escrows');
                
                $escrowRecord = [
                    'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
                    'escrowAccountId' => $escrowPublicKey,
                    'escrowSecretKey' => $escrowSecretKey,
                    'milestones' => $milestonesWithDates,
                    'transactionHash' => $hash,
                    'initialFunding' => floatval($initialFunding),
                    'createdAt' => new MongoDB\BSON\UTCDateTime(),
                    'createdBy' => isset($params['userId']) ? new MongoDB\BSON\ObjectId($params['userId']) : null,
                    'status' => 'active'
                ];
                
                $result = $escrowsCollection->insertOne($escrowRecord);
                
                return [
                    'success' => true,
                    'escrowAccountId' => $escrowPublicKey,
                    'escrowId' => $result['id'],
                    'transactionHash' => $hash
                ];
            } catch (\Exception $e) {
                // Handle transaction submission error
                $errorMessage = $e->getMessage();
                $this->lastError = $errorMessage;
                
                throw new Exception("Transaction submission failed: {$errorMessage}");
            }
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get transaction details for a specific transaction
     * @param string $transactionId Transaction ID
     * @return array Transaction details
     */
    public function getTransactionDetails($transactionId) {
        try {
            $db = new Database();
            $donationsCollection = $db->getCollection('donations');

            // Get transaction by ID
            $tx = $donationsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($transactionId)]);

            if (!$tx) {
                throw new Exception("Transaction not found: {$transactionId}");
            }

            $transaction = [
                'id' => (string)$tx['_id'],
                'amount' => $tx['amount']['value'],
                'currency' => $tx['amount']['currency'],
                'status' => $tx['status'],
                'type' => $tx['type'],
                'visibility' => $tx['visibility'] ?? 'public',
                'date' => $tx['createdAt']->toDateTime()->format('Y-m-d H:i:s'),
                'txHash' => $tx['transaction']['txHash'] ?? null,
                'stellarAddress' => $tx['transaction']['stellarAddress'] ?? null,
                'updatedAt' => $tx['updatedAt']->toDateTime()->format('Y-m-d H:i:s'),
            ];

            // Get blockchain transaction details if hash exists
            if (isset($tx['transaction']['txHash'])) {
                try {
                    $txHash = $tx['transaction']['txHash'];
                    $horizonUrl = $this->useTestnet ?
                        'https://horizon-testnet.stellar.org' :
                        'https://horizon.stellar.org';

                    $response = file_get_contents("{$horizonUrl}/transactions/{$txHash}");
                    if ($response) {
                        $blockchainTx = json_decode($response, true);
                        $transaction['blockchain'] = [
                            'ledger' => $blockchainTx['ledger'] ?? null,
                            'created_at' => $blockchainTx['created_at'] ?? null,
                            'fee_charged' => $blockchainTx['fee_charged'] ?? null,
                            'memo' => $blockchainTx['memo'] ?? null,
                            'memo_type' => $blockchainTx['memo_type'] ?? null,
                        ];
                    }
                } catch (\Exception $e) {
                    // Just skip blockchain details if there's an error
                    error_log("Error fetching blockchain transaction: " . $e->getMessage());
                }
            }

            // Add donor info
            if (isset($tx['userId'])) {
                $donor = $db->getCollection('donors')->findOne(['_id' => $tx['userId']]);
                if ($donor) {
                    $transaction['donor'] = [
                        'id' => (string)$donor['_id'],
                        'name' => $tx['visibility'] === 'anonymous' ? 'Anonymous Donor' : ($donor['name'] ?? 'Unknown Donor'),
                        'email' => $tx['visibility'] === 'anonymous' ? null : ($donor['email'] ?? null),
                    ];
                }
            }

            // Add campaign info
            if (isset($tx['campaignId'])) {
                $campaign = $db->getCollection('campaigns')->findOne(['_id' => $tx['campaignId']]);
                if ($campaign) {
                    $transaction['campaign'] = [
                        'id' => (string)$campaign['_id'],
                        'title' => $campaign['title'] ?? 'Unknown Campaign',
                        'image' => $campaign['image'] ?? null,
                        'creator' => isset($campaign['creator']) ? (string)$campaign['creator'] : null,
                    ];
                }
            }

            // Add recurring details if applicable
            if ($tx['type'] === 'recurring' && isset($tx['recurringDetails'])) {
                $transaction['recurring'] = [
                    'frequency' => $tx['recurringDetails']['frequency'],
                    'status' => $tx['recurringDetails']['status'],
                    'startDate' => $tx['recurringDetails']['startDate']->toDateTime()->format('Y-m-d H:i:s'),
                    'nextPayment' => isset($tx['recurringDetails']['nextProcessing']) ?
                        $tx['recurringDetails']['nextProcessing']->toDateTime()->format('Y-m-d H:i:s') : null,
                    'totalProcessed' => $tx['recurringDetails']['totalProcessed'] ?? 1,
                ];

                // Add cancellation info if cancelled
                if ($tx['recurringDetails']['status'] === 'cancelled' && isset($tx['recurringDetails']['cancelledDate'])) {
                    $transaction['recurring']['cancelledDate'] = $tx['recurringDetails']['cancelledDate']->toDateTime()->format('Y-m-d H:i:s');
                    $transaction['recurring']['cancelledBy'] = isset($tx['recurringDetails']['cancelledBy']) ?
                        (string)$tx['recurringDetails']['cancelledBy'] : null;
                }
            }

            // Add milestone info if it's a milestone transaction
            if ($tx['type'] === 'milestone' && isset($tx['milestoneId'])) {
                $campaign = $db->getCollection('campaigns')->findOne([
                    '_id' => $tx['campaignId'],
                    'timeline.milestones._id' => $tx['milestoneId']
                ]);

                if ($campaign && isset($campaign['timeline']) && isset($campaign['timeline']['milestones'])) {
                    foreach ($campaign['timeline']['milestones'] as $milestone) {
                        if ($milestone['_id'] == $tx['milestoneId']) {
                            $transaction['milestone'] = [
                                'id' => (string)$milestone['_id'],
                                'title' => $milestone['title'],
                                'status' => $milestone['status'],
                                'completedDate' => isset($milestone['completedDate']) ?
                                    $milestone['completedDate']->toDateTime()->format('Y-m-d H:i:s') : null,
                            ];
                            break;
                        }
                    }
                }
            }

            return [
                'success' => true,
                'transaction' => $transaction
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
     * Get Stellar blockchain transaction details
     * @param string $txHash Transaction hash
     * @return array Transaction details from Stellar blockchain
     */
    public function getStellarTransactionDetails($txHash) {
        try {
            // Get transaction details via SDK instead of raw API call
            $transaction = $this->stellarSdk->transactions()->transaction($txHash);
            
            if (!$transaction) {
                throw new Exception("Unable to retrieve transaction: {$txHash}");
            }
            
            // Extract and format relevant details
            $result = [
                'hash' => $transaction->getHash(),
                'ledger' => $transaction->getLedger(),
                'created_at' => $transaction->getCreatedAt(),
                'source_account' => $transaction->getSourceAccount(),
                'fee_charged' => $transaction->getFeeCharged(),
                'successful' => $transaction->isSuccessful(),
                'memo_type' => $transaction->getMemoType(),
                'memo' => $transaction->getMemo(),
            ];
            
            // Get operations for this transaction
            try {
                $operations = $this->stellarSdk->operations()->forTransaction($txHash)->execute();
                $result['operations'] = [];
                
                if ($operations && count($operations->getOperations()) > 0) {
                    foreach ($operations->getOperations() as $op) {
                        $opDetails = [
                            'id' => $op->getOperationId(),
                            'type' => $op->getType(),
                            'source_account' => $op->getSourceAccount(),
                            'created_at' => $op->getCreatedAt(),
                            'details' => $this->extractOperationDetails($op)
                        ];
                        
                        $result['operations'][] = $opDetails;
                    }
                }
            } catch (\Exception $e) {
                // If operations can't be fetched, continue with just transaction details
                error_log("Error fetching operations for transaction {$txHash}: " . $e->getMessage());
            }
            
            return [
                'success' => true,
                'transaction' => $result
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
     * Extract operation details based on operation type
     * @param object $operation Operation response object
     * @return array Extracted details
     */
    private function extractOperationDetails($operation) {
        $details = [];

        // Check the operation class to determine type
        if ($operation instanceof \Soneso\StellarSDK\Responses\Operations\PaymentOperationResponse) {
            $details = [
                'amount' => $operation->getAmount(),
                'asset_type' => $operation->getAsset()->getType(),
                'from' => $operation->getFrom(),
                'to' => $operation->getTo()
            ];
            
            // Add asset code and issuer if not native
            if ($operation->getAsset()->getType() !== 'native') {
                $details['asset_code'] = $operation->getAsset()->getCode();
                $details['asset_issuer'] = $operation->getAsset()->getIssuer();
            }
        } 
        elseif ($operation instanceof \Soneso\StellarSDK\Responses\Operations\CreateAccountOperationResponse) {
            $details = [
                'account' => $operation->getAccount(),
                'funder' => $operation->getFunder(),
                'starting_balance' => $operation->getStartingBalance()
            ];
        }
        elseif ($operation instanceof \Soneso\StellarSDK\Responses\Operations\PathPaymentStrictReceiveOperationResponse) {
            $details = [
                'from' => $operation->getFrom(),
                'to' => $operation->getTo(),
                'amount' => $operation->getAmount(),
                'asset_type' => $operation->getAsset()->getType(),
                'source_asset_type' => $operation->getSourceAsset()->getType(),
                'source_amount' => $operation->getSourceAmount()
            ];
            
            // Add path assets if present
            if (count($operation->getPath()) > 0) {
                $details['path'] = [];
                foreach ($operation->getPath() as $pathAsset) {
                    $details['path'][] = [
                        'asset_type' => $pathAsset->getType(),
                        'asset_code' => $pathAsset->getType() !== 'native' ? $pathAsset->getCode() : null,
                        'asset_issuer' => $pathAsset->getType() !== 'native' ? $pathAsset->getIssuer() : null
                    ];
                }
            }
        }
        elseif ($operation instanceof \Soneso\StellarSDK\Responses\Operations\PathPaymentStrictSendOperationResponse) {
            $details = [
                'from' => $operation->getFrom(),
                'to' => $operation->getTo(),
                'amount' => $operation->getDestAmount(),
                'asset_type' => $operation->getDestAsset()->getType(),
                'source_asset_type' => $operation->getSourceAsset()->getType(),
                'source_amount' => $operation->getSourceAmount()
            ];
        }
        elseif ($operation instanceof \Soneso\StellarSDK\Responses\Operations\ManageSellOfferOperationResponse) {
            $details = [
                'amount' => $operation->getAmount(),
                'price' => $operation->getPrice(),
                'selling_asset_type' => $operation->getSellingAsset()->getType(),
                'buying_asset_type' => $operation->getBuyingAsset()->getType(),
                'offer_id' => $operation->getOfferId()
            ];
        }
        elseif ($operation instanceof \Soneso\StellarSDK\Responses\Operations\ManageBuyOfferOperationResponse) {
            $details = [
                'amount' => $operation->getAmount(),
                'price' => $operation->getPrice(),
                'selling_asset_type' => $operation->getSellingAsset()->getType(),
                'buying_asset_type' => $operation->getBuyingAsset()->getType(),
                'offer_id' => $operation->getOfferId()
            ];
        }
        elseif ($operation instanceof \Soneso\StellarSDK\Responses\Operations\SetOptionsOperationResponse) {
            $details = [
                'home_domain' => $operation->getHomeDomain(),
                'inflation_dest' => $operation->getInflationDest(),
                'clear_flags' => $operation->getClearFlags(),
                'set_flags' => $operation->getSetFlags(),
                'master_key_weight' => $operation->getMasterKeyWeight(),
                'low_threshold' => $operation->getLowThreshold(),
                'med_threshold' => $operation->getMedThreshold(),
                'high_threshold' => $operation->getHighThreshold(),
                'signer_key' => $operation->getSignerKey(),
                'signer_weight' => $operation->getSignerWeight()
            ];
            
            // Remove null values
            foreach ($details as $key => $value) {
                if ($value === null) {
                    unset($details[$key]);
                }
            }
        }
        else {
            // For other operation types, attempt to extract common properties
            $details = [
                'type' => $operation->getType()
            ];
            
            // Try to extract additional properties using reflection
            $methods = get_class_methods($operation);
            foreach ($methods as $method) {
                if (substr($method, 0, 3) === 'get' && $method !== 'getType' && 
                    $method !== 'getOperationId' && $method !== 'getSourceAccount' && 
                    $method !== 'getCreatedAt' && $method !== 'getTransactionHash') {
                    
                    $property = lcfirst(substr($method, 3));
                    try {
                        $value = $operation->$method();
                        
                        // Skip object values except for Asset objects
                        if (is_object($value)) {
                            if ($value instanceof \Soneso\StellarSDK\Asset) {
                                $details[$property] = [
                                    'type' => $value->getType(),
                                    'code' => $value->getType() !== 'native' ? $value->getCode() : null,
                                    'issuer' => $value->getType() !== 'native' ? $value->getIssuer() : null
                                ];
                            }
                            continue;
                        }
                        
                        // Include simple values
                        if (!is_array($value) && !is_resource($value) && $value !== null) {
                            $details[$property] = $value;
                        }
                    } catch (\Exception $e) {
                        // Skip properties that can't be accessed
                        continue;
                    }
                }
            }
        }

        return $details;
    }

    /**
     * Get account transactions from Stellar blockchain
     * @param string $accountId Stellar account ID
     * @param array $options Filtering and pagination options
     * @return array Account transactions
     */
    public function getStellarAccountTransactions($accountId, $options = []) {
        try {
            // Create the transaction request builder
            $transactionsRequestBuilder = $this->stellarSdk->transactions()->forAccount($accountId);
            
            // Apply options/filters
            if (isset($options['limit'])) {
                $transactionsRequestBuilder->limit(intval($options['limit']));
            }
            
            if (isset($options['cursor'])) {
                $transactionsRequestBuilder->cursor($options['cursor']);
            }
            
            if (isset($options['order']) && in_array($options['order'], ['asc', 'desc'])) {
                $transactionsRequestBuilder->order($options['order']);
            }
            
            // Execute request
            $response = $transactionsRequestBuilder->execute();
            
            // Extract and format transactions
            $transactions = [];
            
            foreach ($response->getTransactions() as $tx) {
                $transaction = [
                    'hash' => $tx->getHash(),
                    'ledger' => $tx->getLedger(),
                    'created_at' => $tx->getCreatedAt(),
                    'source_account' => $tx->getSourceAccount(),
                    'successful' => $tx->isSuccessful(),
                    'memo_type' => $tx->getMemoType(),
                    'memo' => $tx->getMemo(),
                ];
                
                $transactions[] = $transaction;
            }
            
            // Get pagination links
            $links = [
                'next' => null,
                'prev' => null,
            ];
            
            // Check for pagination links in response
            $responseLinks = $response->getLinks();
            if ($responseLinks) {
                // Handle next link
                if (array_key_exists('next', $responseLinks)) {
                    $nextLink = $responseLinks['next'];
                    if ($nextLink && method_exists($nextLink, 'getHref')) {
                        $links['next'] = $nextLink->getHref();
                    }
                }
                
                // Handle previous link
                if (array_key_exists('prev', $responseLinks)) {
                    $prevLink = $responseLinks['prev'];
                    if ($prevLink && method_exists($prevLink, 'getHref')) {
                        $links['prev'] = $prevLink->getHref();
                    }
                }
            }
            
            return [
                'success' => true,
                'transactions' => $transactions,
                'next' => $links['next'],
                'prev' => $links['prev'],
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
     * Check account balance on Stellar
     * @param string $accountId Stellar account ID
     * @return array Account balance information
     */
    public function checkStellarAccountBalance($accountId) {
        try {
            // Load account using the SDK
            $account = $this->stellarSdk->accounts()->account($accountId);
            
            if (!$account) {
                throw new Exception("Unable to retrieve account: {$accountId}");
            }
            
            // Format balances in a consistent way
            $balances = [];
            
            foreach ($account->getBalances() as $balance) {
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
            
            // Return formatted account info
            return [
                'success' => true,
                'account_id' => $accountId,
                'balances' => $balances,
                'sequence' => $account->getSequence(),
                'last_modified_time' => $account->getLastModifiedTime(),
                'subentry_count' => $account->getSubentryCount(),
                'home_domain' => $account->getHomeDomain()
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
     * Ensures a campaign has an associated wallet
     * If no wallet exists, creates one and updates the campaign
     * @param array $campaign The campaign document
     * @return array|bool The wallet document if successful, false otherwise
     */
    private function ensureCampaignHasWallet($campaign) {
        try {
            $campaignId = (string)$campaign['_id'];
            
            error_log("TransactionProcessor: Ensuring campaign has wallet for ID: " . $campaignId);
            
            // First check if the campaign already has a walletId
            if (isset($campaign['walletId']) && !empty($campaign['walletId'])) {
                $db = new Database();
                $walletsCollection = $db->getCollection('wallets');
                $wallet = $walletsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaign['walletId'])]);
                
                if ($wallet) {
                    error_log("TransactionProcessor: Campaign already has wallet ID: " . $campaign['walletId']);
                    return $wallet;
                }
                
                error_log("TransactionProcessor: Campaign has walletId but wallet not found: " . $campaign['walletId']);
            }
            
            // Use the Wallet class to create the wallet
            error_log("TransactionProcessor: Creating new wallet for campaign: " . $campaignId);
            $wallet = new Wallet();
            $result = $wallet->createCampaignWallet(['campaignId' => $campaignId]);
            
            if (is_array($result) && isset($result['success']) && $result['success']) {
                // Get the wallet from the database to return the full document
                $db = new Database();
                $walletsCollection = $db->getCollection('wallets');
                
                try {
                    $walletId = $result['wallet']['id'] ?? null;
                    if (!$walletId) {
                        error_log("TransactionProcessor: Wallet ID not found in result: " . json_encode($result));
                        return false;
                    }
                    
                    $walletDoc = $walletsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($walletId)]);
                    
                    if (!$walletDoc) {
                        error_log("TransactionProcessor: Wallet not found after creation for ID: " . $walletId);
                        return false;
                    }
                    
                    error_log("TransactionProcessor: Successfully created and fetched wallet: " . $walletId);
                    return $walletDoc;
                } catch (\Exception $e) {
                    error_log("TransactionProcessor: Error fetching created wallet: " . $e->getMessage());
                    return false;
                }
            }
            
            error_log("TransactionProcessor: Failed to create wallet for campaign: " . ($result['error'] ?? "Unknown error"));
            return false;
        } catch (Exception $e) {
            error_log("TransactionProcessor: Error creating wallet for campaign: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a campaign donation report
     * @param string $campaignId Campaign ID
     * @param array $options Report options
     * @return array Donation report
     */
    public function createCampaignDonationReport($campaignId, $options = []) {
        try {
            $db = new Database();
            $donationsCollection = $db->getCollection('donations');

            // Base query for campaign donations
            $query = [
                'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
                'status' => 'completed'
            ];

            // Apply date range filter
            if (isset($options['dateFrom'])) {
                if (!isset($query['createdAt'])) {
                    $query['createdAt'] = [];
                }
                $query['createdAt']['$gte'] = new MongoDB\BSON\UTCDateTime(strtotime($options['dateFrom']) * 1000);
            }

            if (isset($options['dateTo'])) {
                if (!isset($query['createdAt'])) {
                    $query['createdAt'] = [];
                }
                $query['createdAt']['$lte'] = new MongoDB\BSON\UTCDateTime(strtotime($options['dateTo']) * 1000);
            }

            // Get all donations for the campaign
            $cursor = $donationsCollection->find($query);

            // Prepare report data
            $totalAmount = 0;
            $donorCount = 0;
            $donorIds = [];
            $oneTimeDonations = 0;
            $recurringDonations = 0;
            $anonymousDonations = 0;
            $publicDonations = 0;
            $monthlyData = [];
            $weeklyData = [];

            foreach ($cursor as $donation) {
                // Calculate totals
                $totalAmount += $donation['amount']['value'];

                // Track unique donors
                if (!in_array((string)$donation['userId'], $donorIds)) {
                    $donorIds[] = (string)$donation['userId'];
                    $donorCount++;
                }

                // Count donation types
                if ($donation['type'] === 'recurring') {
                    $recurringDonations++;
                } else {
                    $oneTimeDonations++;
                }

                // Count visibility types
                if ($donation['visibility'] === 'anonymous') {
                    $anonymousDonations++;
                } else {
                    $publicDonations++;
                }

                // Process for monthly chart data
                $date = $donation['createdAt']->toDateTime();
                $monthKey = $date->format('Y-m');

                if (!isset($monthlyData[$monthKey])) {
                    $monthlyData[$monthKey] = [
                        'month' => $date->format('M Y'),
                        'amount' => 0,
                        'count' => 0
                    ];
                }

                $monthlyData[$monthKey]['amount'] += $donation['amount']['value'];
                $monthlyData[$monthKey]['count']++;

                // Process for weekly chart data
                $weekNumber = $date->format('W');
                $year = $date->format('Y');
                $weekKey = $year . '-W' . $weekNumber;

                if (!isset($weeklyData[$weekKey])) {
                    $weeklyData[$weekKey] = [
                        'week' => 'Week ' . $weekNumber . ', ' . $year,
                        'amount' => 0,
                        'count' => 0
                    ];
                }

                $weeklyData[$weekKey]['amount'] += $donation['amount']['value'];
                $weeklyData[$weekKey]['count']++;
            }

            // Sort time series data
            ksort($monthlyData);
            ksort($weeklyData);

            // Get campaign details
            $campaignsCollection = $db->getCollection('campaigns');
            $campaign = $campaignsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaignId)]);

            $campaignDetails = null;
            if ($campaign) {
                $campaignDetails = [
                    'title' => $campaign['title'],
                    'status' => $campaign['status'],
                    'createdAt' => $campaign['createdAt']->toDateTime()->format('Y-m-d H:i:s'),
                    'targetAmount' => $campaign['funding']['targetAmount'] ?? 0,
                    'raisedAmount' => $campaign['funding']['raisedAmount'] ?? 0,
                    'progress' => ($campaign['funding']['targetAmount'] > 0) ?
                        round(($campaign['funding']['raisedAmount'] / $campaign['funding']['targetAmount']) * 100, 2) : 0,
                    'creator' => isset($campaign['creator']) ? (string)$campaign['creator'] : null,
                ];
            }

            return [
                'success' => true,
                'report' => [
                    'campaign' => $campaignDetails,
                    'summary' => [
                        'totalAmount' => $totalAmount,
                        'uniqueDonors' => $donorCount,
                        'totalDonations' => $oneTimeDonations + $recurringDonations,
                        'oneTimeDonations' => $oneTimeDonations,
                        'recurringDonations' => $recurringDonations,
                        'anonymousDonations' => $anonymousDonations,
                        'publicDonations' => $publicDonations,
                        'averageDonation' => ($oneTimeDonations + $recurringDonations > 0) ?
                            round($totalAmount / ($oneTimeDonations + $recurringDonations), 2) : 0,
                    ],
                    'charts' => [
                        'monthly' => array_values($monthlyData),
                        'weekly' => array_values($weeklyData),
                    ],
                    'dateRange' => [
                        'from' => isset($options['dateFrom']) ? $options['dateFrom'] : null,
                        'to' => isset($options['dateTo']) ? $options['dateTo'] : null,
                    ]
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

