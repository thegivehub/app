<?php
/**
 * Transaction API Controller
 * 
 * This class serves as the API controller for transaction operations, providing endpoints
 * that the transaction-demo.html page can use directly.
 */
require_once __DIR__ . '/TransactionProcessor.php';
require_once __DIR__ . '/BlockchainTransactionController.php';
require_once __DIR__ . '/Auth.php';

class Transaction {
    private $processor;
    private $blockchainController;
    private $useTestnet;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Default to testnet in development
        $this->useTestnet = defined('STELLAR_TESTNET') && STELLAR_TESTNET === true;
        
        // Initialize the transaction processor
        $this->processor = new TransactionProcessor($this->useTestnet);
        
        // Initialize blockchain transaction controller
        $this->blockchainController = new BlockchainTransactionController($this->useTestnet);
    }
    
    /**
     * Process a donation transaction
     * 
     * This method handles both one-time and recurring donations.
     * Used by the donation forms in the transaction demo.
     * 
     * @return array Response with transaction details or error
     */
    public function processDonation() {
        try {
            // Get request data
            $data = $this->getRequestData();
            
            // Validate required fields
            $requiredFields = ['donorId', 'campaignId', 'amount'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'error' => "Missing required field: {$field}"];
                }
            }
            
            // Process the donation using TransactionProcessor
            $result = $this->processor->processDonation([
                'donorId' => $data['donorId'],
                'campaignId' => $data['campaignId'],
                'amount' => $data['amount'],
                'sourceSecret' => $data['sourceSecret'] ?? null,
                'isAnonymous' => !empty($data['isAnonymous']),
                'message' => $data['message'] ?? '',
                'recurring' => !empty($data['recurring']),
                'frequency' => $data['frequency'] ?? 'monthly',
                'walletId' => $data['walletId'] ?? null
            ]);
            
            // Return the result
            return $result;
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error processing donation: ' . $e->getMessage()];
        }
    }
    
    /**
     * Create a milestone escrow account
     * 
     * This method creates an escrow account for milestone-based funding.
     * Used by the escrow form in the transaction demo.
     * 
     * @return array Response with escrow details or error
     */
    public function createMilestoneEscrow() {
        try {
            // Get request data
            $data = $this->getRequestData();
            
            // Validate required fields
            $requiredFields = ['campaignId', 'sourceSecret'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'error' => "Missing required field: {$field}"];
                }
            }
            
            // Validate milestones array
            if (empty($data['milestones']) || !is_array($data['milestones'])) {
                return ['success' => false, 'error' => 'Milestones must be provided as an array'];
            }
            
            // Process the escrow creation using TransactionProcessor
            $result = $this->processor->createMilestoneEscrow([
                'campaignId' => $data['campaignId'],
                'sourceSecret' => $data['sourceSecret'],
                'milestones' => $data['milestones'],
                'initialFunding' => $data['initialFunding'] ?? "1"
            ]);
            
            // Return the result
            return $result;
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error creating milestone escrow: ' . $e->getMessage()];
        }
    }
    
    /**
     * Release funds from a milestone escrow
     * 
     * This method releases funds from an escrow account for a specific milestone.
     * Used by the milestone release form in the transaction demo.
     * 
     * @return array Response with release details or error
     */
    public function releaseMilestoneFunding() {
        try {
            // Get request data
            $data = $this->getRequestData();
            
            // Validate required fields
            $requiredFields = ['campaignId', 'milestoneId', 'authorizedBy', 'amount'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'error' => "Missing required field: {$field}"];
                }
            }
            
            // Process the milestone funding release using TransactionProcessor
            $result = $this->processor->releaseMilestoneFunding([
                'campaignId' => $data['campaignId'],
                'milestoneId' => $data['milestoneId'],
                'authorizedBy' => $data['authorizedBy'],
                'amount' => $data['amount']
            ]);
            
            // Return the result
            return $result;
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error releasing milestone funding: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get transaction history
     * 
     * This method returns transaction history for a user or campaign.
     * Used by the transaction history section in the demo.
     * 
     * @return array Response with transaction history or error
     */
    public function getTransactionHistory() {
        try {
            // Get request data
            $data = $this->getRequestData();
            
            // Set default options
            $options = [
                'limit' => $data['limit'] ?? 10,
                'page' => $data['page'] ?? 1,
                'sort' => ['createdAt' => -1]
            ];
            
            // Determine what type of history to retrieve
            if (!empty($data['userId'])) {
                $transactions = $this->blockchainController->getUserTransactions($data['userId'], $options);
                $type = 'user';
            } elseif (!empty($data['campaignId'])) {
                $transactions = $this->blockchainController->getCampaignTransactions($data['campaignId'], $options);
                $type = 'campaign';
            } else {
                return ['success' => false, 'error' => 'Either userId or campaignId must be provided'];
            }
            
            return [
                'success' => true,
                'transactions' => $transactions,
                'type' => $type,
                'pagination' => [
                    'page' => (int) $options['page'],
                    'limit' => (int) $options['limit'],
                    'hasMore' => count($transactions) >= $options['limit']
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error retrieving transaction history: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get transaction details
     * 
     * This method returns details for a specific transaction.
     * 
     * @return array Response with transaction details or error
     */
    public function getTransaction() {
        try {
            // Get request data
            $data = $this->getRequestData();
            
            // Check for transaction hash or ID
            if (!empty($data['txHash'])) {
                $transaction = $this->blockchainController->getTransaction($data['txHash']);
            } elseif (!empty($data['transactionId'])) {
                $transaction = $this->blockchainController->getTransactionById($data['transactionId']);
            } else {
                return ['success' => false, 'error' => 'Either txHash or transactionId must be provided'];
            }
            
            if (!$transaction) {
                return ['success' => false, 'error' => 'Transaction not found'];
            }
            
            return [
                'success' => true,
                'transaction' => $transaction
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Error retrieving transaction: ' . $e->getMessage()];
        }
    }
    
    /**
     * Helper method to get request data
     * 
     * @return array Request data
     */
    private function getRequestData() {
        // Check if this is a POST request with JSON data
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        
        // If valid JSON was provided, use that
        if ($input !== null) {
            return $input;
        }
        
        // Otherwise, use $_POST or $_GET
        return $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
    }
}