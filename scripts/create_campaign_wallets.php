<?php
/**
 * Script to ensure all campaigns have associated Stellar wallets
 * This script will scan all campaigns, and for any without a walletId:
 * 1. Create a new wallet
 * 2. Associate it with the campaign
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/Collection.php';
require_once __DIR__ . '/../lib/Wallet.php';

// Main script execution
echo "Starting campaign wallet creation process...\n";

// Use Soneso Stellar SDK
use Soneso\StellarSDK\Crypto\KeyPair;

// Create a custom direct wallet creation function to bypass complex validation
function createCampaignWalletDirectly($campaignId, $campaignTitle) {
    try {
        // Generate new Stellar keypair
        $keypair = KeyPair::random();
        $publicKey = $keypair->getAccountId();
        $secretKey = $keypair->getSecretSeed();
        
        // Create wallet record
        $db = new Database();
        $walletsCollection = $db->getCollection('wallets');
        
        $walletData = [
            'publicKey' => $publicKey,
            'secretKey' => $secretKey,
            'label' => "Campaign Wallet: " . $campaignTitle,
            'campaignId' => new MongoDB\BSON\ObjectId($campaignId),
            'type' => 'campaign',
            'currency' => 'XLM',
            'createdAt' => new MongoDB\BSON\UTCDateTime(),
            'updatedAt' => new MongoDB\BSON\UTCDateTime(),
            'status' => 'active'
        ];
        
        try {
            $result = $walletsCollection->insertOne($walletData);
            // Debug output
            echo "  Insert Result Type: " . gettype($result) . "\n";
            if (is_object($result)) {
                echo "  Result Class: " . get_class($result) . "\n";
            } elseif (is_array($result)) {
                echo "  Result Array: " . json_encode($result) . "\n";
            }
        } catch (\Exception $e) {
            echo "  MongoDB Error: " . $e->getMessage() . "\n";
            return [
                'success' => false,
                'error' => "MongoDB Error: " . $e->getMessage()
            ];
        }
        
        // Check result type and proceed accordingly
        if (is_object($result) && method_exists($result, 'getInsertedCount') && $result->getInsertedCount() > 0) {
            $walletId = (string)$result->getInsertedId();
        } elseif (is_array($result) && isset($result['insertedId'])) {
            $walletId = (string)$result['insertedId'];
        } elseif (is_array($result) && isset($result['id'])) {
            $walletId = $result['id'];
        } else {
            return [
                'success' => false, 
                'error' => 'Failed to get inserted wallet ID'
            ];
        }
            
        // Update campaign with the new walletId
        $campaignsCollection = $db->getCollection('campaigns');
        $updateResult = $campaignsCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($campaignId)],
            [
                '$set' => [
                    'walletId' => $walletId,
                    'stellarAddress' => $publicKey,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );
        
        return [
            'success' => true,
            'walletId' => $walletId,
            'publicKey' => $publicKey,
            'campaignUpdateSuccess' => is_object($updateResult) && method_exists($updateResult, 'getModifiedCount') 
                ? ($updateResult->getModifiedCount() > 0) 
                : (is_array($updateResult) && isset($updateResult['modified']) ? ($updateResult['modified'] > 0) : false)
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

$db = new Database();
$campaignsCollection = $db->getCollection('campaigns');

// Get all campaigns that don't have a walletId
$campaigns = $campaignsCollection->find([
    '$or' => [
        ['walletId' => ['$exists' => false]],
        ['walletId' => null],
        ['walletId' => '']
    ]
]);

$totalCampaigns = 0;
$createdWallets = 0;
$failedWallets = 0;

foreach ($campaigns as $campaign) {
    $totalCampaigns++;
    $campaignId = (string)$campaign['_id'];
    $campaignTitle = $campaign['title'] ?? "Untitled Campaign";
    
    echo "Processing campaign: $campaignTitle (ID: $campaignId)\n";
    
    try {
        // Use our direct wallet creation function instead of the Wallet class
        $result = createCampaignWalletDirectly($campaignId, $campaignTitle);
        
        if (is_array($result) && isset($result['success']) && $result['success']) {
            $createdWallets++;
            echo "  Created wallet for campaign '$campaignTitle' (ID: $campaignId)\n";
            echo "  Wallet ID: " . $result['walletId'] . "\n";
            echo "  Public Key: " . $result['publicKey'] . "\n";
            echo "  Campaign Update Success: " . ($result['campaignUpdateSuccess'] ? 'Yes' : 'No') . "\n\n";
        } else {
            $failedWallets++;
            $errorMsg = (is_array($result) && isset($result['error'])) ? $result['error'] : "Unknown error";
            echo "  Failed to create wallet for campaign: " . $errorMsg . "\n\n";
        }
    } catch (\Exception $e) {
        $failedWallets++;
        echo "  Exception while creating wallet for campaign: " . $e->getMessage() . "\n\n";
    }
}

echo "\nWallet creation summary:\n";
echo "Total campaigns processed: $totalCampaigns\n";
echo "Wallets created/linked: $createdWallets\n";
echo "Failed wallet creations: $failedWallets\n";

if ($totalCampaigns == 0) {
    echo "\nAll campaigns already have associated wallets!\n";
}

echo "\nDone.\n";