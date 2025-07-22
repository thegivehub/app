// StellarTransactionBuilder.js
// A module for building, signing, and submitting Stellar blockchain transactions

import StellarSdk from 'stellar-sdk';
import StellarFeeManager from './StellarFeeManager.js';

class StellarTransactionBuilder {
  constructor(config = {}) {
    this.useTestnet = config.useTestnet !== false;
    this.server = new StellarSdk.Server(
      this.useTestnet ? 
        'https://horizon-testnet.stellar.org' : 
        'https://horizon.stellar.org'
    );
    this.networkPassphrase = this.useTestnet ?
      StellarSdk.Networks.TESTNET :
      StellarSdk.Networks.PUBLIC;
    
    // Initialize fee manager
    this.feeManager = new StellarFeeManager({
      useTestnet: this.useTestnet,
      enableLogging: config.enableLogging || false
    });
    
    // Store fee priorities
    this.feePriorities = config.feePriorities || {
      donation: 'medium',
      milestone: 'high',
      escrow: 'high',
      recurring: 'medium'
    };
    
    // Set up retry configuration
    this.maxRetries = config.maxRetries || 3;
    this.retryDelay = config.retryDelay || 2000; // ms
  }

  /**
   * Build a transaction with proper fee and timeout
   * @param {string} sourceSecret Source account secret key
   * @param {Function} operationBuilder Function that adds operations to the transaction
   * @param {Object} options Transaction options
   * @returns {Promise<Transaction>} Built transaction
   */
  async buildTransaction(sourceSecret, operationBuilder, options = {}) {
    try {
      const sourceKeypair = StellarSdk.Keypair.fromSecret(sourceSecret);
      const sourceAccount = await this.server.loadAccount(sourceKeypair.publicKey());
      
      // Get recommended fee based on priority
      const baseFee = await this.feeManager.getRecommendedFee({
        priorityLevel: options.feePriority || 'medium'
      });
      
      // Start transaction builder
      let transaction = new StellarSdk.TransactionBuilder(sourceAccount, {
        fee: baseFee.toString(),
        networkPassphrase: this.networkPassphrase
      });
      
      // Add operations using the provided builder function
      transaction = await operationBuilder(transaction);
      
      // Add memo if provided
      if (options.memo) {
        if (typeof options.memo === 'string') {
          transaction = transaction.addMemo(StellarSdk.Memo.text(options.memo));
        } else {
          transaction = transaction.addMemo(options.memo);
        }
      }
      
      // Set timeout and build
      transaction = transaction.setTimeout(30).build();
      
      // Sign the transaction
      transaction.sign(sourceKeypair);

      return transaction;
    } catch (error) {
      console.error('Error building transaction:', error);
      throw error;
    }
  }

  /**
   * Add additional signatures to a transaction
   * @param {Transaction} transaction Transaction to sign
   * @param {string[]} signerSecrets Array of secret keys
   */
  addSignatures(transaction, signerSecrets = []) {
    signerSecrets.forEach(secret => {
      const keypair = StellarSdk.Keypair.fromSecret(secret);
      transaction.sign(keypair);
    });
  }

  /**
   * Submit a transaction with retry logic
   * @param {Transaction} transaction Built transaction
   * @param {Object} options Submission options
   * @returns {Promise<Object>} Submission result
   */
  async submitTransaction(transaction, options = {}) {
    let lastError;
    const signerSecrets = options.signerSecrets || [];

    if (signerSecrets.length) {
      this.addSignatures(transaction, signerSecrets);
    }
    
    for (let attempt = 1; attempt <= this.maxRetries; attempt++) {
      try {
        const result = await this.server.submitTransaction(transaction);
        
        return {
          success: true,
          result: result
        };
      } catch (error) {
        lastError = error;
        
        // Check if error is recoverable
        if (!this.isRecoverableError(error)) {
          break;
        }
        
        console.log(`Transaction submission attempt ${attempt} failed: ${error.message}`);
        
        if (attempt < this.maxRetries) {
          // Exponential backoff with jitter
          const delay = Math.min(
            this.retryDelay * Math.pow(2, attempt - 1) + Math.random() * 1000,
            30000
          );
          await new Promise(resolve => setTimeout(resolve, delay));
          
          // If fee-related error, try with higher fee
          if (this.feeManager.isFeeRelatedError(error) && options.sourceSecret) {
            try {
              transaction = await this.retryWithHigherFee(transaction, options.sourceSecret, signerSecrets);
            } catch (feeError) {
              console.error('Error creating fee bump transaction:', feeError);
            }
          }
        }
      }
    }
    
    return {
      success: false,
      error: lastError.message,
      errorDetail: lastError
    };
  }

  /**
   * Check if an error is recoverable
   * @param {Error} error Transaction error
   * @returns {boolean} Whether the error is recoverable
   */
  isRecoverableError(error) {
    if (!error.response) {
      return true; // Network errors are recoverable
    }
    
    if (error.response.data?.extras?.result_codes) {
      const codes = error.response.data.extras.result_codes;
      
      // These error codes are recoverable
      const recoverableCodes = [
        'tx_bad_seq',         // Can reload sequence number
        'tx_too_late',        // Can retry
        'tx_insufficient_fee' // Can retry with higher fee
      ];
      
      return recoverableCodes.includes(codes.transaction);
    }
    
    return false;
  }

  /**
   * Retry a transaction with a higher fee
   * @param {Transaction} transaction Original transaction
   * @param {string} sourceSecret Source account secret key
   * @returns {Promise<Transaction>} New transaction with higher fee
   */
  async retryWithHigherFee(transaction, sourceSecret, signerSecrets = []) {
    const sourceKeypair = StellarSdk.Keypair.fromSecret(sourceSecret);
    
    // Get a higher fee
    const higherFee = await this.feeManager.getRecommendedFee({
      priorityLevel: 'high',
      forceRefresh: true
    });
    
    // Create fee bump transaction
    const bumpTransaction = StellarSdk.TransactionBuilder.buildFeeBumpTransaction(
      sourceKeypair,
      higherFee.toString(),
      transaction,
      this.networkPassphrase
    );
    
    // Sign and return
    bumpTransaction.sign(sourceKeypair);
    signerSecrets.forEach(secret => {
      const keypair = StellarSdk.Keypair.fromSecret(secret);
      bumpTransaction.sign(keypair);
    });
    return bumpTransaction;
  }

  /**
   * Create a payment transaction
   * @param {Object} params Payment parameters
   * @returns {Promise<Transaction>} Built transaction
   */
  async createPayment(params) {
    const {
      sourceSecret,
      destinationAddress,
      amount,
      asset = StellarSdk.Asset.native(),
      memo = null,
      feePriority = 'medium'
    } = params;
    
    return this.buildTransaction(
      sourceSecret,
      async (transaction) => {
        return transaction.addOperation(
          StellarSdk.Operation.payment({
            destination: destinationAddress,
            asset: asset,
            amount: amount.toString()
          })
        );
      },
      {
        memo,
        feePriority
      }
    );
  }

  /**
   * Create a new account
   * @param {Object} params Account creation parameters
   * @returns {Promise<Transaction>} Built transaction
   */
  async createAccount(params) {
    const {
      sourceSecret,
      destinationPublicKey,
      startingBalance,
      memo = null,
      feePriority = 'high'
    } = params;
    
    return this.buildTransaction(
      sourceSecret,
      async (transaction) => {
        return transaction.addOperation(
          StellarSdk.Operation.createAccount({
            destination: destinationPublicKey,
            startingBalance: startingBalance.toString()
          })
        );
      },
      {
        memo,
        feePriority
      }
    );
  }

  /**
   * Calculate next payment date for recurring donations
   * @param {Date} startDate Start date
   * @param {string} frequency Payment frequency
   * @returns {Date} Next payment date
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
        throw new Error(`Invalid frequency: ${frequency}`);
    }
    
    return date;
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
