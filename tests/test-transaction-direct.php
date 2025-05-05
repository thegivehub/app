<?php
/**
 * Test the TransactionProcessor class directly
 */
require_once __DIR__ . '/lib/TransactionProcessor.php';

// Create a new TransactionProcessor instance (use testnet for testing)
$transactionProcessor = new TransactionProcessor(true);

// Test data
$data = [
    'donorId' => '65ee1a1b2f3a4b5c6d7e8f01',
    'walletId' => '680c2b20f52450fc8f0ae092',
    'campaignId' => '67fef5822ef4f90d370f7332',
    'amount' => '200',
    'message' => '',
    'isAnonymous' => false,
    'recurring' => false
];

// Call processDonation method directly
$result = $transactionProcessor->processDonation($data);

// Output result
echo "Transaction result: \n";
echo json_encode($result, JSON_PRETTY_PRINT);
echo "\n";