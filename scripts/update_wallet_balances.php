<?php
/**
 * Update Wallet Balances Script
 * 
 * This script updates wallet balances from the Stellar network
 * It's designed to be run as a scheduled task (cron job)
 * 
 * Usage: php update_wallet_balances.php
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Use Soneso Stellar SDK
use Soneso\StellarSDK\Server;
use Soneso\StellarSDK\StellarSDK;

class WalletBalanceUpdater {
    private $db;
    private $walletsCollection;
    private $stellarServer;
    private $isTestnet = true;
    private $verbose = false;

    public function __construct($verbose = false) {
        $this->db = new Database();
        $this->walletsCollection = $this->db->getCollection('wallets');
        
        // Initialize Stellar server (testnet by default)
        $horizonUrl = $this->isTestnet 
            ? 'https://horizon-testnet.stellar.org' 
            : 'https://horizon.stellar.org';
        $sdk = new StellarSDK($horizonUrl);
        $this->stellarServer = $sdk;
        
        $this->verbose = $verbose;
        $this->log("Wallet Balance Updater Initialized");
    }

    /**
     * Update balances for all wallets
     */
    public function updateAllWallets() {
        $this->log("Starting wallet balance update process...");
        
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
                    $this->log("Wallet ID: {$wallet['_id']} - Missing public key, skipping");
                    continue;
                }
                
                $publicKey = $wallet['publicKey'];
                $this->log("Updating wallet: {$publicKey}");
                
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
                    
                    $this->log("Current balance: {$balance} XLM");
                    
                    // Update wallet balance in database
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
                        $this->log("Balance updated successfully");
                        $updatedCount++;
                    } else if (is_object($result) && method_exists($result, 'getModifiedCount') && $result->getModifiedCount() > 0) {
                        $this->log("Balance updated successfully");
                        $updatedCount++;
                    } else {
                        $this->log("No changes made to wallet");
                    }
                    
                } catch (\Exception $e) {
                    // Account might not be activated yet
                    $this->log("Error: Account not found on Stellar network or not activated");
                    
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
                        $this->log("Balance set to 0");
                    }
                    
                    $errorCount++;
                }
                
            } catch (\Exception $e) {
                $this->log("Error processing wallet {$wallet['_id']}: " . $e->getMessage());
                $errorCount++;
            }
        }
        
        $this->log("Wallet balance update completed. Updated: {$updatedCount}, Errors: {$errorCount}");
    }

    /**
     * Log a message with timestamp
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}";
        
        // Always write to log file
        file_put_contents(
            __DIR__ . '/../logs/wallet_updates.log', 
            $logMessage . PHP_EOL,
            FILE_APPEND
        );
        
        // Only output to console if verbose mode is enabled
        if ($this->verbose) {
            echo $logMessage . PHP_EOL;
        }
    }
}

// Parse command line arguments
$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);

// Run the script
$updater = new WalletBalanceUpdater($verbose);
$updater->updateAllWallets();