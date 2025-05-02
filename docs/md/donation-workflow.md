# The GiveHub Donation Workflow System

## Overview

The GiveHub donation workflow system is a comprehensive payment processing infrastructure that enables both traditional payment methods and blockchain-based cryptocurrency donations. The system integrates with Stellar blockchain, along with other cryptocurrency networks, and provides robust mechanisms for transaction tracking, verification, and reporting.

This documentation provides a detailed overview of the donation workflow, including the components involved, the relationships between them, and the flow of donation processing from end to end.

## System Components

The donation workflow consists of the following key components:

1. **Donate Class (`lib/Donate.php`)**: The entry point for donation processing that handles both Square payments and crypto donations.

2. **DonationProcessor Class (`lib/DonationProcessor.php`)**: Handles the core donation business logic, database management, and notifications.

3. **TransactionProcessor Class (`lib/TransactionProcessor.php`)**: Handles blockchain-specific transaction processing, particularly for the Stellar network.

4. **BlockchainTransactionController Class (`lib/BlockchainTransactionController.php`)**: Manages blockchain transaction records and provides status verification.

5. **Transaction Class (`lib/Transaction.php`)**: The API controller for transaction operations that provides HTTP endpoints.

## Component Relationships

The relationship between components follows this structure:

```
┌─────────────────────┐          ┌──────────────────────────┐
│                     │          │                          │
│  Transaction Class  │          │    DonationProcessor     │
│   (API Controller)  │──────────▶    (Core Donation        │
│                     │          │     Business Logic)      │
└─────────────────────┘          │                          │
                                 └───────────────┬──────────┘
┌─────────────────────┐                          │
│                     │                          │
│     Donate Class    │          ┌───────────────▼──────────┐
│ (Payment Processor) │──────────▶  TransactionProcessor    │
│                     │          │  (Blockchain Processing) │
└────────────┬────────┘          │                          │
             │                   └───────────────┬──────────┘
             │                                   │
┌────────────▼────────┐          ┌───────────────▼──────────┐
│                     │          │                          │
│ BlockchainTransaction◄─────────▶ Database Collections     │
│     Controller      │          │                          │
│                     │          └──────────────────────────┘
└─────────────────────┘
```

## Donation Workflow Process

### 1. Payment Method Selection

The donation process begins when a user selects a payment method on the frontend:

* **Traditional Payment**: Credit card processing via Square
* **Cryptocurrency**: Stellar (XLM), Ethereum (ETH), or Bitcoin (BTC) payments

### 2. Donation Initiation

#### A. Square Payment Flow

For credit card payments through Square:

1. The frontend calls `/api.php/Donate/processSquarePayment` with payment details
2. The `Donate` class validates the data and connects to Square API
3. Square processes the payment and returns a confirmation
4. The `Donate` class forwards the payment data to `DonationProcessor`
5. The donation is recorded and the campaign funding is updated

#### B. Cryptocurrency Payment Flow

For cryptocurrency donations:

1. The frontend calls `/api.php/Donate/processCryptoDonation` with donation details
2. The `Donate` class validates the crypto type and generates payment instructions
3. A transaction reference is created and stored in the `blockchain_transactions` collection
4. A QR code and payment instructions are generated for the user
5. The user sends the cryptocurrency to the provided wallet address
6. The transaction is later verified through blockchain API calls

### 3. Transaction Recording

All transactions, regardless of payment method, are recorded in the system:

* **donations collection**: Stores donation records including amounts, donor info, and status
* **blockchain_transactions collection**: Records blockchain-specific transaction details

### 4. Transaction Verification

For cryptocurrency transactions, verification happens in two ways:

1. **User-Initiated**: The user provides the transaction hash after completing the payment
2. **Automated Checking**: The system periodically checks pending transactions via the blockchain API

The `BlockchainTransactionController` handles the verification by:

1. Querying the appropriate blockchain API (Stellar, Ethereum, or Bitcoin)
2. Updating the transaction status (pending, confirming, confirmed, failed)
3. Updating the source record (donation, milestone, etc.) with the new status

### 5. Milestone and Recurring Donations

The system supports two advanced donation features:

#### A. Milestone-Based Funding

1. Campaign creators can set up milestones for fund releases
2. Donors contribute to a campaign with milestone escrow
3. Funds are held in an escrow account on the Stellar blockchain
4. Authorized users release funds when milestones are completed
5. Each release creates a blockchain transaction with appropriate memo data

#### B. Recurring Donations

1. Donors can set up recurring donations with specified frequency
2. The system records the recurring schedule and donor authorization
3. A periodic task (`processRecurringDonations` method) processes due donations
4. New transactions are created for each recurrence
5. Donors can cancel recurring donations at any time

## Key Workflow Functions

### Donation Processing Functions

```
┌───────────────────────┐       ┌──────────────────────────┐
│ Donate Class          │       │ DonationProcessor Class  │
├───────────────────────┤       ├──────────────────────────┤
│ processSquarePayment()│──────▶│ processDonation()        │
│ processCryptoDonation()│─────▶│ validateDonationData()   │
│ getSupportedCryptos() │       │ structureDonationData()  │
│ verifyBlockchain      │       │ initiateTransaction()    │
│  Transaction()        │       │ updateCampaignFunding()  │
└───────────────────────┘       │ sendConfirmationEmail()  │
                                │ updateDonationStatus()   │
                                │ processRecurringDonations│
                                └──────────────────────────┘
```

### Blockchain Transaction Functions

```
┌───────────────────────────┐     ┌──────────────────────────┐
│ TransactionProcessor Class│     │ BlockchainTransaction    │
├───────────────────────────┤     │ Controller Class         │
│ processDonation()         │     ├──────────────────────────┤
│ updateCampaignFunding()   │     │ createTransaction()      │
│ releaseMilestoneFunding() │     │ updateTransactionStatus()│
│ createMilestoneEscrow()   │     │ checkTransactionStatus() │
│ getStellarAccountTrans    │     │ getTransaction()         │
│  actions()                │     │ getTransactionById()     │
│ checkStellarAccount       │     │ getTransactionsByStatus()│
│  Balance()                │     │ getUserTransactions()    │
└───────────────────────────┘     │ getCampaignTransactions()│
                                  │ getPendingTransactions() │
                                  └──────────────────────────┘
```

## Database Collections

The donation workflow utilizes several MongoDB collections:

1. **donations**: Main collection for donation records
2. **blockchain_transactions**: Records of all blockchain transactions
3. **campaigns**: Campaign information including funding targets and progress
4. **donors**: Information about donors and their donation history
5. **escrows**: Records of milestone-based funding escrow accounts
6. **transactions**: General transaction records that might not be donations
7. **notifications**: System notifications for various transaction events

## Transaction States

Transactions go through several states during their lifecycle:

| State | Description |
|-------|-------------|
| pending | Transaction is created but not yet confirmed on the blockchain |
| submitted | Transaction has been submitted to the blockchain network |
| confirming | Transaction is being processed by blockchain validators |
| confirmed | Transaction is confirmed and finalized on the blockchain |
| failed | Transaction failed to process or was rejected |
| expired | Pending transaction that timed out (after 1 hour) |

## Blockchain Integration

### Stellar Blockchain Integration

The system primarily uses the Stellar blockchain with these key features:

1. **Native Asset (XLM)**: Used for all Stellar transactions
2. **Transaction Memos**: Store donation references and metadata
3. **Escrow Accounts**: Hold funds for milestone-based campaigns
4. **Multi-Signature**: Used for added security on escrow accounts

### Other Cryptocurrency Support

The system also supports:

1. **Ethereum (ETH)**: Using wallet addresses and blockchain API integration
2. **Bitcoin (BTC)**: Using wallet addresses and blockchain API integration

## Payment Processing

### Square Payment Processing

For credit card payments, the system:

1. Creates a payment with Square API using a payment nonce
2. Processes the payment synchronously
3. Records the transaction immediately with a completed or failed status

### Cryptocurrency Payment Processing

For crypto payments, the system:

1. Generates a wallet address and payment instructions
2. Creates a pending transaction record
3. Waits for the user to complete the payment
4. Verifies the transaction through the blockchain API
5. Updates the transaction status once confirmed

## Error Handling and Resilience

The donation workflow implements several error handling mechanisms:

1. **Transaction Verification Retries**: Failed verifications are retried later
2. **Expired Transaction Handling**: Transactions pending for too long are marked as expired
3. **Failed Payment Recovery**: System tracks failed payments for potential recovery
4. **Error Logging**: Comprehensive error logging throughout the workflow

## Security Considerations

The donation system implements these security measures:

1. **Blockchain Transaction Verification**: Ensures donations are legitimate
2. **Payment Gateway Security**: Uses Square's security infrastructure for credit card processing
3. **Sensitive Data Handling**: Restricts visibility of donor information for anonymous donations
4. **Authorization Checks**: Ensures only authorized users can perform certain actions (like milestone releases)

## Reporting and Analytics

The donation workflow provides comprehensive reporting through:

1. **Transaction History**: Detailed history of all transactions
2. **Campaign Donation Reports**: Summaries of donations by campaign
3. **Donor Activity Reports**: Tracking of donor contributions over time
4. **Blockchain Transaction Verification**: Proof of donation through blockchain records

## API Endpoints

The donation workflow exposes these main API endpoints:

| Endpoint | Description |
|----------|-------------|
| `/api.php/Donate/processSquarePayment` | Process a Square credit card payment |
| `/api.php/Donate/processCryptoDonation` | Generate crypto payment instructions |
| `/api.php/Transaction/processDonation` | Process a blockchain-based donation |
| `/api.php/Transaction/getTransactionHistory` | Get history of transactions |
| `/api.php/Transaction/getTransaction` | Get details of a specific transaction |
| `/api.php/Transaction/createMilestoneEscrow` | Create a milestone-based escrow account |
| `/api.php/Transaction/releaseMilestoneFunding` | Release funds from an escrow account |

## Integration Example

### Processing a Cryptocurrency Donation

```javascript
// Frontend JavaScript example
async function makeCryptoDonation() {
  const donationData = {
    cryptoType: "XLM",
    amount: 50,
    campaignId: "60a3e5b2f91a123456789012",
    donorInfo: {
      name: "John Doe",
      email: "john@example.com"
    },
    message: "Supporting this great cause!",
    isAnonymous: false
  };

  const response = await fetch('/api.php/Donate/processCryptoDonation', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(donationData)
  });

  const result = await response.json();
  
  if (result.success) {
    // Show QR code and payment instructions to user
    displayQRCode(result.qrCode);
    displayWalletAddress(result.walletAddress);
    displayInstructions(result.instructions);
    
    // Store transaction reference for later verification
    saveTransactionReference(result.reference);
  } else {
    // Handle error
    displayError(result.error);
  }
}
```

## Best Practices

### When Using the Donation System:

1. **Always Check Transaction Status**: Verify transaction status before considering a donation complete
2. **Handle Multiple Currencies**: Be prepared to handle different donation currencies
3. **Implement Proper Error Handling**: Add robust error handling for network and blockchain issues
4. **Regular Verification**: Use the verification system to regularly check pending transactions
5. **Backup Recovery Information**: For blockchain donations, always maintain backup recovery information

## Conclusion

The GiveHub donation workflow system provides a robust, secure, and flexible way to accept and manage donations through both traditional payment methods and cryptocurrencies. The system's modular design allows for easy extension to support additional payment methods or blockchain networks in the future.