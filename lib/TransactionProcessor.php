<?php
// lib/TransactionProcessor.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Model.php';

class TransactionProcessor extends Model {
    private $stellarSdk;
    private $horizonUrl;
    private $network;
    private $baseFee;
    private $lastError;
    
    public function __construct($useTestnet = true) {
        parent::__construct();
        
        // Load Stellar SDK (assuming composer requirement for stellar/stellar-sdk)
        // If not yet installed, run: composer require stellar/stellar-sdk
        if (!class_exists('\ZuluCrypto\StellarSdk\Stellar')) {
            throw new Exception('Stellar SDK not found. Please install via Composer.');
        }
        
        // Set network based on environment
        $this->useTestnet = $useTestnet;
        $this->horizonUrl = $useTestnet 
            ? 'https://horizon-testnet.stellar.org' 
            : 'https://horizon.stellar.org';
            
        $this->network = $useTestnet
            ? \ZuluCrypto\StellarSdk\Network::testnet()
            : \ZuluCrypto\StellarSdk\Network::public();
        
        // Initialize Stellar SDK
        $this->stellarSdk = \ZuluCrypto\StellarSdk\Stellar::newClient($this->horizonUrl);
        
        // Set base fee (default is 100 stroops = 0.00001 XLM)
        $this->baseFee = 100;
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
            // Required parameters
            $donorId = $params['donorId'] ?? null;
            $campaignId = $params['campaignId'] ?? null;
            $amount = $params['amount'] ?? null;
            $sourceSecret = $params['sourceSecret'] ?? null;
            $isAnonymous = $params['isAnonymous'] ?? false;
            $message = $params['message'] ?? '';
            $isRecurring = $params['recurring'] ?? false;
            $frequency = $params['frequency'] ?? 'monthly';
            
            // Validate required parameters
            if (!$donorId || !$campaignId || !$amount || !$sourceSecret) {
                throw new Exception('Missing required parameters: donorId, campaignId, amount, sourceSecret');
            }
            
            // Get campaign details from MongoDB
            $campaign = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($campaignId)]);
            if (!$campaign) {
                throw new Exception("Campaign not found: {$campaignId}");
            }
            
            // Get campaign's Stellar address
            $campaignAddress = $campaign['stellarAddress'] ?? null;
            if (!$campaignAddress) {
                throw new Exception("Campaign does not have a Stellar address");
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
                'created' => new MongoDB\BSON\UTCDateTime(),
                'updated' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Create Stellar keypair from secret
            $sourceKeypair = \ZuluCrypto\StellarSdk\Keypair::newFromSeed($sourceSecret);
            $sourceAccount = $this->stellarSdk->requestAccount($sourceKeypair->getPublicKey());
            
            // Create transaction builder
            $transaction = (new \ZuluCrypto\StellarSdk\Transaction\TransactionBuilder($sourceAccount))
                ->setBaseFee($this->baseFee);
            
            // Prepare memo text
            $memoText = "campaign:{$campaignId}";
            if (!$isAnonymous) {
                $memoText .= ",donor:{$donorId}";
            }
            
            // Add memo (limit to 28 characters)
            if (strlen($memoText) > 28) {
                $memoText = substr($memoText, 0, 28);
            }
            $transaction->addMemo(new \ZuluCrypto\StellarSdk\Memo\MemoText($memoText));
            
            // Add payment operation
            $transaction->addPaymentOperation(
                $campaignAddress,
                $amount,
                \ZuluCrypto\StellarSdk\Asset\AssetTypeNative::getAssetType()
            );
            
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
            
            // Sign and submit transaction
            $transaction->sign($sourceKeypair);
            
            try {
                $response = $transaction->submit($this->network);
                
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
                        'updated' => new MongoDB\BSON\UTCDateTime()
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
                'created' => new MongoDB\BSON\UTCDateTime()
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
                    'created' => new MongoDB\BSON\UTCDateTime(),
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
            $escrowKeypair = \ZuluCrypto\StellarSdk\Keypair::newFromSeed($escrow['escrowSecretKey']);
            $escrowAccount = $this->stellarSdk->requestAccount($escrowKeypair->getPublicKey());
            
            // Create transaction builder
            $transaction = (new \ZuluCrypto\StellarSdk\Transaction\TransactionBuilder($escrowAccount))
                ->setBaseFee($this->baseFee);
            
            // Add payment operation
            $transaction->addPaymentOperation(
                $campaign['stellarAddress'],
                $releaseAmount,
                \ZuluCrypto\StellarSdk\Asset\AssetTypeNative::getAssetType()
            );
            
            // Add memo with milestone info
            $transaction->addMemo(new \ZuluCrypto\StellarSdk\Memo\MemoText("milestone:{$campaignId},{$milestoneId}"));
            
            // Sign and submit transaction
            $transaction->sign($escrowKeypair);
            
            try {
                $response = $transaction->submit($this->network);
                
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
                    'created' => new MongoDB\BSON\UTCDateTime(),
                    'updated' => new MongoDB\BSON\UTCDateTime()
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
                'created' => new MongoDB\BSON\UTCDateTime()
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
                    'created' => new MongoDB\BSON\UTCDateTime()
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
            $escrowKeypair = \ZuluCrypto\StellarSdk\Keypair::newFromRandom();
            $escrowPublicKey = $escrowKeypair->getPublicKey();
            $escrowSecretKey = $escrowKeypair->getSecret();
            
            // Create source keypair
            $sourceKeypair = \ZuluCrypto\StellarSdk\Keypair::newFromSeed($sourceSecret);
            $sourceAccount = $this->stellarSdk->requestAccount($sourceKeypair->getPublicKey());
            
            // Create transaction to fund escrow account
            $transaction = (new \ZuluCrypto\StellarSdk\Transaction\TransactionBuilder($sourceAccount))
                ->setBaseFee($this->baseFee)
                ->addCreateAccountOperation($escrowPublicKey, $initialFunding)
                ->addMemo(new \ZuluCrypto\StellarSdk\Memo\MemoText("escrow:{$campaignId}"));
            
            // Sign and submit transaction
            $transaction->sign($sourceKeypair);
            
            try {
                $response = $transaction->submit($this->network);
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
                    'created' => new MongoDB\BSON\UTCDateTime(),
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
                'date' => $tx['created']->toDateTime()->format('Y-m-d H:i:s'),
                'txHash' => $tx['transaction']['txHash'] ?? null,
                'stellarAddress' => $tx['transaction']['stellarAddress'] ?? null,
                'updated' => $tx['updated']->toDateTime()->format('Y-m-d H:i:s'),
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
            // Send request to Horizon API
            $horizonUrl = $this->useTestnet ?
                'https://horizon-testnet.stellar.org' :
                'https://horizon.stellar.org';

            $response = file_get_contents("{$horizonUrl}/transactions/{$txHash}");

            if (!$response) {
                throw new Exception("Unable to retrieve transaction: {$txHash}");
            }

            $transaction = json_decode($response, true);

            // Extract and format relevant details
            $result = [
                'hash' => $transaction['hash'],
                'ledger' => $transaction['ledger'],
                'created_at' => $transaction['created_at'],
                'source_account' => $transaction['source_account'],
                'fee_charged' => $transaction['fee_charged'],
                'successful' => $transaction['successful'],
                'memo_type' => $transaction['memo_type'],
                'memo' => $transaction['memo'],
            ];

            // Get operations if available
            if (isset($transaction['_links']['operations'])) {
                $opsResponse = file_get_contents($transaction['_links']['operations']['href']);

                if ($opsResponse) {
                    $operations = json_decode($opsResponse, true);
                    $result['operations'] = [];

                    if (isset($operations['_embedded']['records'])) {
                        foreach ($operations['_embedded']['records'] as $op) {
                            $result['operations'][] = [
                                'id' => $op['id'],
                                'type' => $op['type'],
                                'source_account' => $op['source_account'],
                                'created_at' => $op['created_at'],
                                'details' => $this->extractOperationDetails($op)
                            ];
                        }
                    }
                }
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
     * @param array $operation Operation data
     * @return array Extracted details
     */
    private function extractOperationDetails($operation) {
        $details = [];

        switch ($operation['type']) {
            case 'payment':
                $details = [
                    'amount' => $operation['amount'],
                    'asset_type' => $operation['asset_type'],
                    'from' => $operation['from'],
                    'to' => $operation['to']
                ];
                break;

            case 'create_account':
                $details = [
                    'account' => $operation['account'],
                    'funder' => $operation['funder'],
                    'starting_balance' => $operation['starting_balance']
                ];
                break;

            case 'path_payment_strict_receive':
            case 'path_payment_strict_send':
                $details = [
                    'from' => $operation['from'],
                    'to' => $operation['to'],
                    'asset_type' => $operation['asset_type'],
                    'amount' => $operation['amount'],
                    'source_asset_type' => $operation['source_asset_type'],
                    'source_amount' => $operation['source_amount']
                ];
                break;

            case 'manage_sell_offer':
            case 'manage_buy_offer':
                $details = [
                    'amount' => $operation['amount'],
                    'price' => $operation['price'],
                    'selling_asset_type' => $operation['selling_asset_type'],
                    'buying_asset_type' => $operation['buying_asset_type']
                ];
                break;

            case 'set_options':
                $details = [
                    'signer_key' => $operation['signer_key'] ?? null,
                    'signer_weight' => $operation['signer_weight'] ?? null,
                    'master_key_weight' => $operation['master_key_weight'] ?? null,
                    'low_threshold' => $operation['low_threshold'] ?? null,
                    'med_threshold' => $operation['med_threshold'] ?? null,
                    'high_threshold' => $operation['high_threshold'] ?? null,
                    'home_domain' => $operation['home_domain'] ?? null
                ];
                break;

            default:
                // For other operation types, just include all fields
                $details = $operation;

                // Remove common fields that are already included at the operation level
                unset($details['id']);
                unset($details['type']);
                unset($details['source_account']);
                unset($details['created_at']);
                unset($details['transaction_hash']);
                unset($details['_links']);
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
            $horizonUrl = $this->useTestnet ?
                'https://horizon-testnet.stellar.org' :
                'https://horizon.stellar.org';

            $url = "{$horizonUrl}/accounts/{$accountId}/transactions?";

            // Add parameters
            if (isset($options['limit'])) {
                $url .= "limit=" . intval($options['limit']) . "&";
            }

            if (isset($options['cursor'])) {
                $url .= "cursor=" . urlencode($options['cursor']) . "&";
            }

            if (isset($options['order']) && in_array($options['order'], ['asc', 'desc'])) {
                $url .= "order=" . $options['order'] . "&";
            }

            // Remove trailing & or ?
            $url = rtrim($url, "&?");

            $response = file_get_contents($url);

            if (!$response) {
                throw new Exception("Unable to retrieve transactions for account: {$accountId}");
            }

            $data = json_decode($response, true);

            $transactions = [];

            if (isset($data['_embedded']['records'])) {
                foreach ($data['_embedded']['records'] as $tx) {
                    $transaction = [
                        'hash' => $tx['hash'],
                        'ledger' => $tx['ledger'],
                        'created_at' => $tx['created_at'],
                        'source_account' => $tx['source_account'],
                        'successful' => $tx['successful'],
                        'memo_type' => $tx['memo_type'],
                        'memo' => $tx['memo'],
                    ];

                    $transactions[] = $transaction;
                }
            }

            return [
                'success' => true,
                'transactions' => $transactions,
                'next' => isset($data['_links']['next']) ? $data['_links']['next']['href'] : null,
                'prev' => isset($data['_links']['prev']) ? $data['_links']['prev']['href'] : null,
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
            $horizonUrl = $this->useTestnet ?
                'https://horizon-testnet.stellar.org' :
                'https://horizon.stellar.org';

            $response = file_get_contents("{$horizonUrl}/accounts/{$accountId}");

            if (!$response) {
                throw new Exception("Unable to retrieve account: {$accountId}");
            }

            $account = json_decode($response, true);

            $balances = [];

            if (isset($account['balances'])) {
                foreach ($account['balances'] as $balance) {
                    if ($balance['asset_type'] === 'native') {
                        $balances[] = [
                            'asset' => 'XLM',
                            'balance' => $balance['balance'],
                            'asset_type' => 'native'
                        ];
                    } else {
                        $balances[] = [
                            'asset' => $balance['asset_code'],
                            'issuer' => $balance['asset_issuer'],
                            'balance' => $balance['balance'],
                            'asset_type' => $balance['asset_type']
                        ];
                    }
                }
            }

            return [
                'success' => true,
                'account_id' => $accountId,
                'balances' => $balances,
                'sequence' => $account['sequence'] ?? null,
                'last_modified_time' => $account['last_modified_time'] ?? null,
                'subentry_count' => $account['subentry_count'] ?? 0,
                'home_domain' => $account['home_domain'] ?? null
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
                if (!isset($query['created'])) {
                    $query['created'] = [];
                }
                $query['created']['$gte'] = new MongoDB\BSON\UTCDateTime(strtotime($options['dateFrom']) * 1000);
            }

            if (isset($options['dateTo'])) {
                if (!isset($query['created'])) {
                    $query['created'] = [];
                }
                $query['created']['$lte'] = new MongoDB\BSON\UTCDateTime(strtotime($options['dateTo']) * 1000);
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
                $date = $donation['created']->toDateTime();
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
                    'created' => $campaign['created']->toDateTime()->format('Y-m-d H:i:s'),
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

