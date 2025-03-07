# The Give Hub Transaction System Integration Guide

This guide provides instructions for integrating the transaction system into your application, covering setup, configuration, best practices, and common integration patterns.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Integrating with Frontend Applications](#integrating-with-frontend-applications)
- [Integrating with Backend Services](#integrating-with-backend-services)
- [Testing](#testing)
- [Deployment](#deployment)
- [Monitoring](#monitoring)
- [Troubleshooting](#troubleshooting)

## Prerequisites

Before integrating the transaction system, ensure you have:

1. **Stellar Account**: A funded Stellar account for testing
2. **MongoDB Database**: For storing transaction records
3. **Node.js >= 14.x** (for JavaScript implementation)
4. **PHP >= 7.4** (for PHP implementation)
5. **Stellar SDK**:
   - JavaScript: `stellar-sdk` npm package
   - PHP: `stellar/stellar-sdk` composer package

## Installation

### JavaScript Implementation

```

## Testing

Thorough testing of blockchain transactions is essential before deploying to production.

### Setting Up a Test Environment

1. **Use Stellar Testnet**:
   - Set `useTestnet: true` in your configuration
   - Get test XLM from [Stellar Laboratory](https://laboratory.stellar.org/)
   - Use testnet addresses for all accounts

2. **Mock Database**:
   - Create a test MongoDB database
   - Seed with test campaigns, donors, and other required data

3. **Test Accounts**:
   - Create multiple Stellar test accounts for different scenarios
   - Label accounts clearly (donor, campaign, escrow)

### Unit Testing

```javascript
// tests/unit/TransactionService.test.js
const mongoose = require('mongoose');
const TransactionService = require('../../services/TransactionService');
const StellarSdk = require('stellar-sdk');
const { MongoMemoryServer } = require('mongodb-memory-server');

describe('TransactionService', () => {
  let mongoServer;
  let transactionService;
  let testAccounts = {};
  
  beforeAll(async () => {
    // Set up in-memory MongoDB for tests
    mongoServer = await MongoMemoryServer.create();
    const uri = mongoServer.getUri();
    await mongoose.connect(uri);
    
    // Create test Stellar accounts
    const donorKeyPair = StellarSdk.Keypair.random();
    const campaignKeyPair = StellarSdk.Keypair.random();
    
    testAccounts = {
      donor: {
        publicKey: donorKeyPair.publicKey(),
        secretKey: donorKeyPair.secret()
      },
      campaign: {
        publicKey: campaignKeyPair.publicKey(),
        secretKey: campaignKeyPair.secret() 
      }
    };
    
    // Fund test accounts on testnet
    await fundTestAccount(testAccounts.donor.publicKey);
    await fundTestAccount(testAccounts.campaign.publicKey);
    
    // Initialize transaction service with test config
    transactionService = new TransactionService(mongoose.connection, {
      useTestnet: true,
      enableLogging: false
    });
    
    // Seed test data
    await seedTestData();
  });
  
  afterAll(async () => {
    await mongoose.disconnect();
    await mongoServer.stop();
  });
  
  // Helper to fund testnet accounts
  async function fundTestAccount(publicKey) {
    try {
      const response = await fetch(
        `https://friendbot.stellar.org?addr=${encodeURIComponent(publicKey)}`
      );
      return await response.json();
    } catch (error) {
      console.error('Error funding account:', error);
      throw error;
    }
  }
  
  // Seed test data
  async function seedTestData() {
    // Create test campaign
    const campaign = new mongoose.models.Campaign({
      _id: new mongoose.Types.ObjectId(),
      title: 'Test Campaign',
      stellarAddress: testAccounts.campaign.publicKey,
      status: 'active',
      creator: new mongoose.Types.ObjectId(),
      funding: {
        targetAmount: 1000,
        raisedAmount: 0,
        donorCount: 0
      }
    });
    
    await campaign.save();
    
    // Create test donor
    const donor = new mongoose.models.Donor({
      _id: new mongoose.Types.ObjectId(),
      name: 'Test Donor',
      status: 'active',
      totalDonated: 0
    });
    
    await donor.save();
    
    testAccounts.campaignId = campaign._id.toString();
    testAccounts.donorId = donor._id.toString();
  }
  
  test('should process a donation successfully', async () => {
    const result = await transactionService.processDonation({
      donorId: testAccounts.donorId,
      campaignId: testAccounts.campaignId,
      amount: '10.5',
      sourceSecret: testAccounts.donor.secretKey,
      isAnonymous: false,
      message: 'Test donation'
    });
    
    expect(result.success).toBe(true);
    expect(result.transactionRecord).toBeDefined();
    expect(result.transactionRecord.status).toBe('completed');
    
    // Verify donation was recorded in database
    const donation = await mongoose.models.Donation.findById(
      result.transactionRecord._id
    );
    
    expect(donation).toBeDefined();
    expect(donation.amount.value).toBe(10.5);
    
    // Verify campaign funding was updated
    const campaign = await mongoose.models.Campaign.findById(
      testAccounts.campaignId
    );
    
    expect(campaign.funding.raisedAmount).toBe(10.5);
    expect(campaign.funding.donorCount).toBe(1);
  });
  
  // Add more tests for other transaction types
});
```

### Integration Testing

For PHP integration tests with PHPUnit:

```php
<?php
// tests/Integration/TransactionProcessorTest.php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\TransactionProcessor;
use MongoDB\Client;

class TransactionProcessorTest extends TestCase
{
    private $processor;
    private $db;
    private $testAccounts = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Connect to test database
        $this->db = new Client('mongodb://localhost:27017');
        $this->db->selectDatabase('givehub_test');
        
        // Initialize transaction processor
        $this->processor = new TransactionProcessor(true); // use testnet
        
        // Create and fund test accounts
        $this->createTestAccounts();
        
        // Seed test data
        $this->seedTestData();
    }
    
    protected function tearDown(): void
    {
        // Clean up test database
        $this->db->selectDatabase('givehub_test')->drop();
        parent::tearDown();
    }
    
    private function createTestAccounts()
    {
        // Create Stellar keypairs
        $donorKeypair = \ZuluCrypto\StellarSdk\Keypair::newFromRandom();
        $campaignKeypair = \ZuluCrypto\StellarSdk\Keypair::newFromRandom();
        
        $this->testAccounts = [
            'donor' => [
                'publicKey' => $donorKeypair->getPublicKey(),
                'secretKey' => $donorKeypair->getSecret()
            ],
            'campaign' => [
                'publicKey' => $campaignKeypair->getPublicKey(),
                'secretKey' => $campaignKeypair->getSecret()
            ]
        ];
        
        // Fund test accounts
        $this->fundTestAccount($this->testAccounts['donor']['publicKey']);
        $this->fundTestAccount($this->testAccounts['campaign']['publicKey']);
    }
    
    private function fundTestAccount($publicKey)
    {
        $response = file_get_contents(
            "https://friendbot.stellar.org?addr=" . urlencode($publicKey)
        );
        
        if (!$response) {
            throw new \Exception("Failed to fund test account: {$publicKey}");
        }
        
        return json_decode($response, true);
    }
    
    private function seedTestData()
    {
        // Create test campaign
        $campaignId = new \MongoDB\BSON\ObjectId();
        $this->db->selectDatabase('givehub_test')->campaigns->insertOne([
            '_id' => $campaignId,
            'title' => 'Test Campaign',
            'stellarAddress' => $this->testAccounts['campaign']['publicKey'],
            'status' => 'active',
            'creator' => new \MongoDB\BSON\ObjectId(),
            'funding' => [
                'targetAmount' => 1000,
                'raisedAmount' => 0,
                'donorCount' => 0
            ]
        ]);
        
        // Create test donor
        $donorId = new \MongoDB\BSON\ObjectId();
        $this->db->selectDatabase('givehub_test')->donors->insertOne([
            '_id' => $donorId,
            'name' => 'Test Donor',
            'status' => 'active',
            'totalDonated' => 0
        ]);
        
        $this->testAccounts['campaignId'] = (string)$campaignId;
        $this->testAccounts['donorId'] = (string)$donorId;
    }
    
    public function testProcessDonation()
    {
        $result = $this->processor->processDonation([
            'donorId' => $this->testAccounts['donorId'],
            'campaignId' => $this->testAccounts['campaignId'],
            'amount' => '10.5',
            'sourceSecret' => $this->testAccounts['donor']['secretKey'],
            'isAnonymous' => false,
            'message' => 'Test donation'
        ]);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('transactionHash', $result);
        
        // Verify donation was recorded in database
        $donation = $this->db->selectDatabase('givehub_test')->donations->findOne([
            'transaction.txHash' => $result['transactionHash']
        ]);
        
        $this->assertNotNull($donation);
        $this->assertEquals(10.5, $donation['amount']['value']);
        
        // Verify campaign funding was updated
        $campaign = $this->db->selectDatabase('givehub_test')->campaigns->findOne([
            '_id' => new \MongoDB\BSON\ObjectId($this->testAccounts['campaignId'])
        ]);
        
        $this->assertEquals(10.5, $campaign['funding']['raisedAmount']);
        $this->assertEquals(1, $campaign['funding']['donorCount']);
    }
    
    // Add more test methods for other transaction types
}
```

## Deployment

### Preparing for Production

1. **Create Mainnet Accounts**:
   - Generate new Stellar keypairs for production use
   - Secure private keys using a vault service or hardware security module
   - Fund accounts with adequate XLM for operations

2. **Update Configuration**:
   - Set `useTestnet: false` in configuration
   - Update Horizon URL to production endpoint
   - Adjust fee settings if needed

3. **Data Migration**:
   - Migrate testing data to production (if applicable)
   - Create initial campaigns in production database

### Deployment Checklist

Before deployment to production:

- [ ] All tests pass (unit, integration, acceptance)
- [ ] Security audit completed
- [ ] Performance testing completed
- [ ] Production Stellar accounts created and funded
- [ ] Database indexes created for performance
- [ ] Configuration updated for production
- [ ] Logging and monitoring set up
- [ ] Backup and recovery procedures documented
- [ ] Legal compliance checked (financial regulations)

### Deployment Process

1. Deploy backend services (Node.js/PHP):

```bash
# Deploy Node.js backend
npm run build
pm2 start ecosystem.config.js --env production

# Deploy PHP backend
composer install --no-dev --optimize-autoloader
php artisan migrate
php artisan config:cache
php artisan route:cache
```

2. Set up scheduled tasks for recurring donations:

```bash
# Add to crontab (Linux)
0 0 * * * /usr/bin/php /path/to/project/artisan schedule:run >> /dev/null 2>&1

# Or for Node.js
0 0 * * * cd /path/to/project && node scripts/process-recurring-donations.js >> /var/log/recurring-donations.log 2>&1
```

## Monitoring

### Key Metrics to Monitor

1. **Transaction Success Rate**:
   - Track percentage of successful vs. failed transactions
   - Alert on sudden drops in success rate

2. **Transaction Latency**:
   - Measure time from submission to blockchain confirmation
   - Track median and 95th percentile response times

3. **Account Balances**:
   - Monitor Stellar account balances
   - Alert when balances fall below threshold

4. **Error Rates**:
   - Track different error types (validation, blockchain, database)
   - Set up alerts for critical errors

### Monitoring Tools

Set up monitoring with:

1. **Prometheus + Grafana**: For metrics collection and visualization
2. **Sentry**: For error tracking and reporting
3. **Stellar Horizon Dashboard**: For blockchain health monitoring
4. **Custom health check endpoints**: To verify system components

Example health check endpoint:

```javascript
// routes/health.js
router.get('/transaction-system', async (req, res) => {
  try {
    // Check database connection
    const dbStatus = await checkDatabaseConnection();
    
    // Check Stellar connection
    const stellarStatus = await checkStellarConnection();
    
    // Check account balances
    const balanceStatus = await checkAccountBalances();
    
    // Overall status
    const overallStatus = 
      dbStatus.healthy && 
      stellarStatus.healthy && 
      balanceStatus.healthy ? 'healthy' : 'unhealthy';
    
    return res.json({
      status: overallStatus,
      components: {
        database: dbStatus,
        stellar: stellarStatus,
        accounts: balanceStatus
      },
      timestamp: new Date().toISOString()
    });
  } catch (error) {
    return res.status(500).json({
      status: 'error',
      message: error.message
    });
  }
});
```

## Troubleshooting

### Common Issues and Solutions

#### 1. Transaction Submission Failures

**Issue**: Transactions fail to submit to the Stellar network.

**Solutions**:
- Check account balances (source account may not have enough XLM)
- Verify transaction sequence numbers (might be out of sync)
- Check if destination account exists
- Ensure the transaction is properly signed

#### 2. Database Consistency Issues

**Issue**: Database state doesn't match blockchain state.

**Solutions**:
- Implement reconciliation process between database and blockchain
- Add transaction status verification job
- Fix data inconsistencies with manual reconciliation tool

#### 3. Recurring Donation Processing Failures

**Issue**: Recurring donations fail to process automatically.

**Solutions**:
- Check scheduler/cron configuration
- Verify donor account balances
- Implement retry mechanism with exponential backoff
- Send notification to donors about upcoming charges

### Debugging Tools

1. **Stellar Laboratory**: For manual transaction inspection
2. **Horizon API Explorer**: For querying transactions and accounts
3. **MongoDB Compass**: For inspecting database state
4. **Log Analysis**: Use centralized logging solution

Example debugging script for transaction verification:

```javascript
// scripts/verify-transaction.js
const StellarSdk = require('stellar-sdk');
const mongoose = require('mongoose');
require('dotenv').config();

// Connect to database
mongoose.connect(process.env.MONGODB_URI);

async function verifyTransaction(txHash) {
  try {
    // Get transaction from database
    const dbTx = await mongoose.models.Donation.findOne({
      'transaction.txHash': txHash
    });
    
    if (!dbTx) {
      console.log('Transaction not found in database');
      return;
    }
    
    // Get transaction from Stellar
    const server = new StellarSdk.Server(
      process.env.NODE_ENV === 'production' 
        ? 'https://horizon.stellar.org' 
        : 'https://horizon-testnet.stellar.org'
    );
    
    const stellarTx = await server.transactions().transaction(txHash).call();
    
    console.log('Database transaction:', {
      id: dbTx._id,
      amount: dbTx.amount,
      status: dbTx.status,
      hash: dbTx.transaction.txHash
    });
    
    console.log('Stellar transaction:', {
      hash: stellarTx.hash,
      successful: stellarTx.successful,
      ledger: stellarTx.ledger,
      created_at: stellarTx.created_at,
      memo: stellarTx.memo
    });
    
    // Verify consistency
    if (dbTx.status === 'completed' && !stellarTx.successful) {
      console.log('INCONSISTENCY: Database shows successful but blockchain shows failed');
    } else if (dbTx.status === 'failed' && stellarTx.successful) {
      console.log('INCONSISTENCY: Database shows failed but blockchain shows successful');
    } else {
      console.log('Transaction status is consistent');
    }
  } catch (error) {
    console.error('Error verifying transaction:', error);
  } finally {
    mongoose.disconnect();
  }
}

// Get transaction hash from command line
const txHash = process.argv[2];
if (!txHash) {
  console.error('Please provide a transaction hash');
  process.exit(1);
}

verifyTransaction(txHash);
```

Run with:

```bash
node scripts/verify-transaction.js TX_HASH
```

This concludes the integration guide for The Give Hub Transaction System. For additional support, please refer to the API documentation or contact the development team.bash
# Install dependencies
npm install stellar-sdk

# Copy transaction modules to your project
cp StellarTransactionBuilder.js /path/to/your/project/lib/
cp TransactionService.js /path/to/your/project/services/
```

### PHP Implementation

```bash
# Install dependencies
composer require stellar/stellar-sdk mongodb/mongodb

# Copy transaction modules to your project
cp TransactionProcessor.php /path/to/your/project/lib/
```

## Configuration

### JavaScript Configuration

Create configuration files for different environments:

```javascript
// config/transaction.js
module.exports = {
  development: {
    useTestnet: true,
    horizonUrl: 'https://horizon-testnet.stellar.org',
    enableLogging: true,
    baseFee: 100,
    timeout: 30,
    maxRetries: 3,
    retryDelay: 2000
  },
  production: {
    useTestnet: false,
    horizonUrl: 'https://horizon.stellar.org',
    enableLogging: false,
    baseFee: 100,
    timeout: 30,
    maxRetries: 5,
    retryDelay: 3000
  }
};
```

Initialize the transaction service:

```javascript
const mongoose = require('mongoose');
const TransactionService = require('./services/TransactionService');
const config = require('./config/transaction')[process.env.NODE_ENV || 'development'];

// Initialize database connection
mongoose.connect(process.env.MONGODB_URI);

// Create transaction service instance
const transactionService = new TransactionService(mongoose.connection, config);

module.exports = transactionService;
```

### PHP Configuration

Create configuration files for different environments:

```php
// config/transaction.php
<?php
return [
    'development' => [
        'useTestnet' => true,
        'horizonUrl' => 'https://horizon-testnet.stellar.org',
        'enableLogging' => true,
        'baseFee' => 100,
        'timeout' => 30,
        'maxRetries' => 3,
        'retryDelay' => 2000
    ],
    'production' => [
        'useTestnet' => false,
        'horizonUrl' => 'https://horizon.stellar.org',
        'enableLogging' => false,
        'baseFee' => 100,
        'timeout' => 30,
        'maxRetries' => 5,
        'retryDelay' => 3000
    ]
];
```

Initialize the transaction processor:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/TransactionProcessor.php';

// Load configuration
$env = getenv('APP_ENV') ?: 'development';
$config = include(__DIR__ . '/config/transaction.php')[$env];

// Initialize transaction processor
$processor = new TransactionProcessor($config['useTestnet']);

// Use processor in your application
```

## Integrating with Frontend Applications

### User Wallet Management

For a secure user experience, implement a wallet management system:

```javascript
// client/services/wallet.js
export class WalletService {
  constructor() {
    this.StellarSdk = require('stellar-sdk');
  }

  // Generate a new Stellar keypair
  generateKeypair() {
    return this.StellarSdk.Keypair.random();
  }

  // Create a keypair from an existing secret
  keypairFromSecret(secret) {
    return this.StellarSdk.Keypair.fromSecret(secret);
  }

  // Encrypt a private key with a user password
  async encryptPrivateKey(privateKey, password) {
    // Implement secure encryption
    // Example: Use the Web Crypto API
    const encoder = new TextEncoder();
    const salt = crypto.getRandomValues(new Uint8Array(16));
    
    const keyMaterial = await crypto.subtle.importKey(
      'raw',
      encoder.encode(password),
      { name: 'PBKDF2' },
      false,
      ['deriveBits', 'deriveKey']
    );
    
    const key = await crypto.subtle.deriveKey(
      {
        name: 'PBKDF2',
        salt,
        iterations: 100000,
        hash: 'SHA-256'
      },
      keyMaterial,
      { name: 'AES-GCM', length: 256 },
      false,
      ['encrypt', 'decrypt']
    );
    
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const encryptedData = await crypto.subtle.encrypt(
      {
        name: 'AES-GCM',
        iv
      },
      key,
      encoder.encode(privateKey)
    );
    
    return {
      encryptedKey: Buffer.from(encryptedData).toString('base64'),
      salt: Buffer.from(salt).toString('base64'),
      iv: Buffer.from(iv).toString('base64')
    };
  }

  // Decrypt a private key
  async decryptPrivateKey(encryptedData, password) {
    // Implement secure decryption
    // ...
  }
}

    /**
     * Release milestone funds
     */
    public function releaseMilestone(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'campaignId' => 'required|string',
            'milestoneId' => 'required|string',
            'amount' => 'numeric|nullable'
        ]);
        
        // Get the campaign
        $campaign = Campaign::findOrFail($validated['campaignId']);
        
        // Check authorization
        $userId = auth()->id();
        if ($campaign->creator !== $userId && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'error' => 'Not authorized to release milestone funds'
            ], 403);
        }
        
        // Process milestone release
        $result = $this->transactionProcessor->releaseMilestoneFunding([
            'campaignId' => $validated['campaignId'],
            'milestoneId' => $validated['milestoneId'],
            'authorizedBy' => $userId,
            'amount' => $validated['amount'] ?? null
        ]);
        
        return response()->json($result);
    }
    
    /**
     * Cancel a recurring donation
     */
    public function cancelRecurring(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'donorId' => 'required|string',
            'campaignId' => 'required|string',
            'sourceSecret' => 'required|string'
        ]);
        
        // Check authorization
        $userId = auth()->id();
        $isAdmin = auth()->user()->hasRole('admin');
        
        if ($validated['donorId'] !== $userId && !$isAdmin) {
            return response()->json([
                'success' => false,
                'error' => 'Not authorized to cancel this recurring donation'
            ], 403);
        }
        
        // Process cancellation
        $result = $this->transactionProcessor->cancelRecurringDonation([
            'donorId' => $validated['donorId'],
            'campaignId' => $validated['campaignId'],
            'userId' => $userId,
            'sourceSecret' => $validated['sourceSecret']
        ]);
        
        return response()->json($result);
    }
}
```

### Donation Form Integration

Integrate the transaction system with your donation form:

```javascript
// client/components/DonationForm.js
import React, { useState } from 'react';
import { WalletService } from '../services/wallet';

export function DonationForm({ campaignId, userId }) {
  const [amount, setAmount] = useState('');
  const [isAnonymous, setIsAnonymous] = useState(false);
  const [message, setMessage] = useState('');
  const [isRecurring, setIsRecurring] = useState(false);
  const [frequency, setFrequency] = useState('monthly');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(null);
  
  const walletService = new WalletService();
  
  const handleSubmit = async (e) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);
    
    try {
      // Get user's encrypted private key from localStorage or secure storage
      const encryptedKey = localStorage.getItem('stellarKey');
      const password = prompt('Enter your wallet password');
      
      // Decrypt key
      const privateKey = await walletService.decryptPrivateKey(
        JSON.parse(encryptedKey),
        password
      );
      
      // Submit donation via API
      const response = await fetch('/api/donations', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          donorId: userId,
          campaignId,
          amount,
          sourceSecret: privateKey,
          isAnonymous,
          message,
          recurring: isRecurring,
          recurringFrequency: frequency
        })
      });
      
      const result = await response.json();
      
      if (!result.success) {
        throw new Error(result.error);
      }
      
      // Handle success
      alert('Donation successful!');
      // Reset form
      setAmount('');
      setMessage('');
      
    } catch (err) {
      setError(err.message);
    } finally {
      setIsSubmitting(false);
    }
  };
  
  return (
    <form onSubmit={handleSubmit}>
      {/* Form inputs */}
      {error && <div className="error">{error}</div>}
      <button type="submit" disabled={isSubmitting}>
        {isSubmitting ? 'Processing...' : 'Donate'}
      </button>
    </form>
  );
}
```

## Integrating with Backend Services

### Express.js API Routes

```javascript
// routes/donations.js
const express = require('express');
const router = express.Router();
const transactionService = require('../services/transaction');
const auth = require('../middleware/auth');

// Process a donation
router.post('/', auth.required, async (req, res) => {
  try {
    const {
      donorId,
      campaignId,
      amount,
      sourceSecret,
      isAnonymous,
      message,
      recurring,
      recurringFrequency
    } = req.body;
    
    // Ensure the authenticated user matches the donor
    if (req.user.id !== donorId) {
      return res.status(403).json({
        success: false,
        error: 'Not authorized to make donations for another user'
      });
    }
    
    const result = await transactionService.processDonation({
      donorId,
      campaignId,
      amount,
      sourceSecret,
      isAnonymous,
      message,
      recurring,
      recurringFrequency
    });
    
    return res.json(result);
  } catch (error) {
    console.error('Donation processing error:', error);
    return res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

// Get user transaction history
router.get('/user/:userId', auth.required, async (req, res) => {
  try {
    const { userId } = req.params;
    const { page, limit, type, status } = req.query;
    
    // Ensure the authenticated user matches the requested user
    if (req.user.id !== userId && !req.user.isAdmin) {
      return res.status(403).json({
        success: false,
        error: 'Not authorized to view another user\'s transactions'
      });
    }
    
    const result = await transactionService.getUserTransactionHistory(userId, {
      page: parseInt(page) || 1,
      limit: parseInt(limit) || 10,
      type,
      status
    });
    
    return res.json(result);
  } catch (error) {
    console.error('Transaction history error:', error);
    return res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

// Process milestone release
router.post('/milestones/release', auth.required, async (req, res) => {
  try {
    const {
      campaignId,
      milestoneId,
      amount
    } = req.body;
    
    // Get campaign details
    const campaign = await Campaign.findById(campaignId);
    
    // Check authorization
    if (campaign.creator.toString() !== req.user.id && !req.user.isAdmin) {
      return res.status(403).json({
        success: false,
        error: 'Not authorized to release milestone funds'
      });
    }
    
    const result = await transactionService.releaseMilestoneFunding({
      campaignId,
      milestoneId,
      authorizedBy: req.user.id,
      amount
    });
    
    return res.json(result);
  } catch (error) {
    console.error('Milestone release error:', error);
    return res.status(500).json({
      success: false,
      error: error.message
    });
  }
});

module.exports = router;
```

### PHP Laravel Controller Example

```php
<?php
// app/Http/Controllers/DonationController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\TransactionProcessor;

class DonationController extends Controller
{
    protected $transactionProcessor;
    
    public function __construct(TransactionProcessor $transactionProcessor)
    {
        $this->transactionProcessor = $transactionProcessor;
        $this->middleware('auth');
    }
    
    /**
     * Process a donation
     */
    public function process(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'campaignId' => 'required|string',
            'amount' => 'required|numeric|min:0.0000001',
            'sourceSecret' => 'required|string',
            'isAnonymous' => 'boolean',
            'message' => 'string|nullable',
            'recurring' => 'boolean',
            'recurringFrequency' => 'string|in:weekly,monthly,quarterly,annually'
        ]);
        
        // Ensure authenticated user is the donor
        $donorId = auth()->id();
        
        // Process donation
        $result = $this->transactionProcessor->processDonation([
            'donorId' => $donorId,
            'campaignId' => $validated['campaignId'],
            'amount' => $validated['amount'],
            'sourceSecret' => $validated['sourceSecret'],
            'isAnonymous' => $validated['isAnonymous'] ?? false,
            'message' => $validated['message'] ?? '',
            'recurring' => $validated['recurring'] ?? false,
            'frequency' => $validated['recurringFrequency'] ?? 'monthly'
        ]);
        
        return response()->json($result);
    }
    
    /**
     * Get user transaction history
     */
    public function userHistory(Request $request)
    {
        // Get query parameters
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 10);
        $type = $request->input('type');
        $status = $request->input('status');
        
        // Get user ID
        $userId = auth()->id();
        
        // Get transaction history
        $result = $this->transactionProcessor->getUserTransactionHistory($userId, [
            'page' => (int)$page,
            'limit' => (int)$limit,
            'type' => $type,
            'status' => $status
        ]);
        
        return response()->json($result);
    }
}
```
