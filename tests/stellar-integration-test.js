// stellar-integration-test.js
import StellarWalletManager from '../lib/StellarWalletManager.js';
import StellarTransactionBuilder from '../lib/StellarTransactionBuilder.js';
import StellarFeeManager from '../lib/StellarFeeManager.js';
import { MongoClient } from 'mongodb';

async function runTests() {
    console.log('Starting Stellar integration tests...');
    
    // Initialize components
    const walletManager = new StellarWalletManager({
        useTestnet: true,
        enableLogging: true
    });
    
    const transactionBuilder = new StellarTransactionBuilder({
        useTestnet: true,
        enableLogging: true
    });
    
    // Connect to MongoDB
    const mongoClient = await MongoClient.connect('mongodb://localhost:27017');
    const db = mongoClient.db('givehub_test');
    
    try {
        // Test 1: Create a new wallet
        console.log('\nTest 1: Creating new wallet...');
        const walletResult = await walletManager.createWallet('test_user_1', db);
        
        if (!walletResult.success) {
            throw new Error(`Failed to create wallet: ${walletResult.error}`);
        }
        
        console.log('Wallet created successfully:', walletResult.wallet.publicKey);
        
        // Test 2: Fund testnet account
        console.log('\nTest 2: Funding testnet account...');
        const fundingResult = await walletManager.fundTestnetAccount(walletResult.wallet.publicKey);
        console.log('Account funded successfully');
        
        // Test 3: Get account details
        console.log('\nTest 3: Getting account details...');
        const accountDetails = await walletManager.getAccountDetails(walletResult.wallet.publicKey);
        
        if (!accountDetails.success) {
            throw new Error(`Failed to get account details: ${accountDetails.error}`);
        }
        
        console.log('Account details retrieved:', {
            id: accountDetails.account.id,
            balances: accountDetails.account.balances
        });
        
        // Test 4: Create a second wallet for payment testing
        console.log('\nTest 4: Creating second wallet for payment testing...');
        const wallet2Result = await walletManager.createWallet('test_user_2', db);
        
        if (!wallet2Result.success) {
            throw new Error(`Failed to create second wallet: ${wallet2Result.error}`);
        }
        
        console.log('Second wallet created successfully:', wallet2Result.wallet.publicKey);
        
        // Get the wallet records from database to get secret keys
        const wallet1 = await db.collection('wallets').findOne({ userId: 'test_user_1' });
        const wallet2 = await db.collection('wallets').findOne({ userId: 'test_user_2' });
        
        // Test 5: Create and submit a payment transaction
        console.log('\nTest 5: Creating and submitting payment transaction...');
        const paymentTx = await transactionBuilder.createPayment({
            sourceSecret: wallet1.secretKey,
            destinationAddress: wallet2.wallet.publicKey,
            amount: '10',
            memo: 'Test payment',
            feePriority: 'medium'
        });
        
        const paymentResult = await transactionBuilder.submitTransaction(paymentTx, {
            sourceSecret: wallet1.secretKey
        });
        
        if (!paymentResult.success) {
            throw new Error(`Failed to submit payment: ${paymentResult.error}`);
        }
        
        console.log('Payment submitted successfully:', paymentResult.result.hash);
        
        // Test 6: Verify transaction fee management
        console.log('\nTest 6: Testing fee management...');
        const feeManager = new StellarFeeManager({
            useTestnet: true,
            enableLogging: true
        });
        
        const feeStats = await feeManager.getFeeStatistics();
        console.log('Current fee statistics:', {
            networkCongestion: feeStats.congestion,
            recommendedFees: feeStats.recommendedFees
        });
        
        // Test 7: Clean up test data
        console.log('\nTest 7: Cleaning up test data...');
        await db.collection('wallets').deleteMany({ 
            userId: { $in: ['test_user_1', 'test_user_2'] }
        });
        
        console.log('Test data cleaned up successfully');
        
        console.log('\nAll tests completed successfully!');
        
    } catch (error) {
        console.error('Test failed:', error);
        throw error;
    } finally {
        await mongoClient.close();
    }
}

// Run the tests
runTests()
    .then(() => {
        console.log('Tests completed successfully');
        process.exit(0);
    })
    .catch(error => {
        console.error('Tests failed:', error);
        process.exit(1);
    }); 