<?php
// lib/DonationProcessor.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Mailer.php';

class DonationProcessor {
    private $db;
    private $auth;
    private $mailer;

    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
        $this->mailer = new Mailer();
    }

    /**
     * Process a new donation
     * 
     * @param array $data Donation data including amount, campaign, payment details
     * @return array Response with transaction ID and status
     */
    public function processDonation($data) {
        try {
            $this->validateDonationData($data);
            
            // Get user information if authenticated
            $userData = null;
            try {
                $userId = $this->auth->getUserIdFromToken();
                if ($userId) {
                    $userData = $this->db->getCollection('users')->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
                }
            } catch (Exception $e) {
                // Continue as guest donor if authentication fails
            }
            
            // Structure the donation document
            $donationData = $this->structureDonationData($data, $userData);
            
            // Begin transaction
            $transactionResult = $this->initiateTransaction($donationData);
            
            if (!$transactionResult['success']) {
                throw new Exception($transactionResult['error']);
            }
            
            // Save donation to database
            $donationsCollection = $this->db->getCollection('donations');
            $result = $donationsCollection->insertOne($donationData);
            
            if (!$result['success']) {
                throw new Exception('Failed to record donation');
            }
            
            // Update campaign funding progress
            $this->updateCampaignFunding($donationData['campaignId'], $donationData['amount']['value']);
            
            // Send confirmation email
            $this->sendConfirmationEmail($donationData);
            
            return [
                'success' => true,
                'transactionId' => $donationData['transaction']['txHash'],
                'donationId' => $result['id'],
                'status' => $donationData['status'],
                'message' => 'Donation processed successfully'
            ];
            
        } catch (Exception $e) {
            error_log('Donation processing error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate donation data
     */
    private function validateDonationData($data) {
        // Required fields
        $requiredFields = ['amount', 'currency', 'campaignId', 'paymentMethod'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Amount must be positive
        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new Exception('Invalid donation amount');
        }
        
        // Verify campaign exists
        $campaignCollection = $this->db->getCollection('campaigns');
        $campaign = $campaignCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($data['campaignId'])]);
        
        if (!$campaign) {
            throw new Exception('Campaign not found');
        }
        
        // Check if campaign is active
        if (isset($campaign['status']) && $campaign['status'] !== 'active') {
            throw new Exception('Campaign is not currently accepting donations');
        }
        
        // Validate payment method
        $validPaymentMethods = ['credit_card', 'stellar', 'crypto', 'bank_transfer'];
        if (!in_array($data['paymentMethod'], $validPaymentMethods)) {
            throw new Exception('Invalid payment method');
        }
        
        return true;
    }
    
    /**
     * Structure donation data for database
     */
    private function structureDonationData($data, $userData) {
        $now = new MongoDB\BSON\UTCDateTime();
        
        // Generate transaction ID
        $txHash = bin2hex(random_bytes(16));
        
        $donationType = isset($data['recurring']) && $data['recurring'] ? 'recurring' : 'one-time';
        
        $donation = [
            'campaignId' => new MongoDB\BSON\ObjectId($data['campaignId']),
            'amount' => [
                'value' => (float) $data['amount'],
                'currency' => $data['currency']
            ],
            'transaction' => [
                'txHash' => $txHash,
                'method' => $data['paymentMethod'],
                'status' => 'pending',
                'timestamp' => $now
            ],
            'type' => $donationType,
            'status' => 'pending',
            'visibility' => $data['visibility'] ?? 'public',
            'created' => $now,
            'updated' => $now
        ];
        
        // Add user information if available
        if ($userData) {
            $donation['userId'] = $userData['_id'];
            $donation['donor'] = [
                'name' => $userData['displayName'] ?? ($userData['personalInfo']['firstName'] . ' ' . $userData['personalInfo']['lastName']),
                'email' => $userData['email']
            ];
        } else if (isset($data['donorInfo'])) {
            // Guest donor
            $donation['donor'] = [
                'name' => $data['donorInfo']['name'] ?? 'Anonymous',
                'email' => $data['donorInfo']['email'] ?? null
            ];
        }
        
        // Add recurring details if applicable
        if ($donationType === 'recurring') {
            $donation['recurringDetails'] = [
                'frequency' => $data['frequency'] ?? 'monthly',
                'startDate' => $now,
                'nextProcessing' => new MongoDB\BSON\UTCDateTime(strtotime('+1 month') * 1000),
                'status' => 'active'
            ];
        }
        
        // Payment method specific details
        if ($data['paymentMethod'] === 'stellar' && isset($data['stellarAddress'])) {
            $donation['transaction']['stellarAddress'] = $data['stellarAddress'];
        } else if ($data['paymentMethod'] === 'credit_card' && isset($data['paymentToken'])) {
            $donation['transaction']['paymentToken'] = $data['paymentToken'];
        }
        
        // Add metadata
        $donation['metadata'] = [
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? null
        ];
        
        return $donation;
    }
    
    /**
     * Initiate payment transaction
     */
    private function initiateTransaction($donationData) {
        // This would connect to your payment processor or blockchain
        // For now, simulate transaction initiation
        
        switch ($donationData['transaction']['method']) {
            case 'credit_card':
                // Connect to payment processor API
                return $this->processCreditCardPayment($donationData);
                
            case 'stellar':
                // Connect to Stellar blockchain
                return $this->processStellarPayment($donationData);
                
            case 'crypto':
                // Connect to crypto payment processor
                return $this->processCryptoPayment($donationData);
                
            case 'bank_transfer':
                // Handle bank transfer
                return $this->processBankTransfer($donationData);
                
            default:
                return [
                    'success' => false,
                    'error' => 'Unsupported payment method'
                ];
        }
    }
    
    /**
     * Process credit card payment
     */
    private function processCreditCardPayment($donationData) {
        // In a real implementation, this would call the payment processor API
        // Simulate successful payment for now
        return [
            'success' => true,
            'transactionId' => $donationData['transaction']['txHash']
        ];
    }
    
    /**
     * Process Stellar blockchain payment
     */
    private function processStellarPayment($donationData) {
        // This would interact with Stellar blockchain
        // Simulate for now
        return [
            'success' => true,
            'transactionId' => $donationData['transaction']['txHash']
        ];
    }
    
    /**
     * Process crypto payment
     */
    private function processCryptoPayment($donationData) {
        // Connect to crypto payment processor
        return [
            'success' => true,
            'transactionId' => $donationData['transaction']['txHash']
        ];
    }
    
    /**
     * Process bank transfer
     */
    private function processBankTransfer($donationData) {
        // Handle bank transfer - usually this is manual
        return [
            'success' => true,
            'transactionId' => $donationData['transaction']['txHash'],
            'instructions' => 'Please transfer the amount to the organization bank account'
        ];
    }
    
    /**
     * Update campaign funding progress
     */
    private function updateCampaignFunding($campaignId, $amount) {
        $campaignCollection = $this->db->getCollection('campaigns');
        
        return $campaignCollection->updateOne(
            ['_id' => $campaignId],
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
    }
    
    /**
     * Send donation confirmation email
     */
    private function sendConfirmationEmail($donationData) {
        if (!isset($donationData['donor']['email'])) {
            return false; // No email to send to
        }
        
        // Get campaign details
        $campaignCollection = $this->db->getCollection('campaigns');
        $campaign = $campaignCollection->findOne(['_id' => $donationData['campaignId']]);
        
        $subject = "Thank you for your donation to " . ($campaign['title'] ?? 'our campaign');
        
        $message = "Thank you for your generous donation of " . 
                   $donationData['amount']['value'] . " " . $donationData['amount']['currency'] . 
                   " to " . ($campaign['title'] ?? 'our campaign') . ".\n\n";
                   
        $message .= "Transaction ID: " . $donationData['transaction']['txHash'] . "\n";
        $message .= "Status: " . ucfirst($donationData['status']) . "\n\n";
        
        if ($donationData['type'] === 'recurring') {
            $message .= "This is a recurring donation that will be processed " . 
                        $donationData['recurringDetails']['frequency'] . ".\n\n";
        }
        
        $message .= "Thank you for your support!\n";
        $message .= "The Give Hub Team";
        
        // Send email using Mailer class
        return $this->mailer->sendEmail(
            $donationData['donor']['email'],
            $subject,
            __DIR__ . '/../templates/donation-confirmation.html',
            (object)[
                'donorName' => $donationData['donor']['name'],
                'amount' => $donationData['amount']['value'],
                'currency' => $donationData['amount']['currency'],
                'campaignTitle' => $campaign['title'] ?? 'our campaign',
                'transactionId' => $donationData['transaction']['txHash'],
                'status' => ucfirst($donationData['status']),
                'donationType' => $donationData['type'],
                'frequency' => $donationData['recurringDetails']['frequency'] ?? null
            ]
        );
    }
    
    /**
     * Check status of a donation
     */
    public function getDonationStatus($transactionId) {
        $donationsCollection = $this->db->getCollection('donations');
        $donation = $donationsCollection->findOne(['transaction.txHash' => $transactionId]);
        
        if (!$donation) {
            return [
                'success' => false,
                'error' => 'Donation not found'
            ];
        }
        
        return [
            'success' => true,
            'status' => $donation['status'],
            'transactionStatus' => $donation['transaction']['status'],
            'donationId' => (string)$donation['_id'],
            'amount' => $donation['amount'],
            'campaignId' => (string)$donation['campaignId'],
            'created' => $donation['created']
        ];
    }
    
    /**
     * Update donation status (e.g., from webhook callback)
     */
    public function updateDonationStatus($transactionId, $status) {
        $donationsCollection = $this->db->getCollection('donations');
        
        $result = $donationsCollection->updateOne(
            ['transaction.txHash' => $transactionId],
            [
                '$set' => [
                    'status' => $status,
                    'transaction.status' => $status,
                    'updated' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
        
        if (!$result->getModifiedCount()) {
            return [
                'success' => false,
                'error' => 'Donation not found or status not updated'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Donation status updated'
        ];
    }
    
    /**
     * Process recurring donations that are due
     */
    public function processRecurringDonations() {
        $now = new MongoDB\BSON\UTCDateTime();
        $donationsCollection = $this->db->getCollection('donations');
        
        // Find recurring donations due for processing
        $dueDonations = $donationsCollection->find([
            'type' => 'recurring',
            'status' => 'completed',
            'recurringDetails.status' => 'active',
            'recurringDetails.nextProcessing' => ['$lte' => $now]
        ]);
        
        $processed = 0;
        $failed = 0;
        
        foreach ($dueDonations as $donation) {
            try {
                // Create a new donation based on the recurring one
                $newDonation = [
                    'campaignId' => $donation['campaignId'],
                    'userId' => $donation['userId'] ?? null,
                    'donor' => $donation['donor'],
                    'amount' => $donation['amount'],
                    'transaction' => [
                        'txHash' => bin2hex(random_bytes(16)),
                        'method' => $donation['transaction']['method'],
                        'status' => 'pending',
                        'timestamp' => $now
                    ],
                    'type' => 'recurring',
                    'status' => 'pending',
                    'visibility' => $donation['visibility'],
                    'recurringDetails' => [
                        'frequency' => $donation['recurringDetails']['frequency'],
                        'parentDonationId' => $donation['_id'],
                        'sequence' => ($donation['recurringDetails']['sequence'] ?? 0) + 1
                    ],
                    'created' => $now,
                    'updated' => $now
                ];
                
                // Process the payment
                $transactionResult = $this->initiateTransaction($newDonation);
                
                if (!$transactionResult['success']) {
                    throw new Exception($transactionResult['error']);
                }
                
                // Save the new donation
                $result = $donationsCollection->insertOne($newDonation);
                
                if (!$result['success']) {
                    throw new Exception('Failed to record recurring donation');
                }
                
                // Update the original donation's next processing date
                $nextProcessingDate = $this->calculateNextProcessingDate(
                    $donation['recurringDetails']['frequency']
                );
                
                $donationsCollection->updateOne(
                    ['_id' => $donation['_id']],
                    [
                        '$set' => [
                            'recurringDetails.nextProcessing' => $nextProcessingDate,
                            'recurringDetails.lastProcessed' => $now,
                            'recurringDetails.sequence' => ($donation['recurringDetails']['sequence'] ?? 0) + 1,
                            'updated' => $now
                        ]
                    ]
                );
                
                // Update campaign funding
                $this->updateCampaignFunding($donation['campaignId'], $donation['amount']['value']);
                
                // Send confirmation email
                $this->sendConfirmationEmail($newDonation);
                
                $processed++;
            } catch (Exception $e) {
                error_log('Failed to process recurring donation: ' . $e->getMessage());
                
                // Update failure count
                $donationsCollection->updateOne(
                    ['_id' => $donation['_id']],
                    [
                        '$inc' => [
                            'recurringDetails.failureCount' => 1
                        ],
                        '$set' => [
                            'updated' => $now
                        ]
                    ]
                );
                
                $failed++;
            }
        }
        
        return [
            'success' => true,
            'processed' => $processed,
            'failed' => $failed
        ];
    }
    
    /**
     * Calculate next processing date based on frequency
     */
    private function calculateNextProcessingDate($frequency) {
        switch ($frequency) {
            case 'weekly':
                $nextDate = strtotime('+1 week');
                break;
            case 'monthly':
                $nextDate = strtotime('+1 month');
                break;
            case 'quarterly':
                $nextDate = strtotime('+3 months');
                break;
            case 'annually':
                $nextDate = strtotime('+1 year');
                break;
            default:
                $nextDate = strtotime('+1 month');
        }
        
        return new MongoDB\BSON\UTCDateTime($nextDate * 1000);
    }
    
    /**
     * Cancel a recurring donation
     */
    public function cancelRecurringDonation($donationId) {
        $donationsCollection = $this->db->getCollection('donations');
        
        $result = $donationsCollection->updateOne(
            [
                '_id' => new MongoDB\BSON\ObjectId($donationId),
                'type' => 'recurring'
            ],
            [
                '$set' => [
                    'recurringDetails.status' => 'cancelled',
                    'updated' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
        
        if (!$result->getModifiedCount()) {
            return [
                'success' => false,
                'error' => 'Recurring donation not found or already cancelled'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Recurring donation cancelled successfully'
        ];
    }
}
