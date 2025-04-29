// StellarWalletManager.js
import StellarSdk from 'stellar-sdk';

class StellarWalletManager {
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
    }

    /**
     * Generate a new Stellar keypair
     * @returns {Object} Object containing public key and encrypted secret key
     */
    generateKeypair() {
        const keypair = StellarSdk.Keypair.random();
        return {
            publicKey: keypair.publicKey(),
            secretKey: keypair.secret()
        };
    }

    /**
     * Create a new wallet for a user
     * @param {string} userId User's ID
     * @param {Object} db Database connection
     * @returns {Promise<Object>} Created wallet details
     */
    async createWallet(userId, db) {
        try {
            // Generate new keypair
            const keypair = this.generateKeypair();

            // Create wallet record
            const wallet = {
                userId: userId,
                publicKey: keypair.publicKey,
                secretKey: keypair.secretKey, // In production, this should be encrypted
                network: this.useTestnet ? 'testnet' : 'public',
                createdAt: new Date(),
                status: 'active'
            };

            // Save to database
            const result = await db.collection('wallets').insertOne(wallet);

            // If using testnet, fund the account
            if (this.useTestnet) {
                await this.fundTestnetAccount(keypair.publicKey);
            }

            return {
                success: true,
                wallet: {
                    id: result.insertedId,
                    publicKey: keypair.publicKey,
                    network: wallet.network
                }
            };
        } catch (error) {
            console.error('Error creating wallet:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Fund a testnet account using Friendbot
     * @param {string} publicKey Account public key
     * @returns {Promise<Object>} Funding result
     */
    async fundTestnetAccount(publicKey) {
        try {
            const response = await fetch(
                `https://friendbot.stellar.org?addr=${encodeURIComponent(publicKey)}`
            );
            return await response.json();
        } catch (error) {
            console.error('Error funding testnet account:', error);
            throw error;
        }
    }

    /**
     * Get account details and balances
     * @param {string} publicKey Account public key
     * @returns {Promise<Object>} Account details
     */
    async getAccountDetails(publicKey) {
        try {
            const account = await this.server.loadAccount(publicKey);
            
            return {
                success: true,
                account: {
                    id: account.id,
                    sequence: account.sequence,
                    balances: account.balances,
                    subentryCount: account.subentry_count,
                    lastModified: account.last_modified_time
                }
            };
        } catch (error) {
            if (error.response && error.response.status === 404) {
                return {
                    success: false,
                    error: 'Account not found'
                };
            }
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Check if an account exists and is funded
     * @param {string} publicKey Account public key
     * @returns {Promise<boolean>} Whether the account exists and is funded
     */
    async isAccountFunded(publicKey) {
        try {
            await this.server.loadAccount(publicKey);
            return true;
        } catch (error) {
            return false;
        }
    }

    /**
     * Calculate minimum balance requirement for an account
     * @param {Object} account Stellar account object
     * @returns {number} Minimum balance in XLM
     */
    calculateMinBalance(account) {
        // Base reserve is 1 XLM
        const baseReserve = 1;
        // Additional reserve per entry is 0.5 XLM
        const additionalReserve = account.subentry_count * 0.5;
        
        return baseReserve + additionalReserve;
    }
}

export default StellarWalletManager; 