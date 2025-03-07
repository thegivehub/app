# The Give Hub Transaction System Troubleshooting Guide

This guide provides solutions for common issues that may arise when working with The Give Hub transaction system. It includes diagnostic steps, common error codes, and recovery procedures.

## Table of Contents

- [Common Errors and Solutions](#common-errors-and-solutions)
- [Diagnostic Tools](#diagnostic-tools)
- [Recovery Procedures](#recovery-procedures)
- [Monitoring and Alerting](#monitoring-and-alerting)
- [FAQ](#faq)

## Common Errors and Solutions

### Stellar Transaction Errors

| Error Code | Description | Solution |
|------------|-------------|----------|
| `tx_bad_seq` | Transaction sequence number is incorrect | Reload the source account and recreate the transaction with the updated sequence number |
| `tx_bad_auth` | Transaction has an invalid signature | Verify the correct private key is being used to sign the transaction |
| `tx_insufficient_balance` | Source account has insufficient funds | Ensure the source account has enough XLM (including reserve requirements) |
| `tx_failed` | Transaction failed during execution | Check operation-specific result codes for more details |
| `tx_too_late` | Transaction submitted past its time bounds | Create a new transaction with updated time bounds |
| `tx_fee_bump_inner_failed` | Fee bump transaction's inner transaction failed | Check inner transaction result codes |

### Error: Transaction Failed with "op_underfunded"

**Problem**: When attempting to process a donation, the transaction fails with `op_underfunded` error.

**Diagnosis**:
1. Check the source account balance:
   ```javascript
   async function checkAccountBalance(publicKey) {
     try {
       const account = await server.loadAccount(publicKey);
       const balances = account.balances.map(b => ({
         asset: b.asset_type === 'native' ? 'XLM' : b.asset_code,
         balance: parseFloat(b.balance)
       }));
       return balances;
     } catch (error) {
       console.error(`Error checking account ${publicKey}:`, error);
       throw error;
     }
   }
   ```

2. Verify Stellar reserve requirements:
   - Base reserve: 1 XLM
   - Additional reserve per entry: 0.5 XLM
   - Check total entries: `account.subentry_count`

**Solutions**:
1. Fund the source account with additional XLM
2. Reduce the transaction amount
3. Notify users of insufficient funds with clear instructions to add funds

### Error: Database Inconsistency

**Problem**: Transaction shows as completed in the database but cannot be found on the Stellar network.

**Diagnosis**:
1. Verify transaction hash against Horizon API:
   ```javascript
   async function verifyTransaction(txHash) {
     try {
       const tx = await server.transactions().transaction(txHash).call();
       return {
         exists: true,
         successful: tx.successful
       };
     } catch (error) {
       if (error.response && error.response.status === 404) {
         return { exists: false };
       }
       throw error;
     }
   }
   ```

**Solutions**:
1. Mark transaction as failed in the database
2. Implement a reconciliation job to periodically check database against blockchain:
   ```javascript
   // Run as a scheduled job
   async function reconcileTransactions(lastHours = 24) {
     const cutoffDate = new Date(Date.now() - lastHours * 60 * 60 * 1000);
     
     const transactions = await db.collection('donations').find({
       'transaction.timestamp': { $gte: cutoffDate }
     }).toArray();
     
     const discrepancies = [];
     
     for (const tx of transactions) {
       if (!tx.transaction.txHash) continue;
       
       try {
         const result = await verifyTransaction(tx.transaction.txHash);
         
         if (!result.exists && tx.status === 'completed') {
           discrepancies.push({
             id: tx._id,
             hash: tx.transaction.txHash,
             issue: 'not_found_on_chain',
             dbStatus: tx.status
           });
           
           // Auto fix
           await db.collection('donations').updateOne(
             { _id: tx._id },
             { $set: { status: 'failed', lastReconciled: new Date() } }
           );
         } else if (result.exists && !result.successful && tx.status === 'completed') {
           discrepancies.push({
             id: tx._id,
             hash: tx.transaction.txHash,
             issue: 'failed_on_chain',
             dbStatus: tx.status
           });
           
           // Auto fix
           await db.collection('donations').updateOne(
             { _id: tx._id },
             { $set: { status: 'failed', lastReconciled: new Date() } }
           );
         } else if (result.exists && result.successful && tx.status !== 'completed') {
           discrepancies.push({
             id: tx._id,
             hash: tx.transaction.txHash,
             issue: 'completed_on_chain',
             dbStatus: tx.status
           });
           
           // Auto fix
           await db.collection('donations').updateOne(
             { _id: tx._id },
             { $set: { status: 'completed', lastReconciled: new Date() } }
           );
         }
       } catch (error) {
         console.error(`Error reconciling transaction ${tx._id}:`, error);
         continue;
       }
     }
     
     return {
       processed: transactions.length,
       discrepancies: discrepancies.length,
       details: discrepancies
     };
   }
   ```

### Error: Recurring Donation Processing Failures

**Problem**: Scheduled recurring donations are not being processed.

**Diagnosis**:
1. Check the cron job or scheduler logs:
   ```bash
   # Check system cron logs
   grep process-recurring /var/log/syslog
   
   # Check PM2 logs for Node.js scheduled tasks
   pm2 logs process-recurring
   ```

2. Verify database query for due donations:
   ```javascript
   // Expected query
   db.collection('donors').find({
     'donationType': 'recurring',
     'recurringDetails.status': 'active',
     'recurringDetails.nextProcessing': { $lte: new Date() }
   });
   ```

**Solutions**:
1. Ensure the scheduler is running with correct permissions
2. Fix any database query issues
3. Implement a catch-up mechanism for missed donations:
   ```javascript
   async function processOverdueRecurringDonations() {
     const today = new Date();
     const oneWeekAgo = new Date(today);
     oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);
     
     // Find donations that should have been processed in the last week
     const overdueRecurringDonations = await db.collection('donors').find({
       'donationType': 'recurring',
       'recurringDetails.status': 'active',
       'recurringDetails.nextProcessing': { 
         $gte: oneWeekAgo,
         $lte: today
       }
     }).toArray();
     
     console.log(`Found ${overdueRecurringDonations.length} overdue recurring donations`);
     
     // Process them
     const results = await processRecurringDonationsBatch(overdueRecurringDonations);
     
     return results;
   }
   ```

## Diagnostic Tools

### Transaction Inspector

A diagnostic tool to inspect transaction details:

```javascript
// transaction-inspector.js
const StellarSdk = require('stellar-sdk');
const mongoose = require('mongoose');
require('dotenv').config();

// Connect to database
mongoose.connect(process.env.MONGODB_URI);

// Initialize Stellar server
const server = new StellarSdk.Server(
  process.env.STELLAR_NETWORK === 'testnet' 
    ? 'https://horizon-testnet.stellar.org' 
    : 'https://horizon.stellar.org'
);

// Main inspection function
async function inspectTransaction(txId) {
  try {
    // Get transaction from database
    const tx = await mongoose.model('Donation').findById(txId);
    
    if (!tx) {
      console.error('Transaction not found in database');
      return;
    }
    
    console.log('=============================================');
    console.log('TRANSACTION INSPECTION REPORT');
    console.log('=============================================');
    console.log('ID:', tx._id);
    console.log('Type:', tx.type);
    console.log('Status:', tx.status);
    console.log('Amount:', tx.amount.value, tx.amount.currency);
    console.log('Created:', tx.created);
    
    // Get campaign details
    const campaign = await mongoose.model('Campaign').findById(tx.campaignId);
    console.log('\nCAMPAIGN DETAILS:');
    console.log('ID:', campaign?._id || 'Not found');
    console.log('Title:', campaign?.title || 'N/A');
    console.log('Stellar Address:', campaign?.stellarAddress || 'N/A');
    
    // Get donor details (if not anonymous)
    if (tx.visibility !== 'anonymous') {
      const donor = await mongoose.model('Donor').findById(tx.userId);
      console.log('\nDONOR DETAILS:');
      console.log('ID:', donor?._id || 'Not found');
      console.log('Name:', donor?.name || 'N/A');
      console.log('Email:', donor?.email || 'N/A');
    } else {
      console.log('\nDONOR DETAILS: Anonymous');
    }
    
    // Check blockchain status
    if (tx.transaction?.txHash) {
      console.log('\nBLOCKCHAIN DETAILS:');
      
      try {
        const stellarTx = await server.transactions().transaction(tx.transaction.txHash).call();
        
        console.log('Exists on blockchain: Yes');
        console.log('Successful:', stellarTx.successful);
        console.log('Ledger:', stellarTx.ledger);
        console.log('Created at:', stellarTx.created_at);
        console.log('Source account:', stellarTx.source_account);
        console.log('Fee charged:', stellarTx.fee_charged, 'stroops');
        
        // Get operations
        const operations = await server.operations().forTransaction(tx.transaction.txHash).call();
        
        console.log('\nOPERATIONS:');
        operations.records.forEach((op, index) => {
          console.log(`Operation ${index + 1}:`);
          console.log('  Type:', op.type);
          
          if (op.type === 'payment') {
            console.log('  From:', op.from);
            console.log('  To:', op.to);
            console.log('  Amount:', op.amount, op.asset_type === 'native' ? 'XLM' : op.asset_code);
          } else if (op.type === 'create_account') {
            console.log('  Funder:', op.funder);
            console.log('  Account:', op.account);
            console.log('  Starting balance:', op.starting_balance, 'XLM');
          }
          // Add other operation types as needed
        });
        
        // Check for any transaction errors
        if (!stellarTx.successful) {
          console.log('\nTRANSACTION RESULT:');
          console.log('  Failed on blockchain');
          console.log('  Database status:', tx.status);
          console.log('  INCONSISTENCY DETECTED!');
        }
      } catch (error) {
        console.log('Exists on blockchain: No');
        console.log('Error retrieving transaction:', error.message);
        
        if (tx.status === 'completed') {
          console.log('\nINCONSISTENCY DETECTED: Transaction marked as completed in database but not found on blockchain');
        }
      }
    } else {
      console.log('\nBLOCKCHAIN DETAILS: No transaction hash available');
    }
    
    // Check recurring donation details if applicable
    if (tx.type === 'recurring' && tx.recurringDetails) {
      console.log('\nRECURRING DONATION DETAILS:');
      console.log('Frequency:', tx.recurringDetails.frequency);
      console.log('Status:', tx.recurringDetails.status);
      console.log('Start date:', tx.recurringDetails.startDate);
      console.log('Next processing date:', tx.recurringDetails.nextProcessing);
      
      if (tx.recurringDetails.status === 'cancelled') {
        console.log('Cancelled date:', tx.recurringDetails.cancelledDate);
        console.log('Cancelled by:', tx.recurringDetails.cancelledBy);
      }
    }
    
    // Check milestone details if applicable
    if (tx.type === 'milestone' && tx.milestoneId) {
      console.log('\nMILESTONE DETAILS:');
      
      const milestone = campaign?.timeline?.milestones?.find(m => m._id.toString() === tx.milestoneId.toString());
      
      if (milestone) {
        console.log('Title:', milestone.title);
        console.log('Status:', milestone.status);
        console.log('Funding target:', milestone.fundingTarget);
        
        if (milestone.completedDate) {
          console.log('Completed date:', milestone.completedDate);
        }
      } else {
        console.log('Milestone not found in campaign');
      }
    }
    
    console.log('\n=============================================');
    
  } catch (error) {
    console.error('Error inspecting transaction:', error);
  } finally {
    await mongoose.disconnect();
  }
}

// If run directly from command line
if (require.main === module) {
  const txId = process.argv[2];
  
  if (!txId) {
    console.error('Please provide a transaction ID');
    process.exit(1);
  }
  
  inspectTransaction(txId)
    .then(() => process.exit(0))
    .catch(error => {
      console.error('Fatal error:', error);
      process.exit(1);
    });
}

module.exports = { inspectTransaction };
```

### Wallet Diagnostic Tool

A tool to diagnose issues with Stellar wallets:

```javascript
// wallet-diagnostic.js
const StellarSdk = require('stellar-sdk');
const mongoose = require('mongoose');
require('dotenv').config();

mongoose.connect(process.env.MONGODB_URI);

const server = new StellarSdk.Server(
  process.env.STELLAR_NETWORK === 'testnet' 
    ? 'https://horizon-testnet.stellar.org' 
    : 'https://horizon.stellar.org'
);

async function diagnosticWalletCheck(userId) {
  try {
    // Get user wallet
    const wallet = await mongoose.model('Wallet').findOne({ userId });
    
    if (!wallet) {
      console.error('Wallet not found for user:', userId);
      return;
    }
    
    console.log('=============================================');
    console.log('WALLET DIAGNOSTIC REPORT');
    console.log('=============================================');
    console.log('User ID:', userId);
    console.log('Public Key:', wallet.publicKey);
    
    // Check if account exists on blockchain
    try {
      const account = await server.loadAccount(wallet.publicKey);
      
      console.log('\nACCOUNT EXISTS ON BLOCKCHAIN: Yes');
      
      // Print balances
      console.log('\nBALANCES:');
      account.balances.forEach(balance => {
        if (balance.asset_type === 'native') {
          console.log('XLM:', balance.balance);
        } else {
          console.log(`${balance.asset_code}:`, balance.balance);
        }
      });
      
      // Calculate minimum balance requirement
      const baseReserve = 1; // XLM
      const entryReserve = 0.5; // XLM per entry
      const minBalance = baseReserve + (account.subentry_count * entryReserve);
      
      const xlmBalance = account.balances.find(b => b.asset_type === 'native');
      const availableBalance = xlmBalance ? parseFloat(xlmBalance.balance) - minBalance : 0;
      
      console.log('\nACCOUNT DETAILS:');
      console.log('Sequence number:', account.sequence);
      console.log('Number of entries:', account.subentry_count);
      console.log('Required minimum balance:', minBalance, 'XLM');
      console.log('Available balance (for transactions):', availableBalance, 'XLM');
      
      if (availableBalance < 1) {
        console.log('\nWARNING: Low available balance may cause transaction failures');
      }
      
      // Check recent transactions
      const transactions = await server.transactions()
        .forAccount(wallet.publicKey)
        .limit(5)
        .order('desc')
        .call();
      
      console.log('\nRECENT TRANSACTIONS:');
      for (const tx of transactions.records) {
        console.log('-', tx.hash);
        console.log('  Created:', tx.created_at);
        console.log('  Successful:', tx.successful);
        
        if (tx.memo_type !== 'none') {
          console.log('  Memo:', tx.memo, `(${tx.memo_type})`);
        }
      }
      
      // Check if account is being used as expected
      const dbTransactions = await mongoose.model('Donation')
        .find({ 'userId': userId })
        .sort({ created: -1 })
        .limit(5);
      
      console.log('\nDATABASE TRANSACTIONS:');
      for (const tx of dbTransactions) {
        console.log('-', tx._id);
        console.log('  Amount:', tx.amount.value, tx.amount.currency);
        console.log('  Status:', tx.status);
        console.log('  Created:', tx.created);
        
        if (tx.transaction?.txHash) {
          console.log('  Blockchain hash:', tx.transaction.txHash);
        } else {
          console.log('  No blockchain hash found');
        }
      }
      
    } catch (error) {
      if (error.response && error.response.status === 404) {
        console.log('\nACCOUNT EXISTS ON BLOCKCHAIN: No');
        console.log('This account has not been created on the Stellar network.');
        console.log('It needs to be funded with the minimum balance (1 XLM).');
      } else {
        console.error('\nError checking account:', error.message);
      }
    }
    
    console.log('\n=============================================');
    
  } catch (error) {
    console.error('Error running wallet diagnostic:', error);
  } finally {
    await mongoose.disconnect();
  }
}

// If run directly from command line
if (require.main === module) {
  const userId = process.argv[2];
  
  if (!userId) {
    console.error('Please provide a user ID');
    process.exit(1);
  }
  
  diagnosticWalletCheck(userId)
    .then(() => process.exit(0))
    .catch(error => {
      console.error('Fatal error:', error);
      process.exit(1);
    });
}

module.exports = { diagnosticWalletCheck };
```

### Database Consistency Checker

A tool to check for database consistency issues:

```javascript
// db-consistency-checker.js
const mongoose = require('mongoose');
require('dotenv').config();

mongoose.connect(process.env.MONGODB_URI);

async function checkDatabaseConsistency() {
  try {
    console.log('=============================================');
    console.log('DATABASE CONSISTENCY REPORT');
    console.log('=============================================');
    
    // Check for donations with completed status but no transaction hash
    const inconsistentCompletedDonations = await mongoose.model('Donation').find({
      status: 'completed',
      $or: [
        { 'transaction.txHash': { $exists: false } },
        { 'transaction.txHash': null }
      ]
    });
    
    console.log('\nDONATIONS MARKED COMPLETED WITH NO TRANSACTION HASH:');
    console.log('Count:', inconsistentCompletedDonations.length);
    
    if (inconsistentCompletedDonations.length > 0) {
      console.log('Sample IDs:');
      inconsistentCompletedDonations.slice(0, 5).forEach(d => {
        console.log('-', d._id, '(created:', d.created, ')');
      });
    }
    
    // Check for failed donations with excessive retries
    const failedWithRetries = await mongoose.model('Donation').find({
      status: 'failed',
      retryCount: { $gt: 3 }
    });
    
    console.log('\nFAILED DONATIONS WITH EXCESSIVE RETRIES:');
    console.log('Count:', failedWithRetries.length);
    
    // Check for pending donations older than 1 hour
    const oneHourAgo = new Date();
    oneHourAgo.setHours(oneHourAgo.getHours() - 1);
    
    const stalePendingDonations = await mongoose.model('Donation').find({
      status: 'pending',
      created: { $lt: oneHourAgo }
    });
    
    console.log('\nSTALE PENDING DONATIONS (> 1 HOUR OLD):');
    console.log('Count:', stalePendingDonations.length);
    
    if (stalePendingDonations.length > 0) {
      console.log('Sample IDs:');
      stalePendingDonations.slice(0, 5).forEach(d => {
        console.log('-', d._id, '(created:', d.created, ')');
      });
    }
    
    // Check for campaign funding discrepancies
    const campaigns = await mongoose.model('Campaign').find();
    
    console.log('\nCAMPAIGN FUNDING CONSISTENCY CHECK:');
    let campaignsWithDiscrepancies = 0;
    
    for (const campaign of campaigns) {
      // Calculate actual funding from donations
      const completedDonations = await mongoose.model('Donation').find({
        campaignId: campaign._id,
        status: 'completed'
      });
      
      const actualFunding = completedDonations.reduce((sum, donation) => {
        return sum + donation.amount.value;
      }, 0);
      
      // Check for refunds
      const refunds = await mongoose.model('Refund').find({
        campaignId: campaign._id
      });
      
      const refundAmount = refunds.reduce((sum, refund) => {
        return sum + refund.amount.value;
      }, 0);
      
      // Calculate expected funding
      const expectedFunding = actualFunding - refundAmount;
      
      // Compare with campaign's recorded funding
      const recordedFunding = campaign.funding?.raisedAmount || 0;
      
      // Allow small floating-point differences (0.001 tolerance)
      if (Math.abs(expectedFunding - recordedFunding) > 0.001) {
        campaignsWithDiscrepancies++;
        
        console.log('\nDiscrepancy found for campaign:', campaign._id);
        console.log('Campaign title:', campaign.title);
        console.log('Recorded funding:', recordedFunding);
        console.log('Expected funding:', expectedFunding);
        console.log('Difference:', expectedFunding - recordedFunding);
      }
    }
    
    console.log('\nTotal campaigns with funding discrepancies:', campaignsWithDiscrepancies);
    
    // Check recurring donation next payment dates
    const recurringDonorsWithPastDates = await mongoose.model('Donor').find({
      'donationType': 'recurring',
      'recurringDetails.status': 'active',
      'recurringDetails.nextProcessing': { $lt: new Date() }
    });
    
    console.log('\nRECURRING DONATIONS WITH PAST DUE DATES:');
    console.log('Count:', recurringDonorsWithPastDates.length);
    
    console.log('\n=============================================');
    
  } catch (error) {
    console.error('Error checking database consistency:', error);
  } finally {
    await mongoose.disconnect();
  }
}

// If run directly from command line
if (require.main === module) {
  checkDatabaseConsistency()
    .then(() => process.exit(0))
    .catch(error => {
      console.error('Fatal error:', error);
      process.exit(1);
    });
}

module.exports = { checkDatabaseConsistency };
```

## Recovery Procedures

### Resolving Stale Pending Transactions

Script to resolve transactions stuck in pending state:

```javascript
// resolve-stale-pending.js
const mongoose = require('mongoose');
const StellarSdk = require('stellar-sdk');
require('dotenv').config();

mongoose.connect(process.env.MONGODB_URI);

const server = new StellarSdk.Server(
  process.env.STELLAR_NETWORK === 'testnet' 
    ? 'https://horizon-testnet.stellar.org' 
    : 'https://horizon.stellar.org'
);

async function resolveStalePendingTransactions(hoursOld = 1) {
  try {
    const cutoffDate = new Date();
    cutoffDate.setHours(cutoffDate.getHours() - hoursOld);
    
    console.log(`Finding pending transactions older than ${hoursOld} hours...`);
    
    const stalePendingTxs = await mongoose.model('Donation').find({
      status: 'pending',
      created: { $lt: cutoffDate }
    });
    
    console.log(`Found ${stalePendingTxs.length} stale pending transactions`);
    
    let resolved = 0;
    let failed = 0;
    
    for (const tx of stalePendingTxs) {
      try {
        // If transaction has a hash, check its status on blockchain
        if (tx.transaction?.txHash) {
          try {
            const stellarTx = await server.transactions().transaction(tx.transaction.txHash).call();
            
            // Update status based on blockchain status
            if (stellarTx.successful) {
              await mongoose.model('Donation').updateOne(
                { _id: tx._id },
                { 
                  $set: { 
                    status: 'completed',
                    'transaction.status': 'completed',
                    updated: new Date()
                  }
                }
              );
              
              console.log(`Transaction ${tx._id} marked as completed (found on blockchain)`);
              
              // Update campaign funding
              await updateCampaignFunding(tx.campaignId, tx.amount.value);
              
              resolved++;
            } else {
              await mongoose.model('Donation').updateOne(
                { _id: tx._id },
                { 
                  $set: { 
                    status: 'failed',
                    'transaction.status': 'failed',
                    'transaction.error': 'Transaction failed on blockchain',
                    updated: new Date()
                  }
                }
              );
              
              console.log(`Transaction ${tx._id} marked as failed (found failed on blockchain)`);
              resolved++;
            }
          } catch (error) {
            if (error.response && error.response.status === 404) {
              // Transaction not found on blockchain
              await mongoose.model('Donation').updateOne(
                { _id: tx._id },
                { 
                  $set: { 
                    status: 'failed',
                    'transaction.status': 'failed',
                    'transaction.error': 'Transaction not found on blockchain',
                    updated: new Date()
                  }
                }
              );
              
              console.log(`Transaction ${tx._id} marked as failed (not found on blockchain)`);
              resolved++;
            } else {
              console.error(`Error checking transaction ${tx._id}:`, error.message);
              failed++;
            }
          }
        } else {
          // No transaction hash, mark as failed
          await mongoose.model('Donation').updateOne(
            { _id: tx._id },
            { 
              $set: { 
                status: 'failed',
                'transaction.status': 'failed',
                'transaction.error': 'No transaction hash available',
                updated: new Date()
              }
            }
          );
          
          console.log(`Transaction ${tx._id} marked as failed (no hash available)`);
          resolved++;
        }
      } catch (error) {
        console.error(`Error processing transaction ${tx._id}:`, error);
        failed++;
      }
    }
    
    console.log('\nRESOLUTION SUMMARY:');
    console.log('Total stale transactions:', stalePendingTxs.length);
    console.log('Successfully resolved:', resolved);
    console.log('Failed to resolve:', failed);
    
  } catch (error) {
    console.error('Error resolving stale pending transactions:', error);
  } finally {
    await mongoose.disconnect();
  }
}

// Helper function to update campaign funding
async function updateCampaignFunding(campaignId, amount) {
  await mongoose.model('Campaign').updateOne(
    { _id: campaignId },
    { 
      $inc: { 
        'funding.raisedAmount': amount,
        'funding.donorCount': 1
      },
      $set: { 
        updated: new Date() 
      }
    }
  );
}

// If run directly from command line
if (require.main === module) {
  const hoursOld = process.argv[2] ? parseInt(process.argv[2]) : 1;
  
  resolveStalePendingTransactions(hoursOld)
    .then(() => process.exit(0))
    .catch(error => {
      console.error('Fatal error:', error);
      process.exit(1);
    });
}

module.exports = { resolveStalePendingTransactions };
```

### Fixing Campaign Funding Discrepancies

Script to fix campaign funding discrepancies:

```javascript
// fix-campaign-funding.js
const mongoose = require('mongoose');
require('dotenv').config();

mongoose.connect(process.env.MONGODB_URI);

async function fixCampaignFunding(campaignId = null) {
  try {
    let campaignsQuery = {};
    
    if (campaignId) {
      campaignsQuery._id = mongoose.Types.ObjectId(campaignId);
    }
    
    const campaigns = await mongoose.model('Campaign').find(campaignsQuery);
    
    console.log(`Processing ${campaigns.length} campaigns...`);
    
    let fixed = 0;
    let noDiscrepancy = 0;
    let failed = 0;
    
    for (const campaign of campaigns) {
      try {
        // Calculate actual funding from donations
        const completedDonations = await mongoose.model('Donation').find({
          campaignId: campaign._id,
          status: 'completed'
        });
        
        const actualFunding = completedDonations.reduce((sum, donation) => {
          return sum + donation.amount.value;
        }, 0);
        
        // Check for refunds
        const refunds = await mongoose.model('Refund').find({
          campaignId: campaign._id
        });
        
        const refundAmount = refunds.reduce((sum, refund) => {
          return sum + refund.amount.value;
        }, 0);
        
        // Calculate correct funding
        const correctFunding = actualFunding - refundAmount;
        
        // Get current funding
        const currentFunding = campaign.funding?.raisedAmount || 0;
        
        // Allow small floating-point differences (0.001 tolerance)
        if (Math.abs(correctFunding - currentFunding) > 0.001) {
          console.log(`\nFixing campaign: ${campaign._id} (${campaign.title})`);
          console.log('Current funding:', currentFunding);
          console.log('Correct funding:', correctFunding);
          console.log('Difference:', correctFunding - currentFunding);
          
          // Update campaign funding
          await mongoose.model('Campaign').updateOne(
            { _id: campaign._id },
            { 
              $set: { 
                'funding.raisedAmount': correctFunding,
                'funding.lastReconciled': new Date(),
                updated: new Date()
              }
            }
          );
          
          console.log('Funding corrected successfully.');
          fixed++;
        } else {
          noDiscrepancy++;
        }
        
        // Recalculate donor count
        const uniqueDonors = await mongoose.model('Donation').distinct('userId', {
          campaignId: campaign._id,
          status: 'completed'
        });
        
        const correctDonorCount = uniqueDonors.length;
        const currentDonorCount = campaign.funding?.donorCount || 0;
        
        if (correctDonorCount !== currentDonorCount) {
          console.log(`\nFixing donor count for campaign: ${campaign._id}`);
          console.log('Current donor count:', currentDonorCount);
          console.log('Correct donor count:', correctDonorCount);
          
          await mongoose.model('Campaign').updateOne(
            { _id: campaign._id },
            { 
              $set: { 
                'funding.donorCount': correctDonorCount,
                updated: new Date()
              }
            }
          );
          
          console.log('Donor count corrected successfully.');
        }
        
      } catch (error) {
        console.error(`Error processing campaign ${campaign._id}:`, error);
        failed++;
      }
    }
    
    console.log('\nSUMMARY:');
    console.log('Total campaigns processed:', campaigns.length);
    console.log('Campaigns fixed:', fixed);
    console.log('Campaigns with no discrepancy:', noDiscrepancy);
    console.log('Failed to process:', failed);
    
  } catch (error) {
    console.error('Error fixing campaign funding:', error);
  } finally {
    await mongoose.disconnect();
  }
}

// If run directly from command line
if (require.main === module) {
  const campaignId = process.argv[2] || null;
  
  fixCampaignFunding(campaignId)
    .then(() => process.exit(0))
    .catch(error => {
      console.error('Fatal error:', error);
      process.exit(1);
    });
}

module.exports = { fixCampaignFunding };
```

## Monitoring and Alerting

### Health Check API

Implement a health check API endpoint to monitor system status:

```javascript
// health-check.js
const express = require('express');
const router = express.Router();
const mongoose = require('mongoose');
const StellarSdk = require('stellar-sdk');

// Stellar server
const server = new StellarSdk.Server(
  process.env.STELLAR_NETWORK === 'testnet' 
    ? 'https://horizon-testnet.stellar.org' 
    : 'https://horizon.stellar.org'
);

// MongoDB health check
async function checkMongoDB() {
  try {
    if (mongoose.connection.readyState !== 1) {
      return { healthy: false, error: 'MongoDB not connected' };
    }
    
    // Try a simple query
    await mongoose.connection.db.admin().ping();
    
    return { healthy: true };
  } catch (error) {
    return { healthy: false, error: error.message };
  }
}

// Stellar health check
async function checkStellar() {
  try {
    // Check if Horizon is responsive
    const response = await server.getHealth();
    
    return { 
      healthy: true,
      details: {
        horizonVersion: response.version,
        coreVersion: response.core_version
      }
    };
  } catch (error) {
    return { healthy: false, error: error.message };
  }
}

// Check transaction processing status
async function checkTransactionProcessing() {
  try {
    const oneHourAgo = new Date();
    oneHourAgo.setHours(oneHourAgo.getHours() - 1);
    
    // Check for stale pending transactions
    const stalePendingCount = await mongoose.model('Donation').countDocuments({
      status: 'pending',
      created: { $lt: oneHourAgo }
    });
    
    // Check for recent failed transactions
    const recentFailedCount = await mongoose.model('Donation').countDocuments({
      status: 'failed',
      created: { $gt: oneHourAgo }
    });
    
    // Check for recent successful transactions
    const recentSuccessCount = await mongoose.model('Donation').countDocuments({
      status: 'completed',
      created: { $gt: oneHourAgo }
    });
    
    const healthy = stalePendingCount < 5; // Fewer than 5 stale pending transactions
    
    return { 
      healthy,
      details: {
        stalePendingTransactions: stalePendingCount,
        recentFailedTransactions: recentFailedCount,
        recentSuccessfulTransactions: recentSuccessCount,
        successRate: recentSuccessCount + recentFailedCount > 0 ? 
          (recentSuccessCount / (recentSuccessCount + recentFailedCount)) * 100 : 100
      }
    };
  } catch (error) {
    return { healthy: false, error: error.message };
  }
}

// Health check endpoint
router.get('/', async (req, res) => {
  try {
    // Run all health checks in parallel
    const [
      mongoStatus,
      stellarStatus,
      transactionStatus
    ] = await Promise.all([
      checkMongoDB(),
      checkStellar(),
      checkTransactionProcessing()
    ]);
    
    // Overall status is healthy only if all checks are healthy
    const healthy = mongoStatus.healthy && 
                    stellarStatus.healthy && 
                    transactionStatus.healthy;
    
    const response = {
      status: healthy ? 'healthy' : 'unhealthy',
      timestamp: new Date().toISOString(),
      components: {
        mongodb: mongoStatus,
        stellar: stellarStatus,
        transactions: transactionStatus
      }
    };
    
    // Send appropriate status code
    res.status(healthy ? 200 : 500).json(response);
  } catch (error) {
    res.status(500).json({
      status: 'error',
      error: error.message,
      timestamp: new Date().toISOString()
    });
  }
});

module.exports = router;
```

## FAQ

### How do I test a transaction without using real funds?

Use the Stellar testnet for development and testing:

1. Update configuration to use testnet:
   ```javascript
   const config = {
     useTestnet: true,
     horizonUrl: 'https://horizon-testnet.stellar.org'
   };
   ```

2. Get test XLM from Stellar's Friendbot:
   ```javascript
   async function fundTestAccount(publicKey) {
     const response = await fetch(
       `https://friendbot.stellar.org?addr=${encodeURIComponent(publicKey)}`
     );
     return await response.json();
   }
   ```

### Why does a transaction show as completed in database but fails to appear in the donor's history?

This could be due to:

1. **Database inconsistency**: The transaction might be marked as completed but the relationship to the donor might be missing
2. **Incorrect donor ID**: The transaction might be associated with a different donor ID
3. **Visibility settings**: If the donation was marked as anonymous, it might be filtered out in certain views

Solution: Use the transaction inspector tool to check the actual donor association:

```bash
node scripts/transaction-inspector.js [transaction_id]
```

### How do I handle chargebacks or disputed transactions?

Stellar transactions cannot be reversed once confirmed, so you need to implement a manual refund process:

1. Record the dispute in your system
2. Process a refund transaction from the campaign account to the donor account
3. Mark the original transaction as refunded
4. Update campaign funding statistics
5. Notify all parties involved

Use the refund procedure provided in this guide.

### How can I add support for a new Stellar asset?

To add support for a new Stellar asset (token):

1. **Update Configuration**: Add the new asset details to your configuration:

```javascript
// config/assets.js
module.exports = {
  supported_assets: [
    { code: 'XLM', issuer: null, type: 'native' }, // Native XLM
    {
      code: 'USDC',
      issuer: 'GBBD47IF6LWK7P7MDEVSCWR7DPUWV3NY3DTQEVFL4NAT4AQH3ZLLFLA5',
      type: 'credit_alphanum4'
    },
    // Add new asset here
    {
      code: 'NEWTOKEN',
      issuer: 'GBCJSSWKGV...[issuer public key]',
      type: 'credit_alphanum12'
    }
  ]
};
```

2. **Update Transaction Builder**: Modify the transaction builder to handle the new asset:

```javascript
// In StellarTransactionBuilder.js
function getAssetFromCode(assetCode, assetIssuer = null) {
  if (!assetCode || assetCode === 'XLM') {
    return StellarSdk.Asset.native();
  }

  // Get asset issuer from configuration if not provided
  if (!assetIssuer) {
    const assetConfig = config.supported_assets.find(a => a.code === assetCode);
    if (!assetConfig || !assetConfig.issuer) {
      throw new Error(`Unknown asset code: ${assetCode}`);
    }
    assetIssuer = assetConfig.issuer;
  }

  // For alphanumeric4 assets (up to 4 characters)
  if (assetCode.length <= 4) {
    return new StellarSdk.Asset(assetCode, assetIssuer);
  }

  // For alphanumeric12 assets (5-12 characters)
  return new StellarSdk.Asset(assetCode, assetIssuer);
}
```

3. **Update Database Schema**: Modify your donation schema to store asset information:

```javascript
// models/Donation.js
const DonationSchema = new mongoose.Schema({
  // ... existing fields
  amount: {
    value: { type: Number, required: true },
    currency: { type: String, required: true, default: 'XLM' },
    issuer: { type: String, default: null } // Add issuer field
  },
  // ... other fields
});
```

4. **Update UI**: Update user interface to include the new asset in dropdown menus

5. **Test Thoroughly**: Test all transaction types with the new asset

### How do I handle timeouts during transaction submission?

Stellar transactions can sometimes time out during submission. Implement a robust retry mechanism:

```javascript
/**
 * Submit transaction with automatic retry for timeout errors
 * @param {Transaction} transaction - Signed Stellar transaction
 * @param {Object} options - Options for submission
 * @returns {Promise} - Resolves to transaction result
 */
async function submitTransactionWithTimeout(transaction, options = {}) {
  const maxRetries = options.maxRetries || 3;
  const initialTimeout = options.initialTimeout || 30000; // 30 seconds

  let lastError;

  for (let attempt = 1; attempt <= maxRetries; attempt++) {
    try {
      // Submit transaction with specific timeout
      const timeout = initialTimeout * attempt; // Increase timeout with each retry

      const result = await Promise.race([
        server.submitTransaction(transaction),
        new Promise((_, reject) =>
          setTimeout(() => reject(new Error('Submission timeout')), timeout)
        )
      ]);

      return result;
    } catch (error) {
      lastError = error;

      // Only retry on timeout or specific recoverable errors
      if (
        error.message === 'Submission timeout' ||
        (error.response && error.response.status === 504) || // Gateway timeout
        (error.response && error.response.data &&
         error.response.data.extras &&
         error.response.data.extras.result_codes &&
         error.response.data.extras.result_codes.transaction === 'tx_too_late')
      ) {
        console.log(`Transaction retry ${attempt}/${maxRetries} after timeout`);

        if (attempt < maxRetries) {
          // Wait before retrying (exponential backoff)
          await new Promise(resolve =>
            setTimeout(resolve, 1000 * Math.pow(2, attempt - 1))
          );
          continue;
        }
      }

      // Non-recoverable error or max retries reached
      throw lastError;
    }
  }

  throw lastError;
}
```

### How do I verify if a transaction was successful when my application crashes mid-processing?

If your application crashes after submitting a transaction but before updating the database, you can verify the transaction status:

```javascript
/**
 * Verify transaction status and update database
 * @param {string} txHash - Transaction hash
 * @param {string} donationId - Database donation ID
 * @returns {Promise} - Resolves to updated donation
 */
async function verifyAndUpdateTransaction(txHash, donationId) {
  try {
    // Get transaction from blockchain
    const tx = await server.transactions().transaction(txHash).call();

    // Get donation from database
    const donation = await mongoose.model('Donation').findById(donationId);

    if (!donation) {
      throw new Error(`Donation not found: ${donationId}`);
    }

    // Check if already processed
    if (donation.status !== 'pending') {
      console.log(`Donation ${donationId} already processed with status: ${donation.status}`);
      return donation;
    }

    // Update based on blockchain status
    if (tx.successful) {
      // Update donation status to completed
      await mongoose.model('Donation').updateOne(
        { _id: donationId },
        {
          $set: {
            status: 'completed',
            'transaction.status': 'completed',
            'transaction.timestamp': new Date(tx.created_at),
            updated: new Date()
          }
        }
      );

      // Update campaign funding
      await updateCampaignFunding(donation.campaignId, donation.amount.value);

      console.log(`Donation ${donationId} verified and marked as completed`);
    } else {
      // Update donation status to failed
      await mongoose.model('Donation').updateOne(
        { _id: donationId },
        {
          $set: {
            status: 'failed',
            'transaction.status': 'failed',
            'transaction.error': 'Transaction failed on blockchain',
            updated: new Date()
          }
        }
      );

      console.log(`Donation ${donationId} verified and marked as failed`);
    }

    // Return updated donation
    return await mongoose.model('Donation').findById(donationId);

  } catch (error) {
    if (error.response && error.response.status === 404) {
      // Transaction not found on blockchain
      console.log(`Transaction ${txHash} not found on blockchain`);

      // Check how old the donation is
      const donation = await mongoose.model('Donation').findById(donationId);
      const thirtyMinutesAgo = new Date(Date.now() - 30 * 60 * 1000);

      // If donation is older than 30 minutes, mark as failed
      if (donation && donation.created < thirtyMinutesAgo) {
        await mongoose.model('Donation').updateOne(
          { _id: donationId },
          {
            $set: {
              status: 'failed',
              'transaction.status': 'failed',
              'transaction.error': 'Transaction not found on blockchain after 30 minutes',
              updated: new Date()
            }
          }
        );

        console.log(`Donation ${donationId} marked as failed (not found on blockchain after 30 minutes)`);
      } else {
        // Less than 30 minutes old, wait longer
        console.log(`Donation ${donationId} is recent, keeping as pending`);
      }
    } else {
      console.error(`Error verifying transaction ${txHash}:`, error);
    }

    throw error;
  }
}
```

### How do I handle ledger full errors during high volume donation periods?

During high network traffic, Stellar may return "tx_insufficient_fee" errors. Implement a dynamic fee strategy:

```javascript
/**
 * Get recommended fee based on network conditions
 * @returns {Promise<number>} - Stellar transaction fee in stroops
 */
async function getDynamicFee() {
  try {
    // Get fee stats from Horizon
    const feeStats = await server.feeStats();

    // Get fee percentiles
    const p10 = parseInt(feeStats.fee_charged.p10);
    const p50 = parseInt(feeStats.fee_charged.p50);
    const p90 = parseInt(feeStats.fee_charged.p90);

    // Calculate recommended fee based on network congestion
    let recommendedFee;

    // If network is congested (p90 significantly higher than p10)
    if (p90 > p10 * 5) {
      // Network is congested, use p90 for faster processing
      recommendedFee = p90;
    } else {
      // Normal network conditions, use median (p50)
      recommendedFee = p50;
    }

    // Add 20% buffer and ensure minimum BASE_FEE
    recommendedFee = Math.max(
      StellarSdk.BASE_FEE,
      Math.ceil(recommendedFee * 1.2)
    );

    console.log(`Dynamic fee: ${recommendedFee} stroops`);
    return recommendedFee;

  } catch (error) {
    console.error('Error getting fee stats:', error);
    // Fallback to 2x BASE_FEE
    return StellarSdk.BASE_FEE * 2;
  }
}

/**
 * Retry transaction with increased fee
 * @param {Transaction} transaction - Original transaction that failed
 * @param {string} sourceSecret - Source account secret key
 * @returns {Promise} - New transaction result
 */
async function retryWithHigherFee(transaction, sourceSecret) {
  try {
    // Create fee bump transaction
    const sourceKeypair = StellarSdk.Keypair.fromSecret(sourceSecret);

    // Get a higher fee (3x the original or based on network conditions)
    const dynamicFee = await getDynamicFee();
    const higherFee = Math.max(
      parseInt(transaction.fee) * 3,
      dynamicFee
    );

    // Create fee bump transaction
    const feeBumpTransaction = StellarSdk.TransactionBuilder.buildFeeBumpTransaction(
      sourceKeypair,
      higherFee,
      transaction,
      StellarSdk.Networks.PUBLIC
    );

    // Sign and submit
    feeBumpTransaction.sign(sourceKeypair);
    return await server.submitTransaction(feeBumpTransaction);

  } catch (error) {
    console.error('Error retrying with higher fee:', error);
    throw error;
  }
}
```

### How do I debug a transaction that has a tx_bad_auth error?

The "tx_bad_auth" error indicates an authentication problem with the transaction:

1. **Check the signing keys**: Ensure the correct account is signing the transaction

2. **Verify the key has proper permissions**:

```javascript
/**
 * Verify signing permissions
 * @param {string} publicKey - Account public key
 * @returns {Promise<Object>} - Account signers and thresholds
 */
async function verifySigningPermissions(publicKey) {
  try {
    // Load account
    const account = await server.loadAccount(publicKey);

    // Get signers and thresholds
    const signers = account.signers;
    const thresholds = {
      low: account.thresholds.low_threshold,
      medium: account.thresholds.med_threshold,
      high: account.thresholds.high_threshold
    };

    console.log('Account signers:');
    signers.forEach(signer => {
      console.log(`- ${signer.key} (weight: ${signer.weight})`);
    });

    console.log('Account thresholds:');
    console.log(thresholds);

    return { signers, thresholds };

  } catch (error) {
    console.error('Error verifying signing permissions:', error);
    throw error;
  }
}
```

3. **Check for multi-signature requirements**: Some accounts may require multiple signatures

4. **Verify transaction source account**: Ensure the transaction is using the correct source account

### How do I monitor the health of the transaction system?

Implement a comprehensive monitoring system:

1. **Transaction Success Rate Alert**:

```javascript
/**
 * Check transaction success rate
 * @param {number} timeWindowMinutes - Time window in minutes
 * @param {number} alertThreshold - Alert threshold percentage
 * @returns {Promise<Object>} - Success rate report
 */
async function monitorTransactionSuccessRate(timeWindowMinutes = 60, alertThreshold = 90) {
  try {
    const timeWindow = new Date();
    timeWindow.setMinutes(timeWindow.getMinutes() - timeWindowMinutes);

    // Count total transactions
    const totalTx = await mongoose.model('Donation').countDocuments({
      created: { $gte: timeWindow },
      status: { $in: ['completed', 'failed'] }
    });

    // Count successful transactions
    const successfulTx = await mongoose.model('Donation').countDocuments({
      created: { $gte: timeWindow },
      status: 'completed'
    });

    // Calculate success rate
    const successRate = totalTx > 0 ? (successfulTx / totalTx) * 100 : 100;

    const status = successRate >= alertThreshold ? 'healthy' : 'warning';

    // Send alert if below threshold
    if (successRate < alertThreshold) {
      await sendAlert({
        type: 'transaction_success_rate',
        level: 'warning',
        message: `Transaction success rate (${successRate.toFixed(2)}%) below threshold (${alertThreshold}%)`,
        details: {
          timeWindow: `${timeWindowMinutes} minutes`,
          totalTransactions: totalTx,
          successfulTransactions: successfulTx,
          successRate: successRate
        }
      });
    }

    return {
      status,
      successRate,
      totalTransactions: totalTx,
      successfulTransactions: successfulTx,
      timeWindow: `${timeWindowMinutes} minutes`
    };

  } catch (error) {
    console.error('Error monitoring transaction success rate:', error);
    throw error;
  }
}
```

2. **Stale Pending Transactions Monitor**:

```javascript
/**
 * Monitor for stale pending transactions
 * @param {number} staleThrMinutes - Stale threshold in minutes
 * @returns {Promise<Object>} - Stale transaction report
 */
async function monitorStalePendingTransactions(staleThrMinutes = 30) {
  try {
    const staleThreshold = new Date();
    staleThreshold.setMinutes(staleThreshold.getMinutes() - staleThrMinutes);

    // Find stale pending transactions
    const staleTx = await mongoose.model('Donation').find({
      status: 'pending',
      created: { $lt: staleThreshold }
    }).sort({ created: 1 }).limit(100);

    // Send alert if stale transactions found
    if (staleTx.length > 0) {
      await sendAlert({
        type: 'stale_pending_transactions',
        level: staleTx.length > 10 ? 'critical' : 'warning',
        message: `${staleTx.length} transactions stuck in pending state for over ${staleThrMinutes} minutes`,
        details: {
          count: staleTx.length,
          oldest: staleTx[0].created,
          sampleIds: staleTx.slice(0, 5).map(tx => tx._id.toString())
        }
      });
    }

    return {
      status: staleTx.length > 10 ? 'critical' : (staleTx.length > 0 ? 'warning' : 'healthy'),
      count: staleTx.length,
      oldest: staleTx.length > 0 ? staleTx[0].created : null,
      threshold: `${staleThrMinutes} minutes`
    };

  } catch (error) {
    console.error('Error monitoring stale pending transactions:', error);
    throw error;
  }
}
```

3. **Account Balance Monitor**:

```javascript
/**
 * Monitor critical account balances
 * @returns {Promise<Object>} - Account balance report
 */
async function monitorAccountBalances() {
  try {
    // Get critical account public keys from configuration
    const criticalAccounts = config.monitored_accounts || [];

    const results = [];

    for (const account of criticalAccounts) {
      try {
        // Load account from Stellar
        const accountData = await server.loadAccount(account.publicKey);

        // Get XLM balance
        const xlmBalance = accountData.balances.find(b => b.asset_type === 'native');
        const balance = xlmBalance ? parseFloat(xlmBalance.balance) : 0;

        // Calculate minimum balance requirement
        const baseReserve = 1; // XLM
        const entryReserve = 0.5; // XLM per entry
        const minBalance = baseReserve + (accountData.subentry_count * entryReserve);

        // Calculate available balance
        const availableBalance = balance - minBalance;

        // Check against threshold
        const threshold = account.threshold || 100; // Default 100 XLM
        const status = availableBalance < threshold ? 'warning' : 'healthy';

        // Send alert if below threshold
        if (availableBalance < threshold) {
          await sendAlert({
            type: 'low_account_balance',
            level: availableBalance < threshold / 2 ? 'critical' : 'warning',
            message: `Account ${account.name} (${account.publicKey.slice(0, 5)}...${account.publicKey.slice(-5)}) has low balance: ${availableBalance.toFixed(2)} XLM available`,
            details: {
              account: account.publicKey,
              name: account.name,
              balance: balance,
              minRequired: minBalance,
              available: availableBalance,
              threshold: threshold
            }
          });
        }

        results.push({
          account: account.publicKey,
          name: account.name,
          status,
          balance,
          minRequired: minBalance,
          available: availableBalance,
          threshold
        });

      } catch (error) {
        console.error(`Error checking account ${account.publicKey}:`, error);

        results.push({
          account: account.publicKey,
          name: account.name,
          status: 'error',
          error: error.message
        });
      }
    }

    return {
      status: results.some(r => r.status === 'error') ? 'error' :
             results.some(r => r.status === 'warning') ? 'warning' : 'healthy',
      accounts: results
    };

  } catch (error) {
    console.error('Error monitoring account balances:', error);
    throw error;
  }
}
```

### What should I do if a donor claims they never received a refund?

1. **Verify refund transaction status**:

```javascript
/**
 * Verify refund transaction status
 * @param {string} refundId - Refund record ID
 * @returns {Promise<Object>} - Refund verification result
 */
async function verifyRefundTransaction(refundId) {
  try {
    // Get refund record
    const refund = await mongoose.model('Refund').findById(refundId);

    if (!refund) {
      return {
        success: false,
        error: 'Refund record not found'
      };
    }

    // Check if transaction hash exists
    if (!refund.transaction || !refund.transaction.txHash) {
      return {
        success: false,
        error: 'No transaction hash found',
        refund
      };
    }

    // Verify on blockchain
    try {
      const tx = await server.transactions().transaction(refund.transaction.txHash).call();

      // Check transaction status
      if (tx.successful) {
        return {
          success: true,
          refund,
          blockchain: {
            exists: true,
            successful: true,
            ledger: tx.ledger,
            created_at: tx.created_at
          }
        };
      } else {
        return {
          success: false,
          error: 'Transaction failed on blockchain',
          refund,
          blockchain: {
            exists: true,
            successful: false,
            ledger: tx.ledger,
            created_at: tx.created_at
          }
        };
      }

    } catch (error) {
      if (error.response && error.response.status === 404) {
        return {
          success: false,
          error: 'Transaction not found on blockchain',
          refund,
          blockchain: {
            exists: false
          }
        };
      }

      throw error;
    }

  } catch (error) {
    console.error('Error verifying refund transaction:', error);
    return {
      success: false,
      error: error.message
    };
  }
}
```

2. **Process a new refund if verification fails**:

```javascript
/**
 * Reprocess failed refund
 * @param {string} refundId - Original refund ID
 * @param {string} adminId - Administrator processing the refund
 * @returns {Promise<Object>} - Refund result
 */
async function reprocessRefund(refundId, adminId) {
  try {
    // Get original refund
    const originalRefund = await mongoose.model('Refund').findById(refundId);

    if (!originalRefund) {
      throw new Error(`Refund not found: ${refundId}`);
    }

    // Verify original donation exists
    const donation = await mongoose.model('Donation').findById(originalRefund.donationId);

    if (!donation) {
      throw new Error(`Original donation not found: ${originalRefund.donationId}`);
    }

    // Get campaign wallet
    const campaignWallet = await mongoose.model('Wallet').findOne({
      campaignId: originalRefund.campaignId
    });

    if (!campaignWallet) {
      throw new Error(`Campaign wallet not found: ${originalRefund.campaignId}`);
    }

    // Get donor wallet
    const donorWallet = await mongoose.model('Wallet').findOne({
      userId: originalRefund.userId
    });

    if (!donorWallet) {
      throw new Error(`Donor wallet not found: ${originalRefund.userId}`);
    }

    // Create new refund transaction
    const stellarBuilder = new StellarTransactionBuilder({
      useTestnet: process.env.STELLAR_NETWORK === 'testnet'
    });

    const transaction = await stellarBuilder.createPayment({
      sourceSecret: campaignWallet.secretKey,
      destinationAddress: donorWallet.publicKey,
      amount: originalRefund.amount.value.toString(),
      memo: `refund:${originalRefund.donationId}:retry`
    });

    const result = await stellarBuilder.submitTransaction(transaction);

    if (!result.success) {
      throw new Error(`Failed to process refund: ${result.error}`);
    }

    // Create new refund record
    const newRefund = {
      donationId: originalRefund.donationId,
      campaignId: originalRefund.campaignId,
      userId: originalRefund.userId,
      amount: originalRefund.amount,
      reason: `Reprocessed refund (Original: ${refundId})`,
      processedBy: new mongoose.Types.ObjectId(adminId),
      transaction: {
        txHash: result.result.hash,
        stellarAddress: donorWallet.publicKey,
        status: 'completed',
        timestamp: new Date()
      },
      originalRefundId: refundId,
      created: new Date()
    };

    const savedRefund = await mongoose.model('Refund').create(newRefund);

    // Update original refund
    await mongoose.model('Refund').updateOne(
      { _id: refundId },
      {
        $set: {
          status: 'replaced',
          replacedBy: savedRefund._id,
          updated: new Date()
        }
      }
    );

    return {
      success: true,
      originalRefundId: refundId,
      newRefundId: savedRefund._id,
      transactionHash: result.result.hash
    };

  } catch (error) {
    console.error('Error reprocessing refund:', error);
    return {
      success: false,
      error: error.message
    };
  }
}
```

This troubleshooting guide aims to provide comprehensive solutions for various issues you might encounter while working with The Give Hub transaction system. Keep it handy when developing, testing, and maintaining the platform.
