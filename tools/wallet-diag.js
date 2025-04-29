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
