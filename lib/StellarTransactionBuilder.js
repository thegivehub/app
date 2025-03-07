// StellarTransactionBuilder.js
// A module for building, signing, and submitting Stellar blockchain transactions

import StellarSdk from 'stellar-sdk';

class StellarTransactionBuilder {
  constructor(config = {}) {
    // Default to testnet if not specified
    this.network = config.useTestnet !== false ? 
      StellarSdk.Networks.TESTNET : 
      StellarSdk.Networks.PUBLIC;
    
    // Set up server connection
    this.horizonUrl = config.horizonUrl || 
      (this.network === StellarSdk.Networks.TESTNET ? 
        'https://horizon-testnet.stellar.org' : 
        'https://horizon.stellar.org');
    
    this.server = new StellarSdk.Server(this.horizonUrl);
    
    // Fee settings
    this.baseFee = config.baseFee || StellarSdk.BASE_FEE;
    
    // Timeout settings (default 30 seconds)
    this.timeout = config.timeout || 30;

    // Initialize logging
    this.enableLogging = config.enableLogging || false;
  }

  /**
   * Log messages if logging is enabled
   * @param {string} message - Message to log
   * @param {*} data - Optional data to log
   */
  log(message, data = null) {
    if (this.enableLogging) {
      if (data) {
        console.log(`[StellarTransactionBuilder] ${message}`, data);
      } else {
        console.log(`[StellarTransactionBuilder] ${message}`);
      }
    }
  }

  /**
   * Load an account to use as transaction source
   * @param {string} publicKey - Public key of the account
   * @returns {Promise<Object>} - Account object
   */
  async loadAccount(publicKey) {
    try {
      this.log(`Loading account ${publicKey}`);
      return await this.server.loadAccount(publicKey);
    } catch (error) {
      this.log('Error loading account', error);
      throw new Error(`Failed to load account: ${error.message}`);
    }
  }

  /**
   * Create a simple payment transaction
   * @param {Object} params - Payment parameters
   * @returns {Promise<Object>} - Transaction object
   */
  async createPayment({
    sourceSecret,
    destinationAddress,
    amount,
    asset = StellarSdk.Asset.native(),
    memo = null
  }) {
    try {
      const sourceKeypair = StellarSdk.Keypair.fromSecret(sourceSecret);
      const sourcePublicKey = sourceKeypair.publicKey();
      
      // Load the source account
      const sourceAccount = await this.loadAccount(sourcePublicKey);
      
      // Start building the transaction
      let transaction = new StellarSdk.TransactionBuilder(sourceAccount, {
        fee: this.baseFee,
        networkPassphrase: this.network
      });
      
      // Add the payment operation
      transaction = transaction.addOperation(
        StellarSdk.Operation.payment({
          destination: destinationAddress,
          asset: asset,
          amount: amount.toString()
        })
      );
      
      // Add memo if provided
      if (memo) {
        if (typeof memo === 'string') {
          transaction = transaction.addMemo(StellarSdk.Memo.text(memo));
        } else if (typeof memo === 'object' && memo.type && memo.value) {
          switch (memo.type.toLowerCase()) {
            case 'text':
              transaction = transaction.addMemo(StellarSdk.Memo.text(memo.value));
              break;
            case 'id':
              transaction = transaction.addMemo(StellarSdk.Memo.id(memo.value));
              break;
            case 'hash':
              transaction = transaction.addMemo(StellarSdk.Memo.hash(memo.value));
              break;
            case 'return':
              transaction = transaction.addMemo(StellarSdk.Memo.return(memo.value));
              break;
            default:
              this.log('Invalid memo type, using text');
              transaction = transaction.addMemo(StellarSdk.Memo.text(memo.value));
          }
        }
      }
      
      // Set timeout and build the transaction
      transaction = transaction.setTimeout(this.timeout).build();
      
      // Sign the transaction
      transaction.sign(sourceKeypair);
      
      this.log('Payment transaction created successfully');
      return transaction;
    } catch (error) {
      this.log('Error creating payment transaction', error);
      throw new Error(`Failed to create payment transaction: ${error.message}`);
    }
  }

  /**
   * Create a donation transaction with campaign-specific metadata
   * @param {Object} params - Donation parameters
   * @returns {Promise<Object>} - Transaction object
   */
  async createDonation({
    sourceSecret,
    campaignAddress,
    amount,
    campaignId,
    donorId = null,
    isAnonymous = false,
    asset = StellarSdk.Asset.native(),
    message = ""
  }) {
    try {
      // Create memo with campaign ID and optional donor ID
      let memoText = `campaign:${campaignId}`;
      if (donorId && !isAnonymous) {
        memoText += `,donor:${donorId}`;
      }
      if (message) {
        // Ensure the memo doesn't exceed Stellar's 28-character limit
        // If we need more data, we could store the message elsewhere and just reference it
        const availableChars = 28 - memoText.length - 1; // -1 for the comma
        if (availableChars > 0) {
          const truncatedMessage = message.substring(0, availableChars);
          memoText += `,msg:${truncatedMessage}`;
        }
      }

      // If memo is still too long, truncate it to fit Stellar's limit
      if (memoText.length > 28) {
        memoText = memoText.substring(0, 28);
      }

      // Create a payment transaction with the campaign memo
      return await this.createPayment({
        sourceSecret,
        destinationAddress: campaignAddress,
        amount,
        asset,
        memo: memoText
      });
    } catch (error) {
      this.log('Error creating donation transaction', error);
      throw new Error(`Failed to create donation transaction: ${error.message}`);
    }
  }

  /**
   * Create a transaction to set up a recurring donation
   * @param {Object} params - Recurring donation parameters
   * @returns {Promise<Object>} - Transaction object
   */
  async createRecurringDonationSetup({
    sourceSecret,
    campaignAddress,
    amount,
    campaignId,
    donorId,
    frequency = "monthly", // "weekly", "monthly", "quarterly", "annually"
    startDate = new Date(),
    asset = StellarSdk.Asset.native()
  }) {
    try {
      const sourceKeypair = StellarSdk.Keypair.fromSecret(sourceSecret);
      const sourcePublicKey = sourceKeypair.publicKey();
      
      // Load the source account
      const sourceAccount = await this.loadAccount(sourcePublicKey);
      
      // Start building the transaction
      let transaction = new StellarSdk.TransactionBuilder(sourceAccount, {
        fee: this.baseFee,
        networkPassphrase: this.network
      });
      
      // Add initial payment operation
      transaction = transaction.addOperation(
        StellarSdk.Operation.payment({
          destination: campaignAddress,
          asset: asset,
          amount: amount.toString()
        })
      );
      
      // Create memo with recurring donation metadata
      const memoText = `recur:${campaignId},${frequency}`;
      transaction = transaction.addMemo(StellarSdk.Memo.text(memoText));
      
      // Set timeout and build the transaction
      transaction = transaction.setTimeout(this.timeout).build();
      
      // Sign the transaction
      transaction.sign(sourceKeypair);
      
      this.log('Recurring donation setup transaction created successfully');
      
      // Return both the transaction and metadata for the recurring setup
      return {
        transaction,
        recurringMetadata: {
          donorId,
          campaignId,
          frequency,
          amount: amount.toString(),
          asset: asset.isNative() ? 'XLM' : `${asset.getCode()}:${asset.getIssuer()}`,
          startDate: startDate.toISOString(),
          nextPaymentDate: this.calculateNextPaymentDate(startDate, frequency)
        }
      };
    } catch (error) {
      this.log('Error creating recurring donation setup', error);
      throw new Error(`Failed to create recurring donation setup: ${error.message}`);
    }
  }

  /**
   * Calculate the next payment date based on frequency
   * @param {Date} startDate - Start date
   * @param {string} frequency - Payment frequency 
   * @returns {string} - ISO string of next payment date
   */
  calculateNextPaymentDate(startDate, frequency) {
    const date = new Date(startDate);
    
    switch (frequency.toLowerCase()) {
      case 'weekly':
        date.setDate(date.getDate() + 7);
        break;
      case 'monthly':
        date.setMonth(date.getMonth() + 1);
        break;
      case 'quarterly':
        date.setMonth(date.getMonth() + 3);
        break;
      case 'annually':
        date.setFullYear(date.getFullYear() + 1);
        break;
      default:
        date.setMonth(date.getMonth() + 1); // Default to monthly
    }
    
    return date.toISOString();
  }

  /**
   * Create a transaction to cancel a recurring donation
   * @param {Object} params - Cancellation parameters
   * @returns {Promise<Object>} - Transaction object
   */
  async createRecurringDonationCancellation({
    sourceSecret,
    campaignAddress,
    campaignId,
    donorId
  }) {
    try {
      const sourceKeypair = StellarSdk.Keypair.fromSecret(sourceSecret);
      const sourcePublicKey = sourceKeypair.publicKey();
      
      // Load the source account
      const sourceAccount = await this.loadAccount(sourcePublicKey);
      
      // Start building the transaction
      let transaction = new StellarSdk.TransactionBuilder(sourceAccount, {
        fee: this.baseFee,
        networkPassphrase: this.network
      });
      
      // Add a payment of minimum amount (0.0000001 XLM) to carry the cancellation memo
      transaction = transaction.addOperation(
        StellarSdk.Operation.payment({
          destination: campaignAddress,
          asset: StellarSdk.Asset.native(),
          amount: "0.0000001" // Minimum amount for transaction
        })
      );
      
      // Create memo with cancellation metadata
      const memoText = `cancel:${campaignId},${donorId}`;
      transaction = transaction.addMemo(StellarSdk.Memo.text(memoText));
      
      // Set timeout and build the transaction
      transaction = transaction.setTimeout(this.timeout).build();
      
      // Sign the transaction
      transaction.sign(sourceKeypair);
      
      this.log('Recurring donation cancellation transaction created successfully');
      return transaction;
    } catch (error) {
      this.log('Error creating recurring donation cancellation', error);
      throw new Error(`Failed to create recurring donation cancellation: ${error.message}`);
    }
  }

  /**
   * Submit a transaction to the Stellar network
   * @param {Object} transaction - Signed transaction object 
   * @returns {Promise<Object>} - Transaction result
   */
  async submitTransaction(transaction) {
    try {
      this.log('Submitting transaction to network');
      const result = await this.server.submitTransaction(transaction);
      this.log('Transaction submitted successfully', result);
      return {
        success: true,
        result: result
      };
    } catch (error) {
      this.log('Error submitting transaction', error);
      
      // Extract detailed error information from Stellar's response
      let errorDetail = 'Unknown error';
      if (error.response && error.response.data && error.response.data.extras) {
        errorDetail = error.response.data.extras.result_codes 
          ? JSON.stringify(error.response.data.extras.result_codes) 
          : error.response.data.extras.reason || errorDetail;
      }
      
      return {
        success: false,
        error: error.message,
        errorDetail: errorDetail
      };
    }
  }

  /**
   * Create an escrow account for milestone-based funding
   * @param {Object} params - Escrow parameters
   * @returns {Promise<Object>} - Escrow setup result
   */
  async createMilestoneEscrow({
    sourceSecret,
    campaignId,
    milestones = [],
    initialFunding = "0"
  }) {
    try {
      const sourceKeypair = StellarSdk.Keypair.fromSecret(sourceSecret);
      const sourcePublicKey = sourceKeypair.publicKey();
      
      // Generate a new keypair for the escrow account
      const escrowKeypair = StellarSdk.Keypair.random();
      const escrowPublicKey = escrowKeypair.publicKey();
      const escrowSecretKey = escrowKeypair.secret();
      
      // Load the source account
      const sourceAccount = await this.loadAccount(sourcePublicKey);
      
      // Start building the transaction
      let transaction = new StellarSdk.TransactionBuilder(sourceAccount, {
        fee: this.baseFee,
        networkPassphrase: this.network
      });
      
      // Create the escrow account with initial funding
      // Minimum balance is 1 XLM + 0.5 XLM per additional signer
      const minBalance = parseFloat(initialFunding) >= 1 ? initialFunding : "1";
      
      transaction = transaction.addOperation(
        StellarSdk.Operation.createAccount({
          destination: escrowPublicKey,
          startingBalance: minBalance
        })
      );
      
      // Add memo with escrow metadata
      const memoText = `escrow:${campaignId}`;
      transaction = transaction.addMemo(StellarSdk.Memo.text(memoText));
      
      // Set timeout and build the transaction
      transaction = transaction.setTimeout(this.timeout).build();
      
      // Sign the transaction
      transaction.sign(sourceKeypair);
      
      // Submit the transaction
      const result = await this.submitTransaction(transaction);
      if (!result.success) {
        throw new Error(`Failed to create escrow account: ${result.error}`);
      }
      
      this.log('Escrow account created successfully');
      
      // Prepare milestones metadata
      const milestonesWithDates = milestones.map((milestone, index) => {
        // Calculate release date if provided
        let releaseDate = null;
        if (milestone.releaseDate) {
          releaseDate = new Date(milestone.releaseDate).toISOString();
        } else if (milestone.releaseDays) {
          const date = new Date();
          date.setDate(date.getDate() + milestone.releaseDays);
          releaseDate = date.toISOString();
        }
        
        return {
          id: milestone.id || `milestone-${index + 1}`,
          title: milestone.title || `Milestone ${index + 1}`,
          amount: milestone.amount || "0",
          releaseDate,
          conditions: milestone.conditions || [],
          status: 'pending'
        };
      });
      
      return {
        success: true,
        escrowAccountId: escrowPublicKey,
        escrowSecretKey: escrowSecretKey,
        campaignId,
        milestones: milestonesWithDates,
        transactionHash: result.result.hash
      };
    } catch (error) {
      this.log('Error creating milestone escrow', error);
      throw new Error(`Failed to create milestone escrow: ${error.message}`);
    }
  }

  /**
   * Release funds from a milestone escrow
   * @param {Object} params - Release parameters
   * @returns {Promise<Object>} - Transaction result
   */
  async releaseMilestoneFunds({
    escrowSecret,
    destinationAddress,
    amount,
    milestoneId,
    campaignId
  }) {
    try {
      // Create payment transaction from escrow to campaign
      const transaction = await this.createPayment({
        sourceSecret: escrowSecret,
        destinationAddress,
        amount,
        memo: `milestone:${campaignId},${milestoneId}`
      });
      
      // Submit the transaction
      return await this.submitTransaction(transaction);
    } catch (error) {
      this.log('Error releasing milestone funds', error);
      throw new Error(`Failed to release milestone funds: ${error.message}`);
    }
  }

  /**
   * Check the status of a transaction
   * @param {string} transactionHash - Hash of the transaction 
   * @returns {Promise<Object>} - Transaction details
   */
  async checkTransaction(transactionHash) {
    try {
      this.log(`Checking transaction ${transactionHash}`);
      const transaction = await this.server.transactions().transaction(transactionHash).call();
      return {
        success: true,
        transaction
      };
    } catch (error) {
      this.log('Error checking transaction', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Get account transactions
   * @param {string} accountId - Stellar account ID
   * @param {Object} options - Query options 
   * @returns {Promise<Array>} - List of transactions
   */
  async getAccountTransactions(accountId, options = {}) {
    try {
      this.log(`Getting transactions for account ${accountId}`);
      
      let builder = this.server.transactions().forAccount(accountId);
      
      // Apply options
      if (options.limit) {
        builder = builder.limit(options.limit);
      }
      
      if (options.cursor) {
        builder = builder.cursor(options.cursor);
      }
      
      if (options.order === 'asc') {
        builder = builder.order('asc');
      } else {
        builder = builder.order('desc');
      }
      
      const transactions = await builder.call();
      
      return {
        success: true,
        transactions: transactions.records,
        next: transactions.next,
        prev: transactions.prev
      };
    } catch (error) {
      this.log('Error getting account transactions', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Get campaign transactions using memo filter
   * @param {string} campaignId - ID of the campaign 
   * @returns {Promise<Array>} - List of campaign transactions
   */
  async getCampaignTransactions(campaignId, accountId) {
    try {
      this.log(`Getting transactions for campaign ${campaignId}`);
      
      if (!accountId) {
        throw new Error('Campaign account ID is required');
      }
      
      // Get all transactions for the account
      const result = await this.getAccountTransactions(accountId, { limit: 100 });
      
      if (!result.success) {
        throw new Error(`Failed to get transactions: ${result.error}`);
      }
      
      // Filter transactions by campaign ID in memo
      const campaignTransactions = result.transactions.filter(tx => {
        if (!tx.memo) return false;
        
        // Check different memo formats
        if (tx.memo.startsWith(`campaign:${campaignId}`)) return true;
        if (tx.memo.includes(`campaign:${campaignId}`)) return true;
        if (tx.memo.includes(`escrow:${campaignId}`)) return true;
        if (tx.memo.includes(`milestone:${campaignId}`)) return true;
        if (tx.memo.includes(`recur:${campaignId}`)) return true;
        
        return false;
      });
      
      return {
        success: true,
        transactions: campaignTransactions,
        total: campaignTransactions.length
      };
    } catch (error) {
      this.log('Error getting campaign transactions', error);
      return {
        success: false,
        error: error.message
      };
    }
  }
}

export default StellarTransactionBuilder;
