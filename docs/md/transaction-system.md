# The Give Hub Transaction System Documentation

## Overview

The Give Hub transaction system is built on the Stellar blockchain to enable secure, transparent donations to social causes. The system handles one-time donations, recurring donations, and milestone-based funding releases with escrow accounts.

The transaction system is implemented across three primary components:

1. **StellarTransactionBuilder.js**: JavaScript module for building, signing, and submitting Stellar blockchain transactions
2. **TransactionService.js**: Node.js service for managing blockchain transactions
3. **TransactionProcessor.php**: PHP implementation for processing transactions

## Table of Contents

- [Architecture](#architecture)
- [Transaction Types](#transaction-types)
- [Key Features](#key-features)
- [Implementation Details](#implementation-details)
- [API Reference](#api-reference)
- [Error Handling](#error-handling)
- [Security Considerations](#security-considerations)
- [Examples](#examples)

## Architecture

The transaction system follows a layered architecture:

1. **Blockchain Layer**: Stellar blockchain for transaction processing and verification
2. **Transaction Builder Layer**: Low-level functions to create and submit blockchain transactions
3. **Service Layer**: Business logic handling donation processing, escrow management, etc.
4. **Database Layer**: Persistent storage of transaction records, donors, campaigns, etc.
5. **API Layer**: Endpoints for client applications to interact with the transaction system

![Architecture Diagram](architecture-diagram.png)

## Transaction Types

The system supports several transaction types:

### One-Time Donations

Simple payments from a donor to a campaign with optional metadata:
- Amount and currency (XLM by default)
- Anonymous or public attribution
- Optional message

### Recurring Donations

Periodic donations set up with a specified frequency:
- Weekly, monthly, quarterly, or annual
- Automated processing
- Cancellation capability

### Milestone Escrow Transactions

Multi-phase funding with escrow accounts:
- Funds held in escrow until milestone conditions are met
- Release triggered by campaign creator or admin
- Notifies all stakeholders upon milestone completion

## Key Features

### Blockchain Integration

- Native Stellar blockchain transactions
- Full transaction history and verification
- Memo field for storing metadata

### Recurring Donations Management

- Flexible scheduling (weekly, monthly, quarterly, annual)
- Automatic processing
- Donor notifications
- Cancellation management

### Milestone-Based Funding

- Escrow account creation and management
- Milestone activation based on funding goals
- Controlled fund release
- Stakeholder notifications

### Transparency and Reporting

- Full transaction history for campaigns and donors
- Blockchain verification of all transactions
- Campaign donation reports with analytics

## Implementation Details

### StellarTransactionBuilder.js

The JavaScript builder provides low-level functions for creating and submitting Stellar transactions:

```javascript
// Example: Creating a payment transaction
const tx = await stellarBuilder.createPayment({
  sourceSecret: donor.secretKey,
  destinationAddress: campaign.stellarAddress,
  amount: "100",
  memo: "Donation for COVID-19 Relief"
});

// Submit the transaction
const result = await stellarBuilder.submitTransaction(tx);
```

Key methods:
- `createPayment`: Create a simple payment transaction
- `createDonation`: Create a donation with campaign metadata
- `createRecurringDonationSetup`: Set up recurring donations
- `createRecurringDonationCancellation`: Cancel recurring donations
- `createMilestoneEscrow`: Create escrow for milestone-based funding
- `releaseMilestoneFunds`: Release funds from escrow upon milestone completion
- `submitTransaction`: Submit transactions to the Stellar network

### TransactionService.js

The Node.js service implements business logic for donation processing:

```javascript
// Example: Processing a donation
const result = await transactionService.processDonation({
  donorId: "user123",
  campaignId: "campaign456",
  amount: "50",
  sourceSecret: "SXXXXXXXXXXXXXXXXXXXXX",
  isAnonymous: false,
  message: "Keep up the good work!",
  recurring: false
});
```

Key methods:
- `processDonation`: Process one-time or recurring donations
- `processRecurringDonations`: Batch process due recurring donations
- `cancelRecurringDonation`: Cancel a recurring donation
- `releaseMilestoneFunding`: Release funds for a completed milestone
- `getUserTransactionHistory`: Get transaction history for a user
- `getCampaignTransactionHistory`: Get transaction history for a campaign

### TransactionProcessor.php

The PHP implementation mirrors the Node.js service for platforms using PHP:

```php
// Example: Processing a donation
$result = $transactionProcessor->processDonation([
  'donorId' => 'user123',
  'campaignId' => 'campaign456',
  'amount' => '50',
  'sourceSecret' => 'SXXXXXXXXXXXXXXXXXXXXX',
  'isAnonymous' => false,
  'message' => 'Keep up the good work!',
  'recurring' => false
]);
```

Key methods match those in the Node.js implementation.

## API Reference

### StellarTransactionBuilder.js

#### Constructor

```javascript
const builder = new StellarTransactionBuilder({
  useTestnet: true, // Set to false for mainnet
  horizonUrl: "https://horizon-testnet.stellar.org", // Optional custom URL
  baseFee: 100, // Optional custom fee (in stroops)
  timeout: 30, // Optional timeout in seconds
  enableLogging: false // Enable detailed logging
});
```

#### Methods

##### `loadAccount(publicKey)`

Load a Stellar account for use as a transaction source.

Parameters:
- `publicKey` (string): Public key of the account

Returns: Promise resolving to an account object

##### `createPayment(params)`

Create a simple payment transaction.

Parameters:
- `params` (object):
  - `sourceSecret` (string): Secret key of the source account
  - `destinationAddress` (string): Destination account address
  - `amount` (string): Amount to send
  - `asset` (StellarSdk.Asset): Asset to send (default: XLM)
  - `memo` (string|object): Optional memo

Returns: Promise resolving to a transaction object

##### `createDonation(params)`

Create a donation transaction with campaign-specific metadata.

Parameters:
- `params` (object):
  - `sourceSecret` (string): Secret key of the source account
  - `campaignAddress` (string): Campaign's Stellar address
  - `amount` (string): Donation amount
  - `campaignId` (string): Campaign identifier
  - `donorId` (string): Optional donor identifier
  - `isAnonymous` (boolean): Whether the donation is anonymous
  - `asset` (StellarSdk.Asset): Asset to send (default: XLM)
  - `message` (string): Optional message

Returns: Promise resolving to a transaction object

##### `createRecurringDonationSetup(params)`

Create a transaction to set up a recurring donation.

Parameters:
- `params` (object):
  - `sourceSecret` (string): Secret key of the source account
  - `campaignAddress` (string): Campaign's Stellar address
  - `amount` (string): Donation amount
  - `campaignId` (string): Campaign identifier
  - `donorId` (string): Donor identifier
  - `frequency` (string): Payment frequency ("weekly", "monthly", "quarterly", "annually")
  - `startDate` (Date): Start date for recurring payments
  - `asset` (StellarSdk.Asset): Asset to send (default: XLM)

Returns: Promise resolving to an object with transaction and recurring metadata

##### `createRecurringDonationCancellation(params)`

Create a transaction to cancel a recurring donation.

Parameters:
- `params` (object):
  - `sourceSecret` (string): Secret key of the source account
  - `campaignAddress` (string): Campaign's Stellar address
  - `campaignId` (string): Campaign identifier
  - `donorId` (string): Donor identifier

Returns: Promise resolving to a transaction object

##### `createMilestoneEscrow(params)`

Create an escrow account for milestone-based funding.

Parameters:
- `params` (object):
  - `sourceSecret` (string): Secret key of the source account
  - `campaignId` (string): Campaign identifier
  - `milestones` (array): Array of milestone objects
  - `initialFunding` (string): Initial funding amount for escrow

Returns: Promise resolving to an escrow setup result

##### `releaseMilestoneFunds(params)`

Release funds from a milestone escrow.

Parameters:
- `params` (object):
  - `escrowSecret` (string): Secret key of the escrow account
  - `destinationAddress` (string): Destination address (campaign)
  - `amount` (string): Amount to release
  - `milestoneId` (string): Milestone identifier
  - `campaignId` (string): Campaign identifier

Returns: Promise resolving to a transaction result

##### `submitTransaction(transaction)`

Submit a transaction to the Stellar network.

Parameters:
- `transaction` (object): Signed transaction object

Returns: Promise resolving to a transaction result

### TransactionService.js

#### Constructor

```javascript
const service = new TransactionService(dbClient, {
  useTestnet: true, // Set to false for mainnet
  enableLogging: false, // Enable detailed logging
  defaultAsset: 'XLM', // Default asset for transactions
  maxRetries: 3, // Maximum retry attempts
  retryDelay: 2000 // Delay between retries in ms
});
```

#### Methods

##### `processDonation(params)`

Process a donation to a campaign.

Parameters:
- `params` (object):
  - `donorId` (string): Donor identifier
  - `campaignId` (string): Campaign identifier
  - `amount` (string): Donation amount
  - `sourceSecret` (string): Donor's private key
  - `isAnonymous` (boolean): Whether the donation is anonymous
  - `message` (string): Optional message
  - `recurring` (boolean): Whether this is a recurring donation
  - `recurringFrequency` (string): Frequency for recurring donations

Returns: Promise resolving to a donation result

##### `releaseMilestoneFunding(params)`

Process milestone funding release.

Parameters:
- `params` (object):
  - `campaignId` (string): Campaign identifier
  - `milestoneId` (string): Milestone identifier
  - `authorizedBy` (string): User authorizing the release
  - `amount` (string): Optional amount to release

Returns: Promise resolving to a release result

##### `processRecurringDonations()`

Process recurring donations that are due.

Returns: Promise resolving to processing results

##### `cancelRecurringDonation(params)`

Cancel a recurring donation.

Parameters:
- `params` (object):
  - `donorId` (string): Donor identifier
  - `campaignId` (string): Campaign identifier
  - `userId` (string): User requesting cancellation

Returns: Promise resolving to a cancellation result

##### `getUserTransactionHistory(userId, options)`

Get transaction history for a user.

Parameters:
- `userId` (string): User identifier
- `options` (object): Pagination and filter options

Returns: Promise resolving to transaction history

##### `getCampaignTransactionHistory(campaignId, options)`

Get transaction history for a campaign.

Parameters:
- `campaignId` (string): Campaign identifier
- `options` (object): Pagination and filter options

Returns: Promise resolving to transaction history

### TransactionProcessor.php

The PHP implementation mirrors the Node.js service with equivalent methods and parameters.

## Error Handling

The transaction system implements comprehensive error handling:

1. **Validation Errors**: Returned when required parameters are missing or invalid
2. **Authorization Errors**: Returned when a user lacks permission for an operation
3. **Blockchain Errors**: Captured and translated into user-friendly messages
4. **Database Errors**: Logged and handled gracefully

Example error response:

```json
{
  "success": false,
  "error": "Failed to process donation: Insufficient funds in source account"
}
```

## Security Considerations

### Private Key Management

The system requires donor private keys for signing transactions. Consider implementing:

1. **Encrypted Storage**: Store private keys encrypted in the database
2. **Key Derivation**: Generate keys from user credentials
3. **Hardware Security Modules**: For production environments

### Authorization

All sensitive operations (milestone releases, recurring donation cancellations) include authorization checks:

```javascript
// Example: Authorization check
if (!isCreator && !isAdmin) {
  throw new Error(`User not authorized to release milestone funds: ${authorizedBy}`);
}
```

### Transaction Validation

All transactions are validated before submission:

1. **Parameter Validation**: Required parameters are checked
2. **Business Rules**: Business logic constraints are enforced
3. **Blockchain Validation**: The Stellar network validates transaction structure

## Examples

### Process a One-Time Donation

```javascript
// Using TransactionService.js
const result = await transactionService.processDonation({
  donorId: "user123",
  campaignId: "campaign456",
  amount: "50",
  sourceSecret: "SXXXXXXXXXXXXXXXXXXXXX",
  isAnonymous: false,
  message: "Supporting your mission!",
  recurring: false
});

// Check result
if (result.success) {
  console.log(`Donation processed successfully: ${result.transactionRecord._id}`);
} else {
  console.error(`Failed to process donation: ${result.error}`);
}
```

### Set Up a Recurring Donation

```javascript
// Using TransactionService.js
const result = await transactionService.processDonation({
  donorId: "user123",
  campaignId: "campaign456",
  amount: "25",
  sourceSecret: "SXXXXXXXXXXXXXXXXXXXXX",
  isAnonymous: false,
  recurring: true,
  recurringFrequency: "monthly"
});

// Check result
if (result.success) {
  console.log(`Recurring donation set up successfully: ${result.transactionRecord._id}`);
  console.log(`Next payment: ${result.transactionRecord.recurringDetails.nextProcessing}`);
} else {
  console.error(`Failed to set up recurring donation: ${result.error}`);
}
```

### Create a Milestone Escrow

```javascript
// Using StellarTransactionBuilder.js
const escrowResult = await stellarBuilder.createMilestoneEscrow({
  sourceSecret: "SXXXXXXXXXXXXXXXXXXXXX",
  campaignId: "campaign456",
  milestones: [
    {
      id: "milestone1",
      title: "Phase 1: Planning",
      amount: "500",
      releaseDays: 30,
      conditions: ["Complete project plan", "Assemble team"]
    },
    {
      id: "milestone2",
      title: "Phase 2: Implementation",
      amount: "1500",
      releaseDays: 60,
      conditions: ["Develop prototype", "User testing"]
    }
  ],
  initialFunding: "2000"
});

// Store escrow details
await db.collection('escrows').insertOne({
  campaignId: "campaign456",
  escrowAccountId: escrowResult.escrowAccountId,
  escrowSecretKey: escrowResult.escrowSecretKey, // Store securely!
  milestones: escrowResult.milestones,
  transactionHash: escrowResult.transactionHash,
  created: new Date()
});
```

### Release Milestone Funds

```javascript
// Using TransactionService.js
const releaseResult = await transactionService.releaseMilestoneFunding({
  campaignId: "campaign456",
  milestoneId: "milestone1",
  authorizedBy: "admin789",
  amount: "500"
});

// Check result
if (releaseResult.success) {
  console.log(`Milestone funds released: ${releaseResult.transactionHash}`);
} else {
  console.error(`Failed to release funds: ${releaseResult.error}`);
}
```

### Process Due Recurring Donations

```javascript
// Using TransactionService.js (typically run as a scheduled job)
const processResult = await transactionService.processRecurringDonations();

console.log(`Processed ${processResult.results.processed} recurring donations`);
console.log(`Successful: ${processResult.results.successful}`);
console.log(`Failed: ${processResult.results.failed}`);
```

### Cancel a Recurring Donation

```javascript
// Using TransactionService.js
const cancellationResult = await transactionService.cancelRecurringDonation({
  donorId: "user123",
  campaignId: "campaign456",
  userId: "user123", // Must be the donor or an admin
  sourceSecret: "SXXXXXXXXXXXXXXXXXXXXX"
});

// Check result
if (cancellationResult.success) {
  console.log(`Recurring donation cancelled: ${cancellationResult.transactionHash}`);
} else {
  console.error(`Failed to cancel donation: ${cancellationResult.error}`);
}
```
