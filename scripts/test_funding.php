<?php
/**
 * Test script to demonstrate wallet funding using Friendbot
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/soneso/stellar-php-sdk/Soneso/StellarSDK/StellarSDK.php';

use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\StellarSDK;
use Soneso\StellarSDK\Util\FriendBot;

// Generate a new random Stellar keypair (will be unfunded)
$keypair = KeyPair::random();
$publicKey = $keypair->getAccountId();
$secretKey = $keypair->getSecretSeed();

echo "Generated new random Stellar keypair:\n";
echo "Public Key: $publicKey\n";
echo "Secret Key: $secretKey\n\n";

// Initialize Stellar SDK
$sdk = StellarSDK::getTestNetInstance();

// Check if account is funded (it shouldn't be)
echo "Checking if account is funded...\n";
try {
    // Try to get account info
    $account = $sdk->requestAccount($publicKey);
    echo "✓ Account is already funded! Balances:\n";
    foreach ($account->getBalances() as $balance) {
        if ($balance->getAssetType() === 'native') {
            echo "  XLM: " . $balance->getBalance() . "\n";
        }
    }
} catch (\Exception $e) {
    echo "✗ Account is not funded: " . $e->getMessage() . "\n";
    
    // Fund with Friendbot
    echo "\nAttempting to fund with Friendbot...\n";
    try {
        $funded = FriendBot::fundTestAccount($publicKey);
        if ($funded) {
            echo "✓ Successfully funded account with Friendbot!\n";
            
            // Verify funding
            try {
                $account = $sdk->requestAccount($publicKey);
                echo "\nAccount balances after funding:\n";
                foreach ($account->getBalances() as $balance) {
                    if ($balance->getAssetType() === 'native') {
                        echo "  XLM: " . $balance->getBalance() . "\n";
                    }
                }
            } catch (\Exception $e) {
                echo "Error verifying funding: " . $e->getMessage() . "\n";
            }
        } else {
            echo "✗ Failed to fund account with Friendbot\n";
        }
    } catch (\Exception $e) {
        echo "Error funding account with Friendbot: " . $e->getMessage() . "\n";
    }
}