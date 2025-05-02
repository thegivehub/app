# Stellar Fee Management System

## Overview

The Stellar Fee Management System dynamically optimizes transaction fees based on network conditions, ensuring transactions are processed efficiently without overpaying. The system continuously monitors the Stellar network's fee market and recommends appropriate fees based on congestion levels and transaction priority.

## Key Features

- **Dynamic Fee Calculation**: Automatically adjusts fees based on real-time network conditions
- **Congestion Analysis**: Identifies network congestion levels (low, medium, high, critical)
- **Priority Levels**: Supports different priority levels for transactions (low, medium, high)
- **Fee Bumping**: Ability to increase fees on pending transactions to accelerate confirmation
- **Fee Caching**: Implements caching to reduce API calls to the Stellar network
- **Fee Statistics**: Provides reporting on current network fee conditions

## How It Works

### Network Congestion Analysis

The system analyzes Stellar network congestion using fee percentile comparisons:

| Congestion Level | Description | Indicators | Fee Multiplier |
|-----------------|-------------|------------|---------------|
| Low | Normal network conditions | Fees are relatively uniform (p90 < 1.5x p10) | 1.0x |
| Medium | Moderate congestion | p90 is 1.5-3x higher than p10 | 1.5x |
| High | High congestion | p90 is 3-5x higher than p10 | 2.0x |
| Critical | Severe congestion | p90 is 5x+ higher than p10 | 3.0x |

### Fee Recommendation Process

1. **Fee Stats Collection**: The system fetches current fee statistics from the Stellar Horizon API
2. **Congestion Analysis**: Analyzes fee percentiles to determine network congestion
3. **Priority Selection**: Selects a base fee based on requested priority:
   - Low Priority: Uses the p10 fee percentile (lower cost, may take longer)
   - Medium Priority: Uses the p50 fee percentile (median fee)
   - High Priority: Uses the p90 fee percentile (higher cost, faster processing)
4. **Apply Congestion Multiplier**: Multiplies the base fee by a congestion-based factor
5. **Minimum Fee Enforcement**: Ensures the fee meets Stellar's minimum requirements

### Fee Bumping

When a transaction is stuck due to low fees, the system can "bump" the fee by:

1. Creating a fee bump transaction that wraps the original transaction
2. Setting a higher fee based on current network conditions with high priority
3. Signing and submitting the fee bump transaction

This is particularly useful during sudden network congestion or when transaction processing times are critical.

## Usage Examples

### Basic Fee Recommendation

```php
// Initialize the fee manager
$feeManager = new StellarFeeManager([
    'useTestnet' => true, // Use Stellar testnet
    'enableLogging' => true // Enable detailed logging
]);

// Get recommended fee for a standard priority transaction
$recommendedFee = $feeManager->getRecommendedFee([
    'priorityLevel' => 'medium'
]);

// Estimate fee for a transaction with multiple operations
$estFee = $feeManager->estimateTransactionFee(5, [ // Transaction with 5 operations
    'priorityLevel' => 'high'
]);
```

### Creating a Transaction with Recommended Fee

```php
// Initialize transaction builder
$sourceAccount = $stellarServer->accounts()->account($sourcePublicKey);
$transactionBuilder = new TransactionBuilder($sourceAccount);

// Add operations to the transaction
$transactionBuilder->addOperation(new PaymentOperation(
    $destinationAddress,
    Asset::native(),
    "100" // Amount in XLM
));

// Use fee manager to set the appropriate fee
$transaction = $feeManager->createTransactionWithRecommendedFee($transactionBuilder, [
    'priorityLevel' => 'high',
    'operationCount' => 1
]);

// Sign and submit the transaction
$transaction->sign($sourceKeypair, Network::testnet());
$response = $stellarServer->submitTransaction($transaction);
```

### Fee Bumping a Stuck Transaction

```php
try {
    // Attempt to submit a transaction
    $response = $stellarServer->submitTransaction($transaction);
} catch (Exception $e) {
    // Check if the error is fee-related
    if ($feeManager->isFeeRelatedError($e)) {
        // Create a fee bump transaction
        $feeBumpTransaction = $feeManager->createFeeBumpTransaction(
            $sourceSecretKey,
            $transaction
        );
        
        // Submit the fee bump transaction
        $response = $stellarServer->submitTransaction($feeBumpTransaction);
    } else {
        // Handle other errors
        throw $e;
    }
}
```

## Fee Statistics and Monitoring

The fee management system provides reporting capabilities to monitor network conditions:

```php
// Get detailed fee statistics
$feeStats = $feeManager->getFeeStatistics();

// Output includes:
// - Current timestamp
// - Network congestion level
// - Network type (testnet/public)
// - Fee statistics (min, max, median, p90)
// - Recommended fees for different priority levels
```

## Configuration Options

| Option | Description | Default |
|--------|-------------|---------|
| `defaultBaseFee` | Default base fee in stroops | 100 |
| `lowMultiplier` | Fee multiplier for low congestion | 1.0 |
| `mediumMultiplier` | Fee multiplier for medium congestion | 1.5 |
| `highMultiplier` | Fee multiplier for high congestion | 2.0 |
| `criticalMultiplier` | Fee multiplier for critical congestion | 3.0 |
| `cacheDuration` | Duration to cache fee stats (milliseconds) | 60000 (1 minute) |
| `useTestnet` | Whether to use Stellar testnet | true |
| `horizonUrl` | Custom Horizon server URL | Based on testnet setting |
| `enableLogging` | Enable detailed logging | false |

## Best Practices

1. **Use Appropriate Priority Levels**:
   - Low: For non-urgent transactions where cost is a concern
   - Medium: For standard transactions with normal processing times
   - High: For time-sensitive transactions that need quick processing

2. **Handle Fee-Related Errors**:
   - Always check for fee-related errors when submitting transactions
   - Use fee bumping when transactions are stuck due to insufficient fees

3. **Monitor Network Conditions**:
   - Periodically fetch fee statistics to monitor network health
   - Consider adjusting default multipliers based on observed patterns

4. **Cache Optimization**:
   - Adjust cache duration based on network volatility and application needs
   - For high-frequency applications, consider shorter cache times

5. **Transaction Batching**:
   - When possible, batch multiple operations into a single transaction
   - Use `estimateTransactionFee()` with the correct operation count

## Technical Implementation

The fee management system is implemented in the `StellarFeeManager.php` class, which integrates with the Soneso Stellar SDK. The system maintains compatibility with the overall application architecture while providing a comprehensive fee management solution.