<?php
/**
 * Script to verify campaign wallet associations
 * This script checks that campaigns have wallets, wallets have campaigns,
 * and that Stellar wallets are funded on the testnet
 * 
 * Usage:
 * php verify_campaign_wallets.php [limit] [skip]
 * 
 * limit: Maximum number of campaigns to process (default: 10)
 * skip: Number of campaigns to skip (default: 0)
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/soneso/stellar-php-sdk/Soneso/StellarSDK/StellarSDK.php';

use Soneso\StellarSDK\StellarSDK;
use Soneso\StellarSDK\Util\FriendBot;

// Parse command line arguments
$limit = isset($argv[1]) && is_numeric($argv[1]) ? (int)$argv[1] : 10;
$skip = isset($argv[2]) && is_numeric($argv[2]) ? (int)$argv[2] : 0;

// Helper function to log messages
function logMessage($message) {
    echo date('[Y-m-d H:i:s]') . " $message\n";
}

/**
 * Check if a Stellar account is funded
 * @param string $publicKey The Stellar account public key to check
 * @return bool|array True if funded, array with details if not funded
 */
function checkStellarAccountFunding($publicKey) {
    $sdk = StellarSDK::getTestNetInstance();
    
    try {
        // Try to get account info
        $account = $sdk->requestAccount($publicKey);
        // If we get here, the account exists
        return true;
    } catch (\Exception $e) {
        // If account is not found or not funded
        if (strpos($e->getMessage(), 'Resource Missing') !== false) {
            return [
                'funded' => false,
                'error' => 'Account not found or not activated on the Stellar testnet'
            ];
        }
        
        // Any other error
        return [
            'funded' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Fund a Stellar account using Friendbot
 * @param string $publicKey The Stellar account public key to fund
 * @return bool True if funding was successful, false otherwise
 */
function fundStellarAccountWithFriendbot($publicKey) {
    try {
        $funded = FriendBot::fundTestAccount($publicKey);
        return $funded;
    } catch (\Exception $e) {
        logMessage("Error funding account with Friendbot: " . $e->getMessage());
        return false;
    }
}

try {
    $db = new Database();
    $campaignsCollection = $db->getCollection('campaigns');
    $walletsCollection = $db->getCollection('wallets');
    
    logMessage("Starting campaign wallet verification (limit: $limit, skip: $skip)...");
    
    // 1. Check campaigns with walletId with pagination
    $options = [
        'limit' => $limit,
        'skip' => $skip
    ];
    
    $campaignsWithWallets = $campaignsCollection->find(
        [
            'walletId' => ['$exists' => true, '$ne' => null, '$ne' => '']
        ],
        $options
    );
    
    // Get total count for reporting
    $totalCount = $campaignsCollection->countDocuments([
        'walletId' => ['$exists' => true, '$ne' => null, '$ne' => '']
    ]);
    
    $totalCampaignsWithWallets = 0;
    $validWallets = 0;
    $invalidWallets = 0;
    $unfundedWallets = 0;
    $fundedWithFriendbot = 0;
    
    foreach ($campaignsWithWallets as $campaign) {
        $totalCampaignsWithWallets++;
        $campaignId = (string)$campaign['_id'];
        $walletId = $campaign['walletId'];
        $campaignTitle = $campaign['title'] ?? "Untitled Campaign";
        
        // Check if wallet exists
        $wallet = $walletsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($walletId)]);
        
        if ($wallet) {
            // Check if wallet is properly linked to campaign
            $walletCampaignId = isset($wallet['campaignId']) ? (string)$wallet['campaignId'] : null;
            
            if ($walletCampaignId === $campaignId) {
                $validWallets++;
                logMessage("Campaign '$campaignTitle' has a valid wallet association (Wallet ID: $walletId)");
                
                // Check for Stellar wallet (XLM) and if it has public key
                if (isset($wallet['currency']) && $wallet['currency'] === 'XLM' && isset($wallet['publicKey'])) {
                    $publicKey = $wallet['publicKey'];
                    
                    logMessage("  Checking Stellar account funding for wallet: $publicKey");
                    $fundingStatus = checkStellarAccountFunding($publicKey);
                    
                    if ($fundingStatus === true) {
                        logMessage("  ✓ Stellar account is funded");
                    } else {
                        $unfundedWallets++;
                        logMessage("  ✗ Stellar account is not funded: " . $fundingStatus['error']);
                        
                        // Try to fund with Friendbot
                        logMessage("  Attempting to fund account with Friendbot...");
                        $fundingResult = fundStellarAccountWithFriendbot($publicKey);
                        
                        if ($fundingResult) {
                            $fundedWithFriendbot++;
                            logMessage("  ✓ Successfully funded account with Friendbot");
                        } else {
                            logMessage("  ✗ Failed to fund account with Friendbot");
                        }
                    }
                }
            } else {
                $invalidWallets++;
                logMessage("ERROR: Campaign '$campaignTitle' has a wallet (ID: $walletId) but the wallet is not properly linked back to the campaign");
            }
        } else {
            $invalidWallets++;
            logMessage("ERROR: Campaign '$campaignTitle' has a walletId ($walletId) but the wallet does not exist");
        }
    }
    
    // 2. Check for wallet records with campaignId (we're not paginating this part)
    // This is only to provide additional information about wallet-campaign relationships
    $walletsWithCampaigns = $walletsCollection->find([
        'campaignId' => ['$exists' => true, '$ne' => null]
    ]);
    
    $totalWalletsWithCampaignId = $walletsCollection->countDocuments([
        'campaignId' => ['$exists' => true, '$ne' => null]
    ]);
    
    $walletsWithValidCampaigns = 0;
    $walletsWithInvalidCampaigns = 0;
    
    logMessage("\nVerification Summary:");
    logMessage("Processed $totalCampaignsWithWallets campaigns out of $totalCount total campaigns with wallet IDs");
    logMessage("Processing batch: $skip to " . ($skip + $limit) . " (limit: $limit)");
    logMessage("  - Valid wallet associations: $validWallets");
    logMessage("  - Invalid wallet associations: $invalidWallets");
    logMessage("Total wallets with campaign IDs: $totalWalletsWithCampaignId");
    
    logMessage("\nStellar Funding Status:");
    logMessage("  - Unfunded wallets detected: $unfundedWallets");
    logMessage("  - Wallets funded with Friendbot: $fundedWithFriendbot");
    
    if ($validWallets === $totalCampaignsWithWallets && $validWallets > 0 && $unfundedWallets === 0) {
        logMessage("\nSUCCESS: All processed campaign-wallet associations are valid and all Stellar accounts are funded!");
    } else if ($validWallets === $totalCampaignsWithWallets && $validWallets > 0 && $unfundedWallets > 0 && $fundedWithFriendbot === $unfundedWallets) {
        logMessage("\nSUCCESS: All processed campaign-wallet associations are valid and all previously unfunded Stellar accounts have been funded!");
    } else if ($validWallets !== $totalCampaignsWithWallets) {
        logMessage("\nWARNING: Some campaign-wallet associations have issues. Check the logs above for details.");
    } else if ($unfundedWallets > 0 && $fundedWithFriendbot < $unfundedWallets) {
        logMessage("\nWARNING: Some Stellar accounts could not be funded. Check the logs above for details.");
    }
    
    // Next steps
    if ($skip + $limit < $totalCount) {
        $nextSkip = $skip + $limit;
        logMessage("\nThere are more campaigns to process. Run the script with:");
        logMessage("php " . basename(__FILE__) . " $limit $nextSkip");
    } else {
        logMessage("\nAll campaigns have been processed.");
    }
    
} catch (Exception $e) {
    logMessage("Error during verification: " . $e->getMessage());
    exit(1);
}

exit(0);