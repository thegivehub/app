<?php
/**
 * Test API routing
 */
require_once __DIR__ . '/api.php';

// Simulate a request to the transaction endpoint
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['PATH_INFO'] = '/transaction/processDonation';

// Create test data
$_POST = [
    'userId' => '65ee1a1b2f3a4b5c6d7e8f01',
    'walletId' => '680c2b20f52450fc8f0ae092',
    'campaignId' => '67fef5822ef4f90d370f7332',
    'amount' => '200',
    'message' => '',
    'isAnonymous' => false,
    'recurring' => false,
    'donorId' => '65ee1a1b2f3a4b5c6d7e8f01'
];

// This will execute the API routing logic
// If successful, it should call the Transaction class processDonation method