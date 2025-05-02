<?php
/**
 * Generate Cryptocurrency Wallets Script
 * 
 * This script generates new cryptocurrency wallets for The GiveHub platform.
 * It creates wallets for Stellar (XLM), and prints instructions for Ethereum and Bitcoin.
 * 
 * Usage: php generate_crypto_wallets.php [--testnet] [--output=file]
 * 
 * Options:
 *   --testnet       Generate testnet wallets (default)
 *   --mainnet       Generate mainnet wallets
 *   --output=file   Save output to a file instead of printing to console
 * 
 * Requirements:
 *   - PHP 7.4 or higher
 *   - Stellar SDK: composer require soneso/stellar-php-sdk
 */

// Start output buffer
ob_start();

echo "======================================================\n";
echo " The GiveHub Cryptocurrency Wallet Generator\n";
echo "======================================================\n\n";

// Check if composer autoload is available
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "ERROR: Composer dependencies not found!\n\n";
    echo "Please run this command from the project root directory:\n";
    echo "composer require soneso/stellar-php-sdk\n\n";
    echo "Then try running this script again.\n";
    $output = ob_get_clean();
    echo $output;
    exit(1);
}

// Load Composer dependencies
require_once __DIR__ . '/../vendor/autoload.php';

// Try to import the StellarKeypair class - we'll check if it exists later
$stellarSdkAvailable = false;
try {
    // Check if the class exists before trying to use it
    if (class_exists('Soneso\StellarSDK\Keypair')) {
        $stellarSdkAvailable = true;
    }
} catch (Throwable $e) {
    // SDK not available - we'll handle this below
}

// Parse command line arguments
$options = getopt('', ['testnet', 'mainnet', 'output:']);
$isTestnet = isset($options['testnet']) || !isset($options['mainnet']);
$outputFile = isset($options['output']) ? $options['output'] : null;

echo "Network Mode: " . ($isTestnet ? "TESTNET" : "MAINNET") . "\n\n";

echo "WARNING: Store these keys securely. Anyone with access to the\n";
echo "private keys will have full control of the funds.\n\n";

// Generate Stellar wallet
echo "======================================================\n";
echo " STELLAR (XLM) WALLET\n";
echo "======================================================\n\n";

try {
    // Check if Stellar SDK is available
    if (!$stellarSdkAvailable) {
        echo "Stellar SDK not found. Please install it with:\n";
        echo "composer require soneso/stellar-php-sdk\n\n";
        
        // Generate a simple random secret for demo purposes
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $stellarSecretKey = 'S';
        for ($i = 0; $i < 55; $i++) {
            $stellarSecretKey .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        echo "EXAMPLE Stellar Keys (NOT SECURE - FOR DEMO ONLY):\n";
        echo "Public Key: G" . substr(str_shuffle($chars), 0, 55) . "\n";
        echo "Secret Key: $stellarSecretKey\n\n";
        
        echo "WARNING: The keys above are NOT SECURE - they're randomly generated\n";
        echo "for demonstration only. Install the Stellar SDK for proper key generation.\n\n";
    } else {
        // Use the Stellar SDK to generate a proper keypair
        $stellarKeypair = \Soneso\StellarSDK\Keypair::random();
        $stellarPublicKey = $stellarKeypair->getPublicKey();
        $stellarSecretKey = $stellarKeypair->getSecret();
        
        echo "Public Key: $stellarPublicKey\n";
        echo "Secret Key: $stellarSecretKey\n\n";
        
        echo "Add to .env file:\n";
        echo "STELLAR_PUBLIC_KEY=$stellarPublicKey\n";
        echo "STELLAR_SECRET_KEY=$stellarSecretKey\n";
        echo "STELLAR_NETWORK=" . ($isTestnet ? "testnet" : "public") . "\n\n";
        
        if ($isTestnet) {
            echo "To fund this testnet account, visit:\n";
            echo "https://laboratory.stellar.org/#account-creator?network=test\n";
            echo "or\n";
            echo "https://friendbot.stellar.org/?addr=$stellarPublicKey\n\n";
        }
    }
} catch (Exception $e) {
    echo "Error generating Stellar wallet: " . $e->getMessage() . "\n\n";
}

// Ethereum wallet instructions
echo "======================================================\n";
echo " ETHEREUM (ETH) WALLET\n";
echo "======================================================\n\n";

echo "For security reasons, we recommend creating an Ethereum wallet\n";
echo "using a trusted external tool such as:\n\n";
echo "1. MetaMask (https://metamask.io)\n";
echo "2. MyEtherWallet (https://www.myetherwallet.com)\n";
echo "3. Hardware wallet (Ledger, Trezor, etc.)\n\n";

echo "After creating a wallet, add to .env file:\n";
echo "ETHEREUM_ADDRESS=your_eth_address\n";
echo "ETHEREUM_PRIVATE_KEY=your_private_key (if needed for automation)\n";
echo "ETHEREUM_NETWORK=" . ($isTestnet ? "goerli" : "mainnet") . "\n";
echo "ETHEREUM_INFURA_PROJECT_ID=your_infura_project_id\n\n";

if ($isTestnet) {
    echo "To fund a testnet Ethereum wallet, visit:\n";
    echo "https://goerlifaucet.com/\n\n";
}

// Bitcoin wallet instructions
echo "======================================================\n";
echo " BITCOIN (BTC) WALLET\n";
echo "======================================================\n\n";

echo "For security reasons, we recommend creating a Bitcoin wallet\n";
echo "using a trusted external tool such as:\n\n";
echo "1. Bitcoin Core (https://bitcoin.org/en/bitcoin-core/)\n";
echo "2. Electrum (https://electrum.org/)\n";
echo "3. Hardware wallet (Ledger, Trezor, etc.)\n\n";

echo "After creating a wallet, add to .env file:\n";
echo "BITCOIN_ADDRESS=your_btc_address\n";
echo "BITCOIN_PRIVATE_KEY=your_private_key (if needed for automation)\n";
echo "BITCOIN_NETWORK=" . ($isTestnet ? "testnet" : "mainnet") . "\n\n";

if ($isTestnet) {
    echo "To fund a testnet Bitcoin wallet, visit:\n";
    echo "https://coinfaucet.eu/en/btc-testnet/\n";
    echo "https://testnet-faucet.mempool.co/\n\n";
}

// General wallet settings
echo "======================================================\n";
echo " GENERAL WALLET SETTINGS\n";
echo "======================================================\n\n";

echo "To configure which cryptocurrencies are available for donations,\n";
echo "set these environment variables:\n\n";
echo "DEFAULT_DONATION_CURRENCY=XLM\n";
echo "ENABLE_MULTIPLE_CRYPTOCURRENCIES=true\n\n";

// Security warning
echo "======================================================\n";
echo " IMPORTANT SECURITY NOTES\n";
echo "======================================================\n\n";

echo "1. NEVER store private keys directly in your code\n";
echo "2. NEVER commit .env files containing keys to git\n";
echo "3. Use environment variables or a secure secrets manager\n";
echo "4. Regularly backup your wallet keys and store securely\n";
echo "5. Consider using a hardware wallet for large amounts\n";
echo "6. Follow cryptocurrency best practices for security\n\n";

// Prepare values for template
$stellarPublicKeyValue = isset($stellarPublicKey) ? $stellarPublicKey : 'YOUR_STELLAR_PUBLIC_KEY';
$stellarSecretKeyValue = isset($stellarSecretKey) ? $stellarSecretKey : 'YOUR_STELLAR_SECRET_KEY';
$stellarNetwork = $isTestnet ? 'testnet' : 'public';
$ethNetwork = $isTestnet ? 'goerli' : 'mainnet';
$btcNetwork = $isTestnet ? 'testnet' : 'mainnet';

// Generate a string for a basic sample .env file
$envContent = <<<EOT
# The GiveHub - Cryptocurrency Wallet Configuration

# Stellar XLM Wallet Configuration
STELLAR_PUBLIC_KEY=$stellarPublicKeyValue
STELLAR_SECRET_KEY=$stellarSecretKeyValue
STELLAR_NETWORK=$stellarNetwork

# Ethereum Wallet Configuration
ETHEREUM_ADDRESS=YOUR_ETHEREUM_ADDRESS
ETHEREUM_PRIVATE_KEY=YOUR_ETHEREUM_PRIVATE_KEY
ETHEREUM_NETWORK=$ethNetwork
ETHEREUM_INFURA_PROJECT_ID=YOUR_INFURA_PROJECT_ID

# Bitcoin Wallet Configuration
BITCOIN_ADDRESS=YOUR_BITCOIN_ADDRESS
BITCOIN_PRIVATE_KEY=YOUR_BITCOIN_PRIVATE_KEY
BITCOIN_NETWORK=$btcNetwork

# Cryptocurrency Settings
DEFAULT_DONATION_CURRENCY=XLM
ENABLE_MULTIPLE_CRYPTOCURRENCIES=true
EOT;

echo "======================================================\n";
echo " SAMPLE .ENV CONFIGURATION\n";
echo "======================================================\n\n";

echo "Here's a sample .env configuration for your wallets:\n\n";
echo $envContent . "\n\n";

// Get buffer content
$output = ob_get_clean();

// Output to file or console
if ($outputFile) {
    file_put_contents($outputFile, $output);
    echo "Wallet information has been saved to: $outputFile\n";
    
    // Also generate a sample .env file if requested
    $envOutputFile = dirname($outputFile) . '/wallet.env.sample';
    file_put_contents($envOutputFile, $envContent);
    echo "Sample .env configuration saved to: $envOutputFile\n";
} else {
    echo $output;
}

// Exit with success
exit(0);