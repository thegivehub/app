<?php
/**
 * Generate Test Donations Script
 *
 * This script generates test donations from existing donor wallets to campaigns
 * in the givehub MongoDB database. It uses the Donate class to process the donations
 * and records transactions in the blockchain_transactions collection.
 */

$envVars = parse_ini_file(__DIR__ . '/../.env');

foreach ($envVars as $key=>$val) {
    putenv("{$key}={$val}");
}

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/Donate.php';
require_once __DIR__ . '/../lib/Wallets.php';
require_once __DIR__ . '/../lib/Campaign.php';

// Configuration
$NUM_DONATIONS = 10; // Number of donations to generate
$MIN_AMOUNT = 10;    // Minimum donation amount
$MAX_AMOUNT = 100;   // Maximum donation amount
$TEST_MODE = true;   // Use Stellar testnet

// Initialize database connection
$db = new Database("givehub");
$usersCollection = $db->getCollection('users');
$campaignsCollection = $db->getCollection('campaigns');
$walletsCollection = $db->getCollection('wallets');

// Initialize classes
$donate = new Donate($TEST_MODE);
$walletsManager = new Wallets();
$campaignManager = new Campaign();

// Helper function to log messages
function logMessage($message) {
    echo date('[Y-m-d H:i:s]') . " $message\n";
}

// Helper function to generate a random amount
function getRandomAmount($min, $max) {
    return round(mt_rand($min * 100, $max * 100) / 100, 2);
}

// Get active campaigns
logMessage("Fetching active campaigns...");
$campaigns = $campaignsCollection->find(['status' => 'active'], ['limit' => 10]);

if (empty($campaigns)) {
    logMessage("No active campaigns found. Looking for campaigns with any status...");
    $campaigns = $campaignsCollection->find([], ['limit' => 10]);

    if (empty($campaigns)) {
        logMessage("ERROR: No campaigns found in the database. Please create campaigns first.");
        exit(1);
    }
}

logMessage("Found " . count($campaigns) . " campaigns");

// Get donor users with wallets
logMessage("Fetching donor users...");
$users = $usersCollection->find(
    [
        'type' => ['$in' => ['donor', 'user']], // Include both donor and regular user types
        'status' => 'active'
    ],
    ['limit' => 20]
);

if (empty($users)) {
    logMessage("No active donor users found. Looking for any users...");
    $users = $usersCollection->find([], ['limit' => 20]);

    if (empty($users)) {
        logMessage("ERROR: No users found in the database. Please create users first.");
        exit(1);
    }
}

logMessage("Found " . count($users) . " potential donor users");

// Find users with wallets
$donorsWithWallets = [];
foreach ($users as $user) {
    $userId = (string)$user['_id'];
    $walletResult = $walletsManager->getUserWallet(['userId' => $userId]);

    if ($walletResult['success'] && !empty($walletResult['wallet'])) {
        $donorsWithWallets[] = [
            'user' => $user,
            'wallet' => $walletResult['wallet']
        ];
    }
}

if (empty($donorsWithWallets)) {
    logMessage("ERROR: No users with wallets found. Please create wallets for users first.");
    exit(1);
}

logMessage("Found " . count($donorsWithWallets) . " donors with wallets");

// Check if the wallet system is properly configured
logMessage("Checking cryptocurrency wallet configuration...");
$cryptos = $donate->getSupportedCryptos();
print_r($cryptos);
// Check if there are any currencies configured
if (isset($cryptos['error'])) {
    logMessage("ERROR: Wallet configuration issue: " . $cryptos['error']);
    logMessage("Please set up your .env file with the cryptocurrency wallet settings");
    exit(1);
}

// Check if XLM is properly configured
$hasXLM = false;
if (isset($cryptos['XLM'])) {
    $hasXLM = true;
    logMessage("Found Stellar XLM wallet configuration");
    logMessage("Network: " . $cryptos['XLM']['network'] . 
               " (Testnet: " . ($cryptos['XLM']['isTestnet'] ? "Yes" : "No") . ")");
} else {
    logMessage("WARNING: Stellar XLM wallet not configured. Set STELLAR_PUBLIC_KEY in your .env file");
}

// Process donations
$successCount = 0;
$failureCount = 0;

for ($i = 0; $i < $NUM_DONATIONS; $i++) {
    // Get random donor and campaign
    $donorIdx = array_rand($donorsWithWallets);
    $campaignIdx = array_rand($campaigns);

    $donor = $donorsWithWallets[$donorIdx];
    $campaign = $campaigns[$campaignIdx];

    $userId = (string)$donor['user']['_id'];
    $campaignId = (string)$campaign['_id'];
    $amount = getRandomAmount($MIN_AMOUNT, $MAX_AMOUNT);

    // Determine donation currency - use XLM if available
    $cryptoType = $hasXLM ? 'XLM' : array_keys($cryptos)[0] ?? 'XLM';
    
    logMessage("Processing donation #{$i}: User ID: $userId to Campaign: '{$campaign['title']}' - Amount: $amount $cryptoType");

    try {
        // Prepare donation data
        $donationData = [
            'userId' => $userId,
            'campaignId' => $campaignId,
            'amount' => $amount,
            'cryptoType' => $cryptoType,
            'isAnonymous' => mt_rand(0, 10) > 8,  // Occasionally make anonymous
            'donorInfo' => [
                'name' => isset($donor['user']['personalInfo'])
                    ? ($donor['user']['personalInfo']['firstName'] . ' ' . $donor['user']['personalInfo']['lastName'])
                    : 'Anonymous Donor',
                'email' => $donor['user']['email'] ?? 'donor@example.com'
            ],
            'message' => 'Test donation generated on ' . date('Y-m-d H:i:s'),
            'campaignData' => [
                'title' => $campaign['title'] ?? 'Unknown Campaign',
                'id' => $campaignId
            ]
        ];

        // Add a flag to indicate this is from the test script
        $donationData['isTestDonation'] = true;
        
        // Process donation using the correct method
        $result = $donate->processCryptoDonation($donationData);

        if (isset($result['success']) && $result['success']) {
            $txRef = $result['reference'] ?? $result['transactionId'] ?? 'N/A';
            $wallet = $result['walletAddress'] ?? 'N/A';
            logMessage("SUCCESS: Donation processed. Transaction ID: $txRef");
            logMessage("         Wallet Address: $wallet (Network: " . ($result['network'] ?? 'unknown') . ")");
            
            // Create an entry in the donations collection
            $donationRecord = [
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
                'amount' => [
                    'value' => (float)$amount,
                    'currency' => $cryptoType
                ],
                'transaction' => [
                    'txHash' => $result['reference'], // Using reference as txHash for test donations
                    'status' => 'completed',
                    'timestamp' => new MongoDB\BSON\UTCDateTime()
                ],
                'transactionId' => $result['transactionId'] ?? null,
                'type' => 'one-time',
                'status' => 'completed',
                'visibility' => $donationData['isAnonymous'] ? 'anonymous' : 'public',
                'message' => $donationData['message'] ?? '',
                'isTestDonation' => true,
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Insert the donation record
            try {
                $donationsCollection = $db->getCollection('donations');
                $donationResult = $donationsCollection->insertOne($donationRecord);
                
                if ($donationResult['success']) {
                    logMessage("       Donation record created in donations collection with ID: " . $donationResult['id']);
                    
                    // Update the blockchain transaction with the sourceId (donation ID)
                    if (!empty($result['transactionId'])) {
                        $blockchainCollection = $db->getCollection('blockchain_transactions');
                        $blockchainCollection->updateOne(
                            ['_id' => new MongoDB\BSON\ObjectId($result['transactionId'])],
                            [
                                '$set' => [
                                    'sourceId' => $donationResult['id'],
                                    'sourceType' => 'donation'
                                ]
                            ]
                        );
                        logMessage("       Blockchain transaction updated with donation ID");
                    }
                } else {
                    logMessage("       WARNING: Failed to create donation record: " . 
                        ($donationResult['error'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                logMessage("       ERROR creating donation record: " . $e->getMessage());
            }
            
            $successCount++;
        } else {
            logMessage("FAILURE: Could not process donation: " . ($result['error'] ?? 'Unknown error'));
            $failureCount++;
        }
    } catch (Exception $e) {
        logMessage("EXCEPTION: " . $e->getMessage());
        $failureCount++;
    }
}

// Summary
logMessage("Donation generation completed:");
logMessage("  Success: $successCount");
logMessage("  Failures: $failureCount");
logMessage("  Total attempted: $NUM_DONATIONS");

if ($failureCount > 0) {
    logMessage("Some donations failed. Check logs for details.");
    exit(1);
} else {
    logMessage("All donations processed successfully!");
    exit(0);
}
