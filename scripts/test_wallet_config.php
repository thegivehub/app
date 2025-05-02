<?php
/**
 * Test Wallet Configuration Script
 * 
 * This script checks if your cryptocurrency wallet configuration is properly set up.
 * It displays the current configuration and verifies environment variables.
 * 
 * Usage: php test_wallet_config.php
 */

// Check if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    echo "Found .env file\n";
} else {
    echo "WARNING: .env file not found. Create one from .env.example or .env.sample\n";
}

// Load composer autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    echo "WARNING: vendor/autoload.php not found. Run 'composer install' first.\n";
}

// Helper function to check env vars
function check_env_var($name, $sensitive = false) {
    $value = getenv($name);
    if (!$value) {
        echo "❌ $name: Not configured\n";
        return false;
    } else {
        if ($sensitive) {
            $masked = substr($value, 0, 4) . '...' . substr($value, -4);
            echo "✅ $name: $masked (Sensitive value masked)\n";
        } else {
            echo "✅ $name: $value\n";
        }
        return true;
    }
}

// Helper to check wallet configuration
function check_wallet_config($currency, $vars) {
    echo "\n=== $currency Wallet Configuration ===\n";
    $configured = true;
    
    foreach ($vars as $var => $sensitive) {
        if (!check_env_var($var, $sensitive)) {
            $configured = false;
        }
    }
    
    return $configured;
}

// Check if MultiCurrencyWallet.php exists
$multiWalletFile = __DIR__ . '/../lib/MultiCurrencyWallet.php';
if (file_exists($multiWalletFile)) {
    echo "✅ Found MultiCurrencyWallet.php\n";
} else {
    echo "❌ MultiCurrencyWallet.php not found. Make sure it's in the lib directory.\n";
}

// Check if Donate.php is properly configured
$donateFile = __DIR__ . '/../lib/Donate.php';
if (file_exists($donateFile)) {
    echo "✅ Found Donate.php\n";
    
    // Check if the file contains the proper environment variable references
    $donateContent = file_get_contents($donateFile);
    if (strpos($donateContent, 'ETHEREUM_ADDRESS') !== false &&
        strpos($donateContent, 'BITCOIN_ADDRESS') !== false &&
        strpos($donateContent, 'STELLAR_PUBLIC_KEY') !== false) {
        echo "✅ Donate.php contains proper wallet environment variables\n";
    } else {
        echo "⚠️ Donate.php may not be using the correct environment variables\n";
    }
} else {
    echo "⚠️ Donate.php not found. This file should integrate with the wallet system.\n";
}

// Check each cryptocurrency configuration
$xlmConfigured = check_wallet_config('Stellar (XLM)', [
    'STELLAR_PUBLIC_KEY' => true,
    'STELLAR_SECRET_KEY' => true,
    'STELLAR_NETWORK' => false
]);

$ethConfigured = check_wallet_config('Ethereum (ETH)', [
    'ETHEREUM_ADDRESS' => true,
    'ETHEREUM_PRIVATE_KEY' => true,
    'ETHEREUM_NETWORK' => false,
    'ETHEREUM_INFURA_PROJECT_ID' => true
]);

$btcConfigured = check_wallet_config('Bitcoin (BTC)', [
    'BITCOIN_ADDRESS' => true,
    'BITCOIN_PRIVATE_KEY' => true,
    'BITCOIN_NETWORK' => false
]);

// Check general cryptocurrency settings
echo "\n=== General Cryptocurrency Settings ===\n";
check_env_var('DEFAULT_DONATION_CURRENCY', false);
check_env_var('ENABLE_MULTIPLE_CRYPTOCURRENCIES', false);

// Print summary and recommendations
echo "\n=== Summary ===\n";
$configuredWallets = [];
if ($xlmConfigured) $configuredWallets[] = 'XLM';
if ($ethConfigured) $configuredWallets[] = 'ETH';
if ($btcConfigured) $configuredWallets[] = 'BTC';

if (empty($configuredWallets)) {
    echo "❌ No cryptocurrency wallets fully configured.\n";
    echo "   Run 'php scripts/generate_crypto_wallets.php' to create new wallets.\n";
} else {
    echo "✅ Configured cryptocurrencies: " . implode(', ', $configuredWallets) . "\n";
    
    $defaultCurrency = getenv('DEFAULT_DONATION_CURRENCY');
    if (!$defaultCurrency || !in_array($defaultCurrency, $configuredWallets)) {
        echo "⚠️ DEFAULT_DONATION_CURRENCY should be one of the configured cryptocurrencies.\n";
    }
}

$enableMultiple = getenv('ENABLE_MULTIPLE_CRYPTOCURRENCIES');
if ($enableMultiple === 'true' && count($configuredWallets) < 2) {
    echo "⚠️ ENABLE_MULTIPLE_CRYPTOCURRENCIES is true, but less than 2 cryptocurrencies are configured.\n";
}

// Test integration with MultiCurrencyWallet.php
if (file_exists($multiWalletFile)) {
    echo "\n=== Testing MultiCurrencyWallet integration ===\n";
    try {
        // Check if class is available before trying to load it
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            require_once $multiWalletFile;
            
            if (class_exists('MultiCurrencyWallet')) {
                echo "✅ MultiCurrencyWallet class loaded successfully.\n";
                
                try {
                    // Try to create an instance of the class
                    $wallet = new MultiCurrencyWallet();
                    
                    // Check if getAvailableCurrencies method exists
                    if (method_exists($wallet, 'getAvailableCurrencies')) {
                        $currencies = $wallet->getAvailableCurrencies();
                        
                        if (isset($currencies['success']) && $currencies['success']) {
                            echo "✅ getAvailableCurrencies() method works.\n";
                            
                            if (isset($currencies['currencies'])) {
                                echo "   Available currencies: ";
                                foreach ($currencies['currencies'] as $symbol => $data) {
                                    if ($symbol !== '_config') {
                                        echo "$symbol ";
                                    }
                                }
                                echo "\n";
                            } else {
                                echo "   No currencies returned in response.\n";
                            }
                        } else {
                            echo "⚠️ getAvailableCurrencies() method returned an error.\n";
                            if (isset($currencies['error'])) {
                                echo "   Error: " . $currencies['error'] . "\n";
                            }
                        }
                    } else {
                        echo "❌ getAvailableCurrencies() method not found in MultiCurrencyWallet class.\n";
                    }
                } catch (Throwable $e) {
                    echo "❌ Error creating MultiCurrencyWallet instance: " . $e->getMessage() . "\n";
                }
            } else {
                echo "❌ MultiCurrencyWallet class not found in the file.\n";
            }
        } else {
            echo "❌ vendor/autoload.php not found. Cannot test class integration.\n";
        }
    } catch (Throwable $e) {
        echo "❌ Error testing MultiCurrencyWallet: " . $e->getMessage() . "\n";
    }
}

echo "\nConfiguration test completed.\n";