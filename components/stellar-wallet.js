// stellar-wallet.js
// Web component for managing Stellar wallets

class StellarWallet extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        
        // Initialize properties
        this.wallet = null;
        this.isTestnet = true;
        
        this.render();
    }
    
    static get observedAttributes() {
        return ['user-id', 'testnet'];
    }
    
    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'user-id' && newValue) {
            this.loadWallet(newValue);
        }
        if (name === 'testnet') {
            this.isTestnet = newValue === 'true';
            this.render();
        }
    }
    
    async loadWallet(userId) {
        try {
            // Use the proper API endpoint
            const response = await fetch(`/api.php/wallets?id=${userId}`);
            const data = await response.json();
            
            if (data && data._id) {
                // Single wallet object returned
                this.wallet = {
                    publicKey: data.publicKey,
                    balance: data.balance || '0.0000000',
                    network: data.network || this.isTestnet ? 'testnet' : 'public'
                };
                console.log('Loaded wallet:', this.wallet);
                this.render();
            } else if (data && data.success && data.wallet) {
                // Success response with wallet object
                this.wallet = data.wallet;
                console.log('Loaded wallet:', this.wallet);
                this.render();
            } else {
                this.showError(data.error || 'Failed to load wallet');
            }
        } catch (error) {
            console.error('Error loading wallet:', error);
            this.showError('Failed to load wallet');
        }
    }
    
    async createWallet() {
        try {
            const userId = this.getAttribute('user-id');
            if (!userId) {
                this.showError('User ID is required');
                return;
            }
            
            // Use the proper API endpoint
            const response = await fetch('/api.php/wallets/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    userId,
                    testnet: this.isTestnet
                })
            });
            
            const data = await response.json();
            
            if (data && data.success) {
                this.wallet = data.wallet;
                this.render();
                this.dispatchEvent(new CustomEvent('wallet-created', {
                    detail: this.wallet
                }));
            } else {
                this.showError(data.error || 'Failed to create wallet');
            }
        } catch (error) {
            console.error('Error creating wallet:', error);
            this.showError('Failed to create wallet');
        }
    }
    
    async fundTestnetAccount() {
        try {
            if (!this.wallet) return;
            
            // Use the proper API endpoint
            const response = await fetch('/api.php/wallets/fund', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    publicKey: this.wallet.publicKey
                })
            });
            
            const data = await response.json();
            
            if (data && data.success) {
                this.showSuccess('Account funded successfully');
                await this.loadWallet(this.getAttribute('user-id'));
            } else {
                this.showError(data.error || 'Failed to fund account');
            }
        } catch (error) {
            console.error('Error funding account:', error);
            this.showError('Failed to fund account');
        }
    }
    
    showError(message) {
        const errorElement = this.shadowRoot.querySelector('.error');
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        setTimeout(() => {
            errorElement.style.display = 'none';
        }, 5000);
    }
    
    showSuccess(message) {
        const successElement = this.shadowRoot.querySelector('.success');
        successElement.textContent = message;
        successElement.style.display = 'block';
        setTimeout(() => {
            successElement.style.display = 'none';
        }, 5000);
    }
    
    render() {
        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: block;
                    font-family: Arial, sans-serif;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    margin: 10px;
                }
                
                .wallet-container {
                    max-width: 600px;
                }
                
                .wallet-info {
                    margin: 15px 0;
                    padding: 15px;
                    background: #f5f5f5;
                    border-radius: 4px;
                }
                
                .balance {
                    font-size: 24px;
                    font-weight: bold;
                    color: #2196F3;
                    margin: 10px 0;
                }
                
                .address {
                    word-break: break-all;
                    font-family: monospace;
                    padding: 10px;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
                
                button {
                    background: #2196F3;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 4px;
                    cursor: pointer;
                    margin: 5px;
                }
                
                button:hover {
                    background: #1976D2;
                }
                
                button:disabled {
                    background: #ccc;
                    cursor: not-allowed;
                }
                
                .error {
                    color: #f44336;
                    padding: 10px;
                    margin: 10px 0;
                    border: 1px solid #f44336;
                    border-radius: 4px;
                    display: none;
                }
                
                .success {
                    color: #4CAF50;
                    padding: 10px;
                    margin: 10px 0;
                    border: 1px solid #4CAF50;
                    border-radius: 4px;
                    display: none;
                }
                
                .network-badge {
                    display: inline-block;
                    padding: 5px 10px;
                    border-radius: 15px;
                    font-size: 12px;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                
                .testnet {
                    background: #FFC107;
                    color: #000;
                }
                
                .mainnet {
                    background: #4CAF50;
                    color: #fff;
                }
            </style>
            
            <div class="wallet-container">
                <div class="network-badge ${this.isTestnet ? 'testnet' : 'mainnet'}">
                    ${this.isTestnet ? 'TESTNET' : 'MAINNET'}
                </div>
                
                <div class="error"></div>
                <div class="success"></div>
                
                ${this.wallet ? `
                    <div class="wallet-info">
                        <h3>Wallet Details</h3>
                        <div class="balance">
                            ${this.formatBalance()} XLM
                        </div>
                        <p>Public Key:</p>
                        <div class="address">
                            ${this.wallet.publicKey}
                        </div>
                        ${this.isTestnet ? `
                            <button @click="${this.fundTestnetAccount.bind(this)}">
                                Fund Testnet Account
                            </button>
                        ` : ''}
                    </div>
                ` : `
                    <button @click="${this.createWallet.bind(this)}">
                        Create New Wallet
                    </button>
                `}
            </div>
        `;
        
        // Add event listeners
        this.shadowRoot.querySelectorAll('button').forEach(button => {
            const clickHandler = button.getAttribute('@click');
            if (clickHandler) {
                const methodMatch = clickHandler.match(/this\.(.*?)[\(\)]/);
                if (methodMatch && methodMatch[1]) {
                    const method = methodMatch[1];
                    button.addEventListener('click', this[method].bind(this));
                }
            }
        });
    }
    
    formatBalance() {
        if (!this.wallet || !this.wallet.balance) return '0.0000000';
        return parseFloat(this.wallet.balance).toFixed(7);
    }
}

customElements.define('stellar-wallet', StellarWallet); 
