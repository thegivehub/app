<?php
/**
 * Wallet Cleanup and Balance Update Script
 * 
 * This script:
 * 1. Removes wallets that don't have a proper user or campaign association
 * 2. Updates wallet balances from the Stellar testnet for valid wallets
 * 
 * Usage: php cleanup_and_update_wallets.php
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Use Soneso Stellar SDK
use Soneso\StellarSDK\Server;
use Soneso\StellarSDK\StellarSDK;

class WalletCleanupTool {
    private $db;
    private $walletsCollection;
    private $usersCollection;
    private $campaignsCollection;
    private $stellarServer;
    private $isTestnet = true;
    private $dryRun = false;
    private $verboseOutput = true;

    public function __construct($dryRun = false) {
        $this->db = new Database();
        $this->walletsCollection = $this->db->getCollection('wallets');
        $this->usersCollection = $this->db->getCollection('users');
        $this->campaignsCollection = $this->db->getCollection('campaigns');
        
        // Initialize Stellar server (testnet by default)
        $horizonUrl = $this->isTestnet 
            ? 'https://horizon-testnet.stellar.org' 
            : 'https://horizon.stellar.org';
        $sdk = new StellarSDK($horizonUrl);
        $this->stellarServer = $sdk;

        $this->dryRun = $dryRun;
        $this->log("Wallet Cleanup Tool Initialized" . ($this->dryRun ? " (DRY RUN MODE)" : ""));
    }

    /**
     * Run the cleanup and update process
     */
    public function run() {
        $this->log("Starting wallet cleanup and update process...");
        
        // Step 1: Remove orphaned wallets
        $this->removeOrphanedWallets();

        // Step 2: Update balances for remaining wallets
        $this->updateWalletBalances();

        $this->log("Wallet cleanup and update process completed.");
    }

    /**
     * Remove wallets that don't have valid user or campaign associations
     */
    private function removeOrphanedWallets() {
        $this->log("Checking for orphaned wallets...");
        
        $wallets = $this->walletsCollection->find([]);
        $orphanedWallets = [];
        $totalWallets = count($wallets);
        
        if ($totalWallets === 0) {
            $this->log("No wallets found in database.");
            return;
        }
        
        $this->log("Found {$totalWallets} wallets in database.");
        
        foreach ($wallets as $wallet) {
            $isOrphaned = false;
            $reason = [];
            
            // Check if wallet has a userId
            if (isset($wallet['userId'])) {
                // Verify if the user exists
                $user = $this->usersCollection->findOne(['_id' => $wallet['userId']]);
                if (!$user) {
                    $isOrphaned = true;
                    $reason[] = "User not found (ID: " . (string)$wallet['userId'] . ")";
                }
            } elseif (isset($wallet['campaignId'])) {
                // Verify if the campaign exists
                $campaign = $this->campaignsCollection->findOne(['_id' => $wallet['campaignId']]);
                if (!$campaign) {
                    $isOrphaned = true;
                    $reason[] = "Campaign not found (ID: " . (string)$wallet['campaignId'] . ")";
                }
            } else {
                $isOrphaned = true;
                $reason[] = "No userId or campaignId found";
            }
            
            if ($isOrphaned) {
                $orphanedWallets[] = [
                    'id' => (string)$wallet['_id'],
                    'publicKey' => $wallet['publicKey'] ?? 'unknown',
                    'reason' => implode(", ", $reason)
                ];
            }
        }
        
        $orphanCount = count($orphanedWallets);
        if ($orphanCount > 0) {
            $this->log("Found {$orphanCount} orphaned wallets to remove:");
            foreach ($orphanedWallets as $wallet) {
                $this->log("  - Wallet ID: {$wallet['id']}, Public Key: {$wallet['publicKey']}, Reason: {$wallet['reason']}");
                
                if (!$this->dryRun) {
                    $result = $this->walletsCollection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($wallet['id'])]);
                    $this->log("    - " . ($result->getDeletedCount() > 0 ? "Removed" : "Failed to remove"));
                }
            }
        } else {
            $this->log("No orphaned wallets found.");
        }
    }

    /**
     * Update balances for all valid wallets from the Stellar network
     */
    private function updateWalletBalances() {
        $this->log("Updating wallet balances from Stellar network...");
        
        $wallets = $this->walletsCollection->find([]);
        $totalWallets = count($wallets);
        $updatedCount = 0;
        $errorCount = 0;
        
        if ($totalWallets === 0) {
            $this->log("No wallets found in database.");
            return;
        }
        
        $this->log("Found {$totalWallets} wallets to update.");
        
        foreach ($wallets as $wallet) {
            try {
                if (!isset($wallet['publicKey'])) {
                    $this->log("  - Wallet ID: {$wallet['_id']} - Missing public key, skipping");
                    continue;
                }
                
                $publicKey = $wallet['publicKey'];
                $this->log("  - Updating wallet: {$publicKey}");
                
                // Get account details from Stellar network
                try {
                    $accountResponse = $this->stellarServer->accounts()->account($publicKey);
                    
                    // Process balances from account data
                    $balance = '0';
                    $balances = $accountResponse->getBalances();
                    if (!empty($balances)) {
                        foreach ($balances as $stellarBalance) {
                            if ($stellarBalance->getAssetType() === "native") {
                                $balance = $stellarBalance->getBalance();
                                break;
                            }
                        }
                    }
                    
                    $this->log("    - Current balance: {$balance} XLM");
                    
                    // Update wallet balance in database
                    if (!$this->dryRun) {
                        $result = $this->walletsCollection->updateOne(
                            ['_id' => $wallet['_id']],
                            [
                                '$set' => [
                                    'balance' => $balance,
                                    'lastUpdated' => new MongoDB\BSON\UTCDateTime()
                                ]
                            ]
                        );
                        
                        if (is_array($result) && isset($result['modified']) && $result['modified'] > 0) {
                            $this->log("    - Balance updated successfully");
                            $updatedCount++;
                        } else if (is_object($result) && method_exists($result, 'getModifiedCount') && $result->getModifiedCount() > 0) {
                            $this->log("    - Balance updated successfully");
                            $updatedCount++;
                        } else {
                            $this->log("    - No changes made to wallet");
                        }
                    } else {
                        $this->log("    - Would update balance to {$balance} XLM (dry run)");
                        $updatedCount++;
                    }
                    
                } catch (\Exception $e) {
                    // Account might not be activated yet
                    $this->log("    - Error: Account not found on Stellar network or not activated");
                    
                    if (!$this->dryRun) {
                        // Set balance to 0 for accounts not found
                        $result = $this->walletsCollection->updateOne(
                            ['_id' => $wallet['_id']],
                            [
                                '$set' => [
                                    'balance' => '0',
                                    'lastUpdated' => new MongoDB\BSON\UTCDateTime()
                                ]
                            ]
                        );
                        
                        if ((is_array($result) && isset($result['modified']) && $result['modified'] > 0) ||
                            (is_object($result) && method_exists($result, 'getModifiedCount') && $result->getModifiedCount() > 0)) {
                            $this->log("    - Balance set to 0");
                        }
                    }
                    
                    $errorCount++;
                }
                
            } catch (\Exception $e) {
                $this->log("  - Error processing wallet {$wallet['_id']}: " . $e->getMessage());
                $errorCount++;
            }
        }
        
        $this->log("Wallet balance update completed. Updated: {$updatedCount}, Errors: {$errorCount}");
    }

    /**
     * Log a message with timestamp
     */
    private function log($message) {
        if ($this->verboseOutput) {
            $timestamp = date('Y-m-d H:i:s');
            echo "[{$timestamp}] {$message}" . PHP_EOL;
        }
    }
}

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv) || in_array('-d', $argv);

// Run the script
$tool = new WalletCleanupTool($dryRun);
$tool->run();