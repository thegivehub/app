<?php
/**
 * Script to ensure all wallets (users and campaigns) are funded on the Stellar testnet
 *
 * This script will:
 * 1. Find all wallets in the database
 * 2. Check if they are already funded on the testnet
 * 3. Fund any unfunded wallets using Friendbot
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/Collection.php';
require_once __DIR__ . '/../vendor/soneso/stellar-php-sdk/Soneso/StellarSDK/StellarSDK.php';

// Use Soneso Stellar SDK
use Soneso\StellarSDK\StellarSDK;
use Soneso\StellarSDK\Util\FriendBot;

class WalletFunder {
    private $db;
    private $walletsCollection;
    private $stellarSdk;
    
    public function __construct() {
        // Initialize database connection
        $this->db = new Database();
        $this->walletsCollection = $this->db->getCollection('wallets');
        
        // Initialize Stellar SDK
        $this->stellarSdk = StellarSDK::getTestNetInstance();
    }
    
    /**
     * Main function to process all wallets
     */
    public function processAllWallets() {
        try {
            // Get all wallets from the database
            $wallets = $this->getAllWallets();
            
            if (empty($wallets)) {
                echo "No wallets found in the database.\n";
                return;
            }
            
            $totalWallets = count($wallets);
            echo "Found {$totalWallets} wallets in the database.\n";
            
            $fundedCount = 0;
            $alreadyFundedCount = 0;
            $failedCount = 0;
            
            // Process each wallet
            foreach ($wallets as $wallet) {
                $publicKey = $wallet['publicKey'];
                $walletId = (string)$wallet['_id'];
                
                $walletType = $wallet['type'] ?? 'unknown';
                $walletLabel = $wallet['label'] ?? 'Unlabeled Wallet';
                
                echo "\nProcessing {$walletType} wallet: {$walletLabel} (ID: {$walletId})\n";
                echo "  Public Key: {$publicKey}\n";
                
                // Check if wallet is already funded
                $isFunded = $this->isAccountFunded($publicKey);
                
                if ($isFunded) {
                    echo "  ✓ Wallet is already funded!\n";
                    $alreadyFundedCount++;
                    continue;
                }
                
                // Fund the wallet using Friendbot
                echo "  Attempting to fund wallet with Friendbot...\n";
                $result = $this->fundAccount($publicKey);
                
                if ($result['success']) {
                    echo "  ✓ Successfully funded wallet!\n";
                    echo "    Balance: " . $result['balance'] . " XLM\n";
                    $fundedCount++;
                } else {
                    echo "  ✗ Failed to fund wallet: " . $result['error'] . "\n";
                    $failedCount++;
                }
            }
            
            // Print summary
            echo "\nFunding summary:\n";
            echo "Total wallets processed: {$totalWallets}\n";
            echo "Already funded: {$alreadyFundedCount}\n";
            echo "Newly funded: {$fundedCount}\n";
            echo "Failed to fund: {$failedCount}\n";
            
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    /**
     * Get all wallets from the database
     */
    private function getAllWallets() {
        $cursor = $this->walletsCollection->find([]);
        $wallets = [];
        
        foreach ($cursor as $wallet) {
            $wallets[] = $wallet;
        }
        
        return $wallets;
    }
    
    /**
     * Check if an account is already funded on the Stellar network
     */
    private function isAccountFunded($publicKey) {
        try {
            // Try to get account info from the Stellar network
            $this->stellarSdk->requestAccount($publicKey);
            return true;
        } catch (Exception $e) {
            // If account is not found, it's not funded
            return false;
        }
    }
    
    /**
     * Fund an account using Friendbot
     */
    private function fundAccount($publicKey) {
        try {
            $success = FriendBot::fundTestAccount($publicKey);
            
            if (!$success) {
                return [
                    'success' => false,
                    'error' => 'Friendbot funding failed without specific error'
                ];
            }
            
            // Verify account is now funded
            try {
                $account = $this->stellarSdk->requestAccount($publicKey);
                $balance = '0';
                
                // Get the native XLM balance
                foreach ($account->getBalances() as $stellarBalance) {
                    if ($stellarBalance->getAssetType() === 'native') {
                        $balance = $stellarBalance->getBalance();
                        break;
                    }
                }
                
                return [
                    'success' => true,
                    'balance' => $balance
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'error' => 'Failed to verify funding: ' . $e->getMessage()
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Run the script
echo "Starting wallet funding process for all wallets (users and campaigns)...\n";
$funder = new WalletFunder();
$funder->processAllWallets();
echo "\nWallet funding process completed.\n";