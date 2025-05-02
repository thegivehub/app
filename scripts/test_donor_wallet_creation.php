<?php
/**
 * Test Donor Wallet Creation Script
 * 
 * This script performs these tasks:
 * 1. Lists the first 10 donors in the database
 * 2. Checks if each donor has a linked user account
 * 3. For the first valid donor (with a userId), creates a test wallet
 * 4. Funds the wallet on the Stellar testnet
 * 
 * This is useful for testing the wallet retrieval functionality in transaction-demo.html
 * 
 * Run this script from the command line:
 * php scripts/test_donor_wallet_creation.php [--donor-id=123456789]
 */

// Load required files
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/Wallets.php';

// Parse command line options
$donorId = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--donor-id=') === 0) {
        $donorId = substr($arg, strlen('--donor-id='));
    }
}

// Initialize database connection
$db = new Database();
$donorsCollection = $db->getCollection('donors');
$usersCollection = $db->getCollection('users');
$walletsService = new Wallets();

echo "=== Donor Wallet Creation Test ===\n\n";

// Get donors
if ($donorId) {
    // Use specified donor ID
    try {
        $donor = $donorsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($donorId)]);
        if (!$donor) {
            echo "Donor with ID $donorId not found.\n";
            exit(1);
        }
        $donors = [$donor];
        echo "Using specified donor: " . $donorId . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    // Get the first 10 donors
    $donors = $donorsCollection->find([], ['limit' => 10]);
    echo "Using first 10 donors in the database.\n";
}

// Check donors
echo "\nChecking donors...\n";
echo str_repeat('-', 80) . "\n";
printf("%-24s %-24s %-30s %s\n", "Donor ID", "User ID", "Name", "Email");
echo str_repeat('-', 80) . "\n";

$validDonor = null;
$validUserId = null;

foreach ($donors as $donor) {
    $donorId = (string)$donor['_id'];
    
    // Extract donor name and email
    $name = $donor['name'] ?? ($donor['donor']['name'] ?? "Unknown");
    $email = $donor['email'] ?? ($donor['donor']['email'] ?? "Unknown");
    
    // Check if donor has a userId
    if (isset($donor['userId'])) {
        $userId = (string)$donor['userId'];
        
        // Check if the user exists
        $user = $usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
        if ($user) {
            // This is a valid donor with a linked user account
            if (!$validDonor) {
                $validDonor = $donor;
                $validUserId = $userId;
            }
            printf("%-24s %-24s %-30s %s\n", $donorId, $userId, $name, $email);
        } else {
            printf("%-24s %-24s %-30s %s [USER NOT FOUND]\n", $donorId, $userId, $name, $email);
        }
    } else {
        printf("%-24s %-24s %-30s %s [NO USER ID]\n", $donorId, "N/A", $name, $email);
    }
}

echo str_repeat('-', 80) . "\n\n";

// If no valid donors with user accounts were found
if (!$validDonor) {
    echo "No donors with valid user accounts found.\n";
    echo "Please run the link_donors_to_users.php script first.\n";
    exit(1);
}

// Get wallet for the valid donor
echo "Checking existing wallets for donor $validUserId...\n";
$wallets = $walletsService->getUserWallets(['userId' => $validUserId]);

if ($wallets['success'] && !empty($wallets['wallets'])) {
    echo "Found " . count($wallets['wallets']) . " existing wallets:\n";
    foreach ($wallets['wallets'] as $index => $wallet) {
        echo ($index + 1) . ". " . $wallet['label'] . " (" . $wallet['publicKey'] . ")\n";
        echo "   Balance: " . $wallet['balance'] . " XLM\n";
    }
} else {
    echo "No existing wallets found. Creating a new wallet...\n";
    
    // Create wallet
    $walletResult = $walletsService->createWallet(['userId' => $validUserId]);
    
    if ($walletResult['success']) {
        $wallet = $walletResult['wallet'];
        echo "Created new wallet with ID: " . $wallet['id'] . "\n";
        echo "Public Key: " . $wallet['publicKey'] . "\n";
        
        // Fund the wallet
        echo "\nFunding the wallet on testnet...\n";
        $fundResult = $walletsService->fundTestnetAccount(['publicKey' => $wallet['publicKey']]);
        
        if ($fundResult['success']) {
            echo "Wallet funded successfully!\n";
            echo "Balance: " . $fundResult['account']['balances'][0]['balance'] . " XLM\n";
        } else {
            echo "Failed to fund wallet: " . ($fundResult['error'] ?? "Unknown error") . "\n";
        }
    } else {
        echo "Failed to create wallet: " . ($walletResult['error'] ?? "Unknown error") . "\n";
    }
}

echo "\nYou can now test the transaction-demo.html page with donor ID: " . (string)$validDonor['_id'] . "\n";
echo "User ID (for reference): $validUserId\n";
?>