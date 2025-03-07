# The Give Hub Transaction System: Best Practices

This document outlines best practices for working with The Give Hub transaction system, focusing on security, performance optimization, error handling, and business operations.

## Table of Contents

- [Security Best Practices](#security-best-practices)
- [Performance Optimization](#performance-optimization)
- [Error Handling and Recovery](#error-handling-and-recovery)
- [Business Operations](#business-operations)
- [Regulatory Compliance](#regulatory-compliance)
- [Future Enhancements](#future-enhancements)

## Security Best Practices

### Private Key Management

The most sensitive aspect of the system is the management of Stellar private keys. Follow these practices:

1. **Never store unencrypted private keys**:
   - Always encrypt private keys before storing in database or client storage
   - Use strong encryption algorithms (AES-256-GCM)
   - Derive encryption keys from user passwords using PBKDF2 with high iteration counts

2. **Key segmentation**:
   - Use different keys for different purposes (donations, escrow, administration)
   - Implement key rotation procedures for campaign accounts

3. **Secure key transmission**:
   - Never transmit private keys in URL parameters
   - Use HTTPS for all API communication
   - Consider implementing signing-only operations where possible

### Example: Secure Key Storage

```javascript
// Secure key storage pattern
async function storeUserKey(userId, privateKey, password) {
  // Create a strong encryption key from the password
  const encryptionKey = await deriveEncryptionKey(password);
  
  // Encrypt the private key
  const encryptedData = await encryptPrivateKey(privateKey, encryptionKey);
  
  // Store only encrypted data
  await db.collection('wallets').insertOne({
    userId,
    encryptedKey: encryptedData.encryptedKey,
    salt: encryptedData.salt,
    iv: encryptedData.iv,
    // Store metadata about encryption method for future compatibility
    encryptionMethod: 'aes-256-gcm',
    created: new Date(),
    lastUsed: new Date()
  });
}

// Private key is never stored in memory longer than necessary
async function signTransaction(transaction, userId, password) {
  // Retrieve encrypted key
  const wallet = await db.collection('wallets').findOne({ userId });
  
  // Derive encryption key from password
  const encryptionKey = await deriveEncryptionKey(password, wallet.salt);
  
  // Decrypt the private key
  const privateKey = await decryptPrivateKey(
    wallet.encryptedKey, 
    encryptionKey,
    wallet.iv
  );
  
  // Sign transaction
  const keypair = StellarSdk.Keypair.fromSecret(privateKey);
  transaction.sign(keypair);
  
  // Clear private key from memory
  privateKey = null;
  
  // Update last used timestamp
  await db.collection('wallets').updateOne(
    { userId },
    { $set: { lastUsed: new Date() }}
  );
  
  return transaction;
}
```

### Access Control

1. **Principle of least privilege**:
   - Each user role should have minimal permissions necessary
   - Admins should have separate accounts for daily vs. administrative tasks

2. **Strong authentication**:
   - Implement multi-factor authentication for administrative functions
   - Require additional verification for high-value transactions

3. **Audit logging**:
   - Log all access to sensitive functions
   - Include IP address, timestamp, user ID, and action details
   - Store logs securely and maintain for an appropriate duration

## Performance Optimization

### Database Optimization

1. **Indexes**: Create appropriate indexes for frequently queried fields:

```javascript
// Create indexes for common queries
db.donations.createIndex({ userId: 1, created: -1 }); // User transaction history
db.donations.createIndex({ campaignId: 1, created: -1 }); // Campaign transaction history
db.donations.createIndex({ 'transaction.txHash': 1 }); // Transaction lookup by hash
db.donations.createIndex({ 
  'recurringDetails.status': 1, 
  'recurringDetails.nextProcessing': 1 
}); // Recurring donation processing
```

2. **Pagination**: Always implement pagination for transaction lists:

```javascript
// Efficient pagination pattern
function getTransactionHistory(userId, page = 1, limit = 10) {
  const skip = (page - 1) * limit;
  
  return db.collection('donations')
    .find({ userId })
    .sort({ created: -1 })
    .skip(skip)
    .limit(limit)
    .toArray();
}
```

3. **Projection**: Only retrieve fields that are needed:

```javascript
// Use projection to limit fields
db.collection('donations').find(
  { campaignId },
  { 
    projection: { 
      amount: 1, 
      status: 1, 
      created: 1, 
      userId: 1,
      'transaction.txHash': 1 
    } 
  }
);
```

### Blockchain Optimizations

1. **Batch processing**: For recurring donations, batch similar operations where possible:

```javascript
// Process recurring donations in batches
async function processRecurringDonationsBatch() {
  // Get due donations, limit batch size
  const dueDonations = await getDueDonations(100);
  
  // Group by destination campaign
  const campaignGroups = groupBy(dueDonations, 'campaignId');
  
  // Process each campaign group
  for (const [campaignId, donations] of Object.entries(campaignGroups)) {
    // Process in smaller batches
    const batches = chunk(donations, 10);
    for (const batch of batches) {
      await Promise.all(batch.map(processDonation));
    }
    
    // Add delay between batches
    await sleep(1000);
  }
}
```

2. **Horizon API rate limits**: Be mindful of Stellar Horizon API rate limits:

```javascript
// Implement rate limiting for Horizon API calls
class HorizonRateLimiter {
  constructor(maxRequestsPerSecond = 20) {
    this.queue = [];
    this.processing = false;
    this.maxRequestsPerSecond = maxRequestsPerSecond;
    this.requestTimes = [];
  }
  
  async submit(fn) {
    return new Promise((resolve, reject) => {
      this.queue.push({ fn, resolve, reject });
      if (!this.processing) {
        this.processQueue();
      }
    });
  }
  
  async processQueue() {
    if (this.queue.length === 0) {
      this.processing = false;
      return;
    }
    
    this.processing = true;
    
    // Check rate limit
    const now = Date.now();
    this.requestTimes = this.requestTimes.filter(time => now - time < 1000);
    
    if (this.requestTimes.length >= this.maxRequestsPerSecond) {
      // Wait until we can make another request
      const waitTime = 1000 - (now - this.requestTimes[0]);
      await new Promise(resolve => setTimeout(resolve, waitTime));
      return this.processQueue();
    }
    
    // Process next item
    const { fn, resolve, reject } = this.queue.shift();
    this.requestTimes.push(Date.now());
    
    try {
      const result = await fn();
      resolve(result);
    } catch (error) {
      reject(error);
    }
    
    // Process next item
    setTimeout(() => this.processQueue(), 1000 / this.maxRequestsPerSecond);
  }
}

// Usage
const horizonRateLimiter = new HorizonRateLimiter();
const result = await horizonRateLimiter.submit(() => server.transactions().transaction(txHash).call());
```

3. **Fee strategy**: Adjust transaction fees based on network conditions:

```javascript
// Dynamic fee strategy
async function getDynamicFee() {
  try {
    // Get fee stats from Horizon
    const feeStats = await server.feeStats();
    
    // Use the p70 fee for faster confirmation
    const recommendedFee = feeStats.fee_charged.p70;
    
    // Add 20% buffer
    return Math.ceil(recommendedFee * 1.2);
  } catch (error) {
    // Fallback to base fee
    console.error('Error getting fee stats:', error);
    return StellarSdk.BASE_FEE;
  }
}
```
## Error Handling and Recovery (continued)

### Transaction Error Recovery

1. **Classify errors** into categories:
   - Temporary failures (network issues, rate limits)
   - Permanent failures (invalid account, insufficient funds)
   - Unknown errors

2. **Implement retry strategies**:

```javascript
// Retry strategy with exponential backoff
async function submitTransactionWithRetry(transaction, maxRetries = 3) {
  let lastError;
  
  for (let attempt = 1; attempt <= maxRetries; attempt++) {
    try {
      const result = await server.submitTransaction(transaction);
      return result;
    } catch (error) {
      lastError = error;
      
      // Check if error is recoverable
      if (!isRecoverableError(error)) {
        throw error;
      }
      
      console.log(`Transaction submission attempt ${attempt} failed: ${error.message}`);
      
      if (attempt < maxRetries) {
        // Exponential backoff with jitter
        const delay = Math.min(1000 * Math.pow(2, attempt) + Math.random() * 1000, 30000);
        await new Promise(resolve => setTimeout(resolve, delay));
      }
    }
  }
  
  throw new Error(`Transaction submission failed after ${maxRetries} attempts: ${lastError.message}`);
}

// Helper to determine if an error is recoverable
function isRecoverableError(error) {
  // Network errors are recoverable
  if (error.response === undefined) {
    return true;
  }
  
  // Check Stellar error codes
  if (error.response?.data?.extras?.result_codes) {
    const codes = error.response.data.extras.result_codes;
    
    // tx_bad_seq is recoverable (just reload account sequence number)
    if (codes.transaction === 'tx_bad_seq') {
      return true;
    }
    
    // timeout error is recoverable
    if (codes.transaction === 'tx_too_late') {
      return true;
    }
  }
  
  return false;
}
```

3. **Transaction status verification**:

```javascript
// Verify transaction status
async function verifyTransactionStatus(txHash) {
  try {
    const tx = await server.transactions().transaction(txHash).call();
    return {
      found: true,
      successful: tx.successful,
      ledger: tx.ledger,
      timestamp: tx.created_at
    };
  } catch (error) {
    if (error.response?.status === 404) {
      return { found: false };
    }
    throw error;
  }
}

// Reconcile database with blockchain state
async function reconcileTransaction(dbTransaction) {
  const txHash = dbTransaction.transaction.txHash;
  
  // No hash means transaction wasn't submitted
  if (!txHash) {
    return {
      needsUpdate: false,
      reason: 'no_hash'
    };
  }
  
  // Check status on blockchain
  const status = await verifyTransactionStatus(txHash);
  
  // Transaction not found on blockchain
  if (!status.found) {
    // If our DB shows completed, this is a problem
    if (dbTransaction.status === 'completed') {
      return {
        needsUpdate: true,
        reason: 'not_found_on_chain',
        newStatus: 'failed'
      };
    }
    return {
      needsUpdate: false,
      reason: 'not_found_but_not_completed'
    };
  }
  
  // Transaction found on blockchain
  if (status.successful && dbTransaction.status !== 'completed') {
    return {
      needsUpdate: true,
      reason: 'successful_on_chain',
      newStatus: 'completed'
    };
  }
  
  if (!status.successful && dbTransaction.status === 'completed') {
    return {
      needsUpdate: true,
      reason: 'failed_on_chain',
      newStatus: 'failed'
    };
  }
  
  return {
    needsUpdate: false,
    reason: 'status_consistent'
  };
}
```

### Error Notification System

Implement a robust notification system for critical errors:

```javascript
// Notification system for critical errors
class ErrorNotifier {
  constructor(config = {}) {
    this.emailService = config.emailService;
    this.smsService = config.smsService;
    this.slackWebhook = config.slackWebhook;
    this.adminEmails = config.adminEmails || [];
    this.adminPhones = config.adminPhones || [];
  }
  
  async notifyCritical(error, context = {}) {
    const timestamp = new Date().toISOString();
    const message = `[CRITICAL] ${error.message}`;
    
    // Log the error
    console.error(`${timestamp} - ${message}`, {
      stack: error.stack,
      context
    });
    
    // Prepare notification content
    const content = `
      Time: ${timestamp}
      Error: ${error.message}
      Stack: ${error.stack}
      Context: ${JSON.stringify(context, null, 2)}
    `;
    
    // Send email notifications
    if (this.emailService && this.adminEmails.length > 0) {
      try {
        await this.emailService.sendMail({
          to: this.adminEmails,
          subject: message,
          text: content
        });
      } catch (e) {
        console.error('Failed to send email notification:', e);
      }
    }
    
    // Send SMS for highest priority errors
    if (this.smsService && this.adminPhones.length > 0 && context.priority === 'highest') {
      try {
        await this.smsService.sendSms({
          to: this.adminPhones,
          text: `${message} - Please check email for details.`
        });
      } catch (e) {
        console.error('Failed to send SMS notification:', e);
      }
    }
    
    // Send Slack notification
    if (this.slackWebhook) {
      try {
        await fetch(this.slackWebhook, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            text: message,
            blocks: [
              {
                type: 'section',
                text: {
                  type: 'mrkdwn',
                  text: `*Critical Error Alert*\n${message}`
                }
              },
              {
                type: 'section',
                text: {
                  type: 'mrkdwn',
                  text: `*Stack:*\n\`\`\`${error.stack}\`\`\``
                }
              },
              {
                type: 'section',
                text: {
                  type: 'mrkdwn',
                  text: `*Context:*\n\`\`\`${JSON.stringify(context, null, 2)}\`\`\``
                }
              }
            ]
          })
        });
      } catch (e) {
        console.error('Failed to send Slack notification:', e);
      }
    }
  }
}
```

## Business Operations

### Handling Refunds

Implement a refund process for erroneous or disputed donations:

```javascript
// Process a refund
async function processRefund(donationId, adminId, reason) {
  // Get the donation record
  const donation = await db.collection('donations').findOne({
    _id: new ObjectId(donationId)
  });
  
  if (!donation) {
    throw new Error(`Donation not found: ${donationId}`);
  }
  
  // Verify donation can be refunded
  if (donation.status !== 'completed') {
    throw new Error(`Cannot refund donation with status: ${donation.status}`);
  }
  
  // Check for existing refund
  const existingRefund = await db.collection('refunds').findOne({
    donationId: new ObjectId(donationId)
  });
  
  if (existingRefund) {
    throw new Error(`Refund already processed for donation: ${donationId}`);
  }
  
  // Get campaign details
  const campaign = await db.collection('campaigns').findOne({
    _id: donation.campaignId
  });
  
  if (!campaign) {
    throw new Error(`Campaign not found for donation: ${donationId}`);
  }
  
  // Get campaign wallet for refund source
  const campaignWallet = await db.collection('wallets').findOne({
    campaignId: campaign._id
  });
  
  if (!campaignWallet) {
    throw new Error(`Campaign wallet not found: ${campaign._id}`);
  }
  
  // Create refund transaction
  const stellarBuilder = new StellarTransactionBuilder({
    useTestnet: process.env.STELLAR_NETWORK === 'testnet'
  });
  
  // Get donor account address (where to send refund)
  const donorWallet = await db.collection('wallets').findOne({
    userId: donation.userId
  });
  
  if (!donorWallet) {
    throw new Error(`Donor wallet not found: ${donation.userId}`);
  }
  
  // Create and submit refund transaction
  const transaction = await stellarBuilder.createPayment({
    sourceSecret: campaignWallet.secretKey,
    destinationAddress: donorWallet.publicKey,
    amount: donation.amount.value.toString(),
    memo: `refund:${donationId}`
  });
  
  const result = await stellarBuilder.submitTransaction(transaction);
  
  if (!result.success) {
    throw new Error(`Failed to process refund: ${result.error}`);
  }
  
  // Create refund record
  const refund = {
    donationId: new ObjectId(donationId),
    campaignId: donation.campaignId,
    userId: donation.userId,
    amount: donation.amount,
    reason,
    processedBy: new ObjectId(adminId),
    transaction: {
      txHash: result.result.hash,
      stellarAddress: donorWallet.publicKey,
      status: 'completed',
      timestamp: new Date()
    },
    created: new Date()
  };
  
  await db.collection('refunds').insertOne(refund);
  
  // Update donation status
  await db.collection('donations').updateOne(
    { _id: new ObjectId(donationId) },
    { 
      $set: { 
        status: 'refunded',
        refundedAt: new Date(),
        refundedBy: new ObjectId(adminId),
        refundReason: reason
      }
    }
  );
  
  // Update campaign funding stats
  await db.collection('campaigns').updateOne(
    { _id: donation.campaignId },
    { $inc: { 'funding.raisedAmount': -donation.amount.value } }
  );
  
  // Create notifications
  await createRefundNotifications(donation, refund);
  
  return {
    success: true,
    refundId: refund._id,
    transactionHash: result.result.hash
  };
}
```

### Milestone Management

Implement a robust milestone management system:

```javascript
// Activate milestone based on criteria
async function evaluateMilestoneActivation(campaignId) {
  const campaign = await db.collection('campaigns').findOne({
    _id: new ObjectId(campaignId)
  });
  
  if (!campaign || !campaign.timeline || !campaign.timeline.milestones) {
    return { success: false, message: 'Campaign or milestones not found' };
  }
  
  const updatedMilestones = [];
  
  for (const milestone of campaign.timeline.milestones) {
    // Skip if not pending
    if (milestone.status !== 'pending') {
      continue;
    }
    
    // Check if funding target reached
    if (milestone.fundingTarget && campaign.funding.raisedAmount >= milestone.fundingTarget) {
      // Update milestone status
      await db.collection('campaigns').updateOne(
        { 
          _id: campaign._id, 
          'timeline.milestones._id': milestone._id 
        },
        { 
          $set: { 
            'timeline.milestones.$.status': 'active',
            'timeline.milestones.$.activatedDate': new Date(),
            'timeline.milestones.$.activationMethod': 'funding_target'
          }
        }
      );
      
      updatedMilestones.push({
        id: milestone._id,
        title: milestone.title,
        activation: 'funding_target'
      });
      
      // Create notification
      await createMilestoneNotification(
        campaign._id,
        milestone._id,
        'milestone_activated'
      );
    }
    
    // Check if scheduled date reached
    if (milestone.scheduledDate && new Date() >= new Date(milestone.scheduledDate)) {
      // Update milestone status
      await db.collection('campaigns').updateOne(
        { 
          _id: campaign._id, 
          'timeline.milestones._id': milestone._id 
        },
        { 
          $set: { 
            'timeline.milestones.$.status': 'active',
            'timeline.milestones.$.activatedDate': new Date(),
            'timeline.milestones.$.activationMethod': 'scheduled_date'
          }
        }
      );
      
      updatedMilestones.push({
        id: milestone._id,
        title: milestone.title,
        activation: 'scheduled_date'
      });
      
      // Create notification
      await createMilestoneNotification(
        campaign._id,
        milestone._id,
        'milestone_activated'
      );
    }
  }
  
  return {
    success: true,
    campaign: campaign._id,
    activatedMilestones: updatedMilestones
  };
}
```

## Regulatory Compliance

### Transaction Record Keeping

Maintain comprehensive records for regulatory compliance:

```javascript
// Create a transaction audit record
async function createTransactionAuditRecord(transaction, metadata = {}) {
  const auditRecord = {
    transactionId: transaction._id,
    transactionType: transaction.type,
    userId: transaction.userId,
    campaignId: transaction.campaignId,
    amount: transaction.amount,
    status: transaction.status,
    stellarTxHash: transaction.transaction?.txHash,
    ipAddress: metadata.ipAddress,
    userAgent: metadata.userAgent,
    geoLocation: metadata.geoLocation,
    deviceId: metadata.deviceId,
    timestamp: new Date(),
    eventType: metadata.eventType || 'transaction_created',
    changes: metadata.changes || null
  };
  
  await db.collection('transactionAudit').insertOne(auditRecord);
  
  return auditRecord._id;
}
```

### KYC/AML Integration

For high-value transactions, implement Know Your Customer (KYC) and Anti-Money Laundering (AML) checks:

```javascript
// Check if KYC is required for transaction
function isKycRequired(amount, userRiskScore) {
  // Example thresholds
  const KYC_THRESHOLD = 1000; // $1000 or equivalent
  const HIGH_RISK_KYC_THRESHOLD = 500; // $500 for high-risk users
  
  if (userRiskScore === 'high') {
    return amount >= HIGH_RISK_KYC_THRESHOLD;
  }
  
  return amount >= KYC_THRESHOLD;
}

// Validate transaction against AML rules
async function validateAmlRules(transaction, user) {
  const rules = [
    // Check transaction amount against user's average
    async () => {
      const avgAmount = await calculateUserAverageTransactionAmount(user._id);
      const threshold = avgAmount * 5; // 5x average is suspicious
      
      if (transaction.amount.value > threshold && threshold > 100) {
        return {
          passed: false,
          reason: 'amount_exceeds_user_average',
          risk: 'medium'
        };
      }
      return { passed: true };
    },
    
    // Check transaction frequency
    async () => {
      const last24Hours = await countUserTransactionsLast24Hours(user._id);
      if (last24Hours > 10) { // More than 10 transactions in 24 hours
        return {
          passed: false,
          reason: 'high_transaction_frequency',
          risk: 'medium'
        };
      }
      return { passed: true };
    },
    
    // Check for high-risk countries
    async () => {
      if (user.countryCode && HIGH_RISK_COUNTRIES.includes(user.countryCode)) {
        return {
          passed: false,
          reason: 'high_risk_jurisdiction',
          risk: 'high'
        };
      }
      return { passed: true };
    }
  ];
  
  // Run all rules
  const results = await Promise.all(rules.map(rule => rule()));
  
  // Check if any rules failed
  const failedRules = results.filter(result => !result.passed);
  
  if (failedRules.length > 0) {
    return {
      passed: false,
      failedRules
    };
  }
  
  return {
    passed: true
  };
}
```

## Future Enhancements

### Multi-Asset Support

Extend the system to support multiple Stellar assets:

```javascript
// Create multi-asset donation
async function createMultiAssetDonation(params) {
  const { 
    sourceSecret, 
    campaignAddress,
    assets = [] 
  } = params;
  
  if (assets.length === 0) {
    throw new Error('At least one asset must be specified');
  }
  
  // Create keypair
  const sourceKeypair = StellarSdk.Keypair.fromSecret(sourceSecret);
  const sourceAccount = await server.loadAccount(sourceKeypair.publicKey());
  
  // Start transaction builder
  let transaction = new StellarSdk.TransactionBuilder(sourceAccount, {
    fee: await getDynamicFee(),
    networkPassphrase: networkPassphrase
  });
  
  // Add payment operations for each asset
  for (const asset of assets) {
    const stellarAsset = asset.code === 'XLM' 
      ? StellarSdk.Asset.native()
      : new StellarSdk.Asset(asset.code, asset.issuer);
    
    transaction = transaction.addOperation(
      StellarSdk.Operation.payment({
        destination: campaignAddress,
        asset: stellarAsset,
        amount: asset.amount.toString()
      })
    );
  }
  
  // Add memo
  if (params.memo) {
    transaction = transaction.addMemo(StellarSdk.Memo.text(params.memo));
  }
  
  // Build and sign
  transaction = transaction.setTimeout(30).build();
  transaction.sign(sourceKeypair);
  
  return transaction;
}
```

### Cross-Chain Integration

For future expansion, consider integrating with other blockchains:

1. **Stellar-Ethereum Bridge**:
   - Use standardized cross-chain bridges
   - Implement atomic swaps where possible
   - Consider Layer 2 solutions for Ethereum integration

2. **Stablecoin Integration**:
   - Add support for popular stablecoins on Stellar (USDC)
   - Implement currency conversion services

### Smart Contract Integration

When Soroban smart contracts become widely available on Stellar:

```javascript
// Example smart contract interaction for milestone funding
async function createMilestoneContract(params) {
  const { 
    escrowSecret,
    campaignAddress,
    milestones 
  } = params;
  
  // Create contract
  const escrowKeypair = StellarSdk.Keypair.fromSecret(escrowSecret);
  const escrowAccount = await server.loadAccount(escrowKeypair.publicKey());
  
  // Deploy milestone contract WASM
  const contractWasm = await fs.readFile('./contracts/milestone_escrow.wasm');
  
  const transaction = new StellarSdk.TransactionBuilder(escrowAccount, {
    fee: await getDynamicFee(),
    networkPassphrase: networkPassphrase
  })
  .addOperation(StellarSdk.Operation.invokeHostFunction({
    function: StellarSdk.xdr.HostFunction.deployContract(contractWasm),
    auth: []
  }))
  .setTimeout(30)
  .build();
  
  transaction.sign(escrowKeypair);
  const result = await server.submitTransaction(transaction);
  
  // Get contract ID from result
  const contractId = getContractIdFromDeployment(result);
  
  // Initialize contract with milestones
  const initTransaction = new StellarSdk.TransactionBuilder(escrowAccount, {
    fee: await getDynamicFee(),
    networkPassphrase: networkPassphrase
  })
  .addOperation(StellarSdk.Operation.invokeHostFunction({
    function: StellarSdk.xdr.HostFunction.invokeContract({
      contractId: contractId,
      functionName: "initialize",
      args: [
        StellarSdk.xdr.ScVal.string(campaignAddress),
        StellarSdk.xdr.ScVal.array(milestones.map(m => 
          StellarSdk.xdr.ScVal.string(JSON.stringify(m))
        ))
      ]
    }),
    auth: []
  }))
  .setTimeout(30)
  .build();
  
  initTransaction.sign(escrowKeypair);
  await server.submitTransaction(initTransaction);
  
  return {
    contractId,
    escrowAddress: escrowKeypair.publicKey()
  };
}
```

