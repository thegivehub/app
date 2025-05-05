<?php
/**
 * Test file for transaction processor
 */
require_once __DIR__ . '/lib/TransactionProcessor.php';

// Create a test transaction
$transaction = new TransactionProcessor(true); // Use testnet

// Use test data similar to the real request
$params = [
    'userId' => '65ee1a1b2f3a4b5c6d7e8f01',
    'walletId' => '680c2b20f52450fc8f0ae092',
    'campaignId' => '67fef5822ef4f90d370f7332',
    'amount' => '200',
    'message' => '',
    'isAnonymous' => false,
    'recurring' => false,
    'donorId' => '65ee1a1b2f3a4b5c6d7e8f01'
];

// Try processing the transaction
$result = $transaction->processDonation($params);

// Output the result
echo "Transaction Result:\n";
echo json_encode($result, JSON_PRETTY_PRINT);
echo "\n";

// If there was an error, show the error message
if (!$result['success']) {
    echo "\nError Message: " . $transaction->getLastError() . "\n";
}