<?php
/**
 * Test the Transaction class via the API
 */
require_once __DIR__ . '/lib/Transaction.php';

// Create instance
$transaction = new Transaction();

// Test data (simulate the request from the curl command)
$jsonData = '{
    "userId": "65ee1a1b2f3a4b5c6d7e8f01",
    "walletId": "680c2b20f52450fc8f0ae092",
    "campaignId": "67fef5822ef4f90d370f7332",
    "amount": "200",
    "message": "",
    "isAnonymous": false,
    "recurring": false,
    "donorId": "65ee1a1b2f3a4b5c6d7e8f01"
}';

// Mock the file_get_contents to return our test data
function file_get_contents($filename) {
    if ($filename === 'php://input') {
        global $jsonData;
        return $jsonData;
    }
    return \file_get_contents($filename);
}

// Set request method
$_SERVER['REQUEST_METHOD'] = 'POST';

// Call processDonation
$result = $transaction->processDonation();

// Output result
echo "Transaction API result:\n";
echo json_encode($result, JSON_PRETTY_PRINT);
echo "\n";