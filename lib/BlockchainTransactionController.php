<?php
/**
 * BlockchainTransactionController - Handles operations related to blockchain transactions
 */
require_once __DIR__ . '/db.php';

class BlockchainTransactionController {
    private $db;
    private $collection;
    private $horizonUrl;
    
    /**
     * Constructor
     * 
     * @param bool $useTestnet Whether to use the Stellar testnet
     */
    public function __construct($useTestnet = false) {
        $this->db = Database::getInstance();
        $this->collection = $this->db->getCollection('blockchain_transactions');
        
        // Set Horizon API URL based on network
        $this->horizonUrl = $useTestnet ? 
            'https://horizon-testnet.stellar.org' : 
            'https://horizon.stellar.org';
    }
    
    /**
     * Create a new blockchain transaction record
     * 
     * @param array $data Transaction data
     * @return array Result of the operation
     */
    public function createTransaction($data) {
        // Validate required fields
        if (empty($data['txHash'])) {
            return ['success' => false, 'error' => 'Transaction hash is required'];
        }
        
        if (empty($data['type'])) {
            return ['success' => false, 'error' => 'Transaction type is required'];
        }
        
        // Check if transaction already exists
        $existingTx = $this->collection->findOne(['txHash' => $data['txHash']]);
        if ($existingTx) {
            return [
                'success' => false, 
                'error' => 'Transaction with this hash already exists',
                'transactionId' => $existingTx['_id']
            ];
        }
        
        // Set default status if not provided
        if (empty($data['status'])) {
            $data['status'] = 'pending';
        }
        
        // Create status history entry
        $now = new MongoDB\BSON\UTCDateTime();
        $data['statusHistory'] = [
            [
                'status' => $data['status'],
                'timestamp' => $now,
                'details' => 'Transaction created'
            ]
        ];
        
        // Set timestamps
        $data['createdAt'] = $now;
        $data['updatedAt'] = $now;
        $data['lastChecked'] = $now;
        
        // Insert into database
        $result = $this->collection->insertOne($data);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Blockchain transaction record created successfully',
                'transactionId' => $result['id']
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to create blockchain transaction record: ' . ($result['error'] ?? 'Unknown error')
            ];
        }
    }
    
    /**
     * Update a blockchain transaction's status
     * 
     * @param string $txHash Transaction hash
     * @param string $status New status
     * @param string $details Optional details about the status change
     * @param array $additionalData Additional data to update
     * @return array Result of the operation
     */
    public function updateTransactionStatus($txHash, $status, $details = '', $additionalData = []) {
        // Validate status
        $validStatuses = ['pending', 'submitted', 'confirming', 'confirmed', 'failed', 'expired'];
        if (!in_array($status, $validStatuses)) {
            return [
                'success' => false, 
                'error' => 'Invalid status. Must be one of: ' . implode(', ', $validStatuses)
            ];
        }
        
        // Get current transaction
        $transaction = $this->collection->findOne(['txHash' => $txHash]);
        if (!$transaction) {
            return ['success' => false, 'error' => 'Transaction not found'];
        }
        
        // Don't update if status is the same (unless forced)
        if ($transaction['status'] === $status && empty($additionalData['force'])) {
            return [
                'success' => true,
                'message' => 'Transaction status unchanged',
                'transactionId' => $transaction['_id']
            ];
        }
        
        // Prepare update data
        $now = new MongoDB\BSON\UTCDateTime();
        $updateData = [
            'status' => $status,
            'updatedAt' => $now,
            'lastChecked' => $now
        ];
        
        // Add status history entry
        $statusHistoryEntry = [
            'status' => $status,
            'timestamp' => $now,
            'details' => $details
        ];
        
        // Merge additional data
        if (!empty($additionalData)) {
            foreach ($additionalData as $key => $value) {
                if ($key !== 'statusHistory' && $key !== '_id') {
                    $updateData[$key] = $value;
                }
            }
        }
        
        // Update the transaction
        $result = $this->collection->updateOne(
            ['txHash' => $txHash],
            [
                '$set' => $updateData,
                '$push' => ['statusHistory' => $statusHistoryEntry]
            ]
        );
        
        if ($result['success']) {
            // If transaction is confirmed or failed, update the source record
            if (in_array($status, ['confirmed', 'failed']) && !empty($transaction['sourceId']) && !empty($transaction['sourceType'])) {
                $this->updateSourceRecord($transaction['sourceId'], $transaction['sourceType'], $status);
            }
            
            return [
                'success' => true,
                'message' => 'Transaction status updated successfully',
                'transactionId' => $transaction['_id'],
                'previousStatus' => $transaction['status'],
                'newStatus' => $status
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to update transaction status: ' . ($result['error'] ?? 'Unknown error')
            ];
        }
    }
    
    /**
     * Update the source record (donation, milestone, etc.) with the transaction status
     * 
     * @param string $sourceId Source record ID
     * @param string $sourceType Type of source record
     * @param string $status Transaction status
     * @return array Result of the operation
     */
    private function updateSourceRecord($sourceId, $sourceType, $status) {
        try {
            // Map blockchain status to source record status
            $sourceStatus = 'pending';
            if ($status === 'confirmed') {
                $sourceStatus = 'completed';
            } elseif ($status === 'failed') {
                $sourceStatus = 'failed';
            }
            
            // Determine collection based on source type
            $collectionName = '';
            switch ($sourceType) {
                case 'donation':
                    $collectionName = 'donations';
                    break;
                case 'milestone':
                    $collectionName = 'transactions'; // Assuming milestone payments are in transactions collection
                    break;
                case 'escrow':
                    $collectionName = 'escrows';
                    break;
                case 'withdrawal':
                    $collectionName = 'withdrawals';
                    break;
                default:
                    return ['success' => false, 'error' => 'Unknown source type'];
            }
            
            // Update the source record
            $sourceCollection = $this->db->getCollection($collectionName);
            $result = $sourceCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($sourceId)],
                [
                    '$set' => [
                        'status' => $sourceStatus,
                        'transaction.status' => $sourceStatus,
                        'updated' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            return [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Source record updated successfully' : 'Failed to update source record'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error updating source record: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check a transaction's status on the blockchain and update the record
     * 
     * @param string $txHash Transaction hash
     * @return array Result of the operation
     */
    public function checkTransactionStatus($txHash) {
        try {
            // Get current transaction record
            $transaction = $this->collection->findOne(['txHash' => $txHash]);
            if (!$transaction) {
                return ['success' => false, 'error' => 'Transaction not found'];
            }
            
            // Update lastChecked timestamp
            $now = new MongoDB\BSON\UTCDateTime();
            $this->collection->updateOne(
                ['txHash' => $txHash],
                ['$set' => ['lastChecked' => $now]]
            );
            
            // Query Stellar Horizon API
            $response = @file_get_contents("{$this->horizonUrl}/transactions/{$txHash}");
            
            if (!$response) {
                // Transaction not found on blockchain yet
                if ($transaction['status'] === 'pending') {
                    // If it's been pending for too long, mark as expired
                    $createdAt = $transaction['createdAt']->toDateTime();
                    $now = new DateTime();
                    $diff = $now->getTimestamp() - $createdAt->getTimestamp();
                    
                    // If pending for more than 1 hour, mark as expired
                    if ($diff > 3600) {
                        return $this->updateTransactionStatus($txHash, 'expired', 'Transaction expired after 1 hour');
                    }
                }
                
                return [
                    'success' => true,
                    'message' => 'Transaction not found on blockchain yet',
                    'status' => $transaction['status']
                ];
            }
            
            // Parse response
            $stellarTx = json_decode($response, true);
            
            // Extract relevant details
            $stellarDetails = [
                'ledger' => (int)$stellarTx['ledger'],
                'sourceAccount' => $stellarTx['source_account'],
                'fee' => (int)$stellarTx['fee_charged'],
                'memo' => $stellarTx['memo'],
                'memoType' => $stellarTx['memo_type'],
                'successful' => (bool)$stellarTx['successful'],
                'operationCount' => count($stellarTx['operations'] ?? [])
            ];
            
            // Get operations to find destination account
            $operationsResponse = @file_get_contents("{$this->horizonUrl}/transactions/{$txHash}/operations");
            if ($operationsResponse) {
                $operations = json_decode($operationsResponse, true);
                if (isset($operations['_embedded']['records'][0])) {
                    $firstOp = $operations['_embedded']['records'][0];
                    if (isset($firstOp['type']) && $firstOp['type'] === 'payment') {
                        $stellarDetails['destinationAccount'] = $firstOp['to'];
                    }
                }
            }
            
            // Determine new status based on blockchain data
            $newStatus = $transaction['status'];
            $statusDetails = '';
            
            if ($stellarTx['successful']) {
                $newStatus = 'confirmed';
                $statusDetails = 'Transaction confirmed on blockchain in ledger ' . $stellarTx['ledger'];
            } else {
                $newStatus = 'failed';
                $statusDetails = 'Transaction failed on blockchain';
            }
            
            // Update transaction with blockchain details and new status
            return $this->updateTransactionStatus($txHash, $newStatus, $statusDetails, [
                'stellarDetails' => $stellarDetails,
                'confirmations' => 1 // Stellar transactions are final after 1 confirmation
            ]);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error checking transaction status: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get a transaction by hash
     * 
     * @param string $txHash Transaction hash
     * @return array|null The transaction document or null if not found
     */
    public function getTransaction($txHash) {
        return $this->collection->findOne(['txHash' => $txHash]);
    }
    
    /**
     * Get a transaction by ID
     * 
     * @param string $id Transaction ID
     * @return array|null The transaction document or null if not found
     */
    public function getTransactionById($id) {
        return $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    }
    
    /**
     * Get transactions by status
     * 
     * @param string $status Status to filter by
     * @param array $options Query options (pagination, sorting, etc.)
     * @return array List of transaction documents
     */
    public function getTransactionsByStatus($status, $options = []) {
        // Set default options
        $defaultOptions = [
            'limit' => 50,
            'page' => 1,
            'sort' => ['createdAt' => -1]
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        return $this->collection->find(['status' => $status], $options);
    }
    
    /**
     * Get transactions for a user
     * 
     * @param string $userId User ID
     * @param array $options Query options (pagination, sorting, etc.)
     * @return array List of transaction documents
     */
    public function getUserTransactions($userId, $options = []) {
        // Set default options
        $defaultOptions = [
            'limit' => 50,
            'page' => 1,
            'sort' => ['createdAt' => -1]
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        return $this->collection->find(['userId' => $userId], $options);
    }
    
    /**
     * Get transactions for a campaign
     * 
     * @param string $campaignId Campaign ID
     * @param array $options Query options (pagination, sorting, etc.)
     * @return array List of transaction documents
     */
    public function getCampaignTransactions($campaignId, $options = []) {
        // Set default options
        $defaultOptions = [
            'limit' => 50,
            'page' => 1,
            'sort' => ['createdAt' => -1]
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        return $this->collection->find(['campaignId' => $campaignId], $options);
    }
    
    /**
     * Get pending transactions that need to be checked
     * 
     * @param int $maxAge Maximum age in seconds for transactions to check
     * @param int $limit Maximum number of transactions to return
     * @return array List of transaction documents
     */
    public function getPendingTransactions($maxAge = 3600, $limit = 50) {
        $cutoffTime = new DateTime();
        $cutoffTime->modify("-{$maxAge} seconds");
        $cutoffDateTime = new MongoDB\BSON\UTCDateTime($cutoffTime->getTimestamp() * 1000);
        
        return $this->collection->find(
            [
                'status' => ['$in' => ['pending', 'submitted']],
                'lastChecked' => ['$lt' => $cutoffDateTime]
            ],
            [
                'limit' => $limit,
                'sort' => ['lastChecked' => 1]
            ]
        );
    }
} 