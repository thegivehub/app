<?php
/**
 * Test the Transaction class directly by setting up test data 
 */
require_once __DIR__ . '/lib/Transaction.php';

// Create an extended test class for testing
class TestTransaction extends Transaction {
    // Override getRequestData to return our test data
    protected function getRequestData() {
        // Return our test data
        return [
            'userId' => '65ee1a1b2f3a4b5c6d7e8f01',
            'walletId' => '680c2b20f52450fc8f0ae092',
            'campaignId' => '67fef5822ef4f90d370f7332',
            'amount' => '200',
            'message' => '',
            'isAnonymous' => false,
            'recurring' => false,
            'donorId' => '65ee1a1b2f3a4b5c6d7e8f01'
        ];
    }
}

// Create instance of our test class
$transaction = new TestTransaction();

// Call processDonation
$result = $transaction->processDonation();

// Output result
echo "Transaction result:\n";
echo json_encode($result, JSON_PRETTY_PRINT);
echo "\n";