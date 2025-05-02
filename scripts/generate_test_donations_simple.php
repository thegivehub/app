<?php
/**
 * Generate Test Donations Script (Simplified Version)
 *
 * This script generates test donations directly in the database, bypassing some validation checks.
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/Campaign.php';
require_once __DIR__ . '/../lib/Wallets.php';

// Configuration
$NUM_DONATIONS = 10; // Number of donations to generate
$MIN_AMOUNT = 10;    // Minimum donation amount
$MAX_AMOUNT = 100;   // Maximum donation amount

// Initialize database connection
$db = new Database("givehub");
$usersCollection = $db->getCollection('users');
$campaignsCollection = $db->getCollection('campaigns');
$walletsCollection = $db->getCollection('wallets');
$donationsCollection = $db->getCollection('test_donations');
$blockchainTxCollection = $db->getCollection('test_blockchain_transactions');

// Initialize classes
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

// Process donations
$successCount = 0;
$failureCount = 0;

// Create the test Stellar wallet info
$stellarAddress = getenv("STELLAR_PUBLIC_KEY") ?: "GDOQRTZAUGJJLQCHZLYKMFCA6CQEHPBBOPYLRXCG2WSNO4LEYXLXD3OB";
$stellarNetwork = getenv("STELLAR_NETWORK") ?: "testnet";

for ($i = 0; $i < $NUM_DONATIONS; $i++) {
    // Get random donor and campaign
    $donorIdx = array_rand($donorsWithWallets);
    $campaignIdx = array_rand($campaigns);

    $donor = $donorsWithWallets[$donorIdx];
    $campaign = $campaigns[$campaignIdx];

    $userId = (string)$donor['user']['_id'];
    $campaignId = (string)$campaign['_id'];
    $amount = getRandomAmount($MIN_AMOUNT, $MAX_AMOUNT);
    $now = new MongoDB\BSON\UTCDateTime();
    $reference = "TEST_" . uniqid();
    $txHash = "TEST_TX_" . uniqid() . "_" . time();

    logMessage("Processing donation #{$i}: User ID: $userId to Campaign: '{$campaign['title']}' - Amount: $amount XLM");

    try {
        // Create the blockchain transaction record directly
        $blockchainTxData = [
            'txHash' => $txHash,
            'type' => 'payment',
            'status' => 'pending',
            'cryptoType' => 'XLM',
            'network' => $stellarNetwork,
            'amount' => [
                'value' => $amount,
                'currency' => 'XLM'
            ],
            'walletAddress' => $stellarAddress,
            'campaignId' => new MongoDB\BSON\ObjectId($campaignId), 
            'userId' => new MongoDB\BSON\ObjectId($userId),
            'sourceType' => 'donation',
            'reference' => $reference,
            'createdAt' => $now,
            'updatedAt' => $now,
            'lastChecked' => $now,
            'statusHistory' => [
                [
                    'status' => 'pending',
                    'timestamp' => $now,
                    'details' => 'Test transaction created'
                ]
            ],
            'metadata' => [
                'donorInfo' => [
                    'name' => isset($donor['user']['personalInfo'])
                        ? ($donor['user']['personalInfo']['firstName'] . ' ' . $donor['user']['personalInfo']['lastName'])
                        : 'Anonymous Donor',
                    'email' => $donor['user']['email'] ?? 'donor@example.com'
                ],
                'campaignData' => [
                    'title' => $campaign['title'] ?? 'Unknown Campaign',
                    'id' => $campaignId
                ],
                'isAnonymous' => mt_rand(0, 10) > 8,
                'message' => 'Test donation generated on ' . date('Y-m-d H:i:s'),
                'isTestDonation' => true
            ]
        ];
        
        // Insert the blockchain transaction
        $insertTxResult = $blockchainTxCollection->insertOne($blockchainTxData);
        
        if (!$insertTxResult['success']) {
            throw new Exception("Failed to insert blockchain transaction: " . ($insertTxResult['error'] ?? 'Unknown error'));
        }
        
        $blockchainTxId = $insertTxResult['id'] ?? null;
        
        // Create the donation record
        $donationData = [
            'userId' => new MongoDB\BSON\ObjectId($userId),
            'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
            'amount' => [
                'value' => new MongoDB\BSON\Decimal128((string)$amount),
                'currency' => 'XLM'
            ],
            'transaction' => [
                'txHash' => $txHash,
                'stellarAddress' => $stellarAddress,
                'status' => 'pending',
                'timestamp' => $now,
                'memo' => $reference
            ],
            'status' => 'pending',
            'type' => 'one-time',
            'visibility' => mt_rand(0, 10) > 8 ? 'anonymous' : 'public',
            'created' => $now,
            'updated' => $now,
            'metadata' => [
                'donorInfo' => [
                    'name' => isset($donor['user']['personalInfo'])
                        ? ($donor['user']['personalInfo']['firstName'] . ' ' . $donor['user']['personalInfo']['lastName'])
                        : 'Anonymous Donor',
                    'email' => $donor['user']['email'] ?? 'donor@example.com'
                ],
                'campaignData' => [
                    'title' => $campaign['title'] ?? 'Unknown Campaign'
                ],
                'isTestDonation' => true,
                'transactionId' => $blockchainTxId ? (string)$blockchainTxId : null
            ]
        ];
        
        // Insert the donation
        $insertDonationResult = $donationsCollection->insertOne($donationData);
        
        if (!$insertDonationResult['success']) {
            throw new Exception("Failed to insert donation: " . ($insertDonationResult['error'] ?? 'Unknown error'));
        }
        
        $donationId = $insertDonationResult['id'] ?? null;
        
        // Update the blockchain transaction with the donation ID
        if ($blockchainTxId && $donationId) {
            $blockchainTxCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($blockchainTxId)],
                ['$set' => ['sourceId' => (string)$donationId]]
            );
        }
        
        logMessage("SUCCESS: Donation processed. Transaction ID: {$txHash}");
        logMessage("         Wallet Address: {$stellarAddress} (Network: {$stellarNetwork})");
        $successCount++;
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