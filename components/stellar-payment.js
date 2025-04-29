// stellar-payment.js
// Web component for making Stellar payments

class StellarPayment extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        
        // Initialize properties
        this.wallet = null;
        this.isTestnet = true;
        this.isProcessing = false;
        
        this.render();
    }
    
    static get observedAttributes() {
        return ['wallet-id', 'testnet'];
    }
    
    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'wallet-id' && newValue) {
            this.loadWallet(newValue);
        }
        if (name === 'testnet') {
            this.isTestnet = newValue === 'true';
            this.render();
        }
    }
    
    async loadWallet(walletId) {
        try {
            // Use the proper API endpoint
            const response = await fetch(`/api.php/wallet?id=${walletId}`);
            const data = await response.json();
            
            if (data && data._id) {
                // Single wallet object returned
                this.wallet = {
                    publicKey: data.publicKey,
                    balance: data.balance || '0.0000000',
                    network: data.network || this.isTestnet ? 'testnet' : 'public'
                };
                console.log('Payment component loaded wallet:', this.wallet);
                this.render();
            } else if (data && data.success && data.wallet) {
                // Success response with wallet object
                this.wallet = data.wallet;
                console.log('Payment component loaded wallet:', this.wallet);
                this.render();
            } else {
                this.showError(data.error || 'Failed to load wallet');
            }
        } catch (error) {
            console.error('Error loading wallet:', error);
            this.showError('Failed to load wallet');
        }
    }
    
    async sendPayment(event) {
        event.preventDefault();
        
        if (this.isProcessing) return;
        this.isProcessing = true;
        this.render();
        
        try {
            const form = this.shadowRoot.querySelector('form');
            const destinationAddress = form.querySelector('#destination').value;
            const amount = form.querySelector('#amount').value;
            const memo = form.querySelector('#memo').value;
            
            if (!this.wallet) {
                throw new Error('No wallet loaded');
            }
            
            // Use the proper API endpoint
            const response = await fetch('/api.php/payment/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    sourceWalletId: this.getAttribute('wallet-id'),
                    destinationAddress,
                    amount,
                    memo,
                    testnet: this.isTestnet
                })
            });
            
            const data = await response.json();
            
            if (data && data.success) {
                this.showSuccess('Payment sent successfully!');
                form.reset();
                this.dispatchEvent(new CustomEvent('payment-sent', {
                    detail: data.transaction || data
                }));
            } else {
                this.showError(data.error || 'Failed to send payment');
            }
        } catch (error) {
            console.error('Error sending payment:', error);
            this.showError(error.message);
        } finally {
            this.isProcessing = false;
            this.render();
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
                
                .payment-container {
                    max-width: 600px;
                }
                
                form {
                    display: flex;
                    flex-direction: column;
                    gap: 15px;
                }
                
                .form-group {
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                }
                
                label {
                    font-weight: bold;
                }
                
                input, textarea {
                    padding: 8px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 14px;
                }
                
                input:focus, textarea:focus {
                    outline: none;
                    border-color: #2196F3;
                }
                
                button {
                    background: #2196F3;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 16px;
                }
                
                button:hover:not(:disabled) {
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
                
                .balance-info {
                    margin: 10px 0;
                    padding: 10px;
                    background: #f5f5f5;
                    border-radius: 4px;
                }
                
                .balance {
                    font-size: 18px;
                    font-weight: bold;
                    color: #2196F3;
                }
            </style>
            
            <div class="payment-container">
                <div class="network-badge ${this.isTestnet ? 'testnet' : 'mainnet'}">
                    ${this.isTestnet ? 'TESTNET' : 'MAINNET'}
                </div>
                
                <div class="error"></div>
                <div class="success"></div>
                
                ${this.wallet ? `
                    <div class="balance-info">
                        <div>Available Balance:</div>
                        <div class="balance">${this.formatBalance()} XLM</div>
                    </div>
                    
                    <form @submit="${this.sendPayment.bind(this)}">
                        <div class="form-group">
                            <label for="destination">Destination Address</label>
                            <input type="text" id="destination" required
                                placeholder="Enter Stellar public key"
                                pattern="^G[A-Za-z0-9]{55}$"
                                title="Enter a valid Stellar public key starting with G">
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">Amount (XLM)</label>
                            <input type="number" id="amount" required
                                min="0.0000001" step="0.0000001"
                                max="${this.getMaxAmount()}"
                                placeholder="Enter amount in XLM">
                        </div>
                        
                        <div class="form-group">
                            <label for="memo">Memo (Optional)</label>
                            <input type="text" id="memo"
                                maxlength="28"
                                placeholder="Enter optional memo">
                        </div>
                        
                        <button type="submit" ?disabled="${this.isProcessing}">
                            ${this.isProcessing ? 'Sending...' : 'Send Payment'}
                        </button>
                    </form>
                ` : `
                    <p>No wallet loaded</p>
                `}
            </div>
        `;
        
        // Add event listeners
        const form = this.shadowRoot.querySelector('form');
        if (form) {
            form.addEventListener('submit', this.sendPayment.bind(this));
        }
    }
    
    formatBalance() {
        if (!this.wallet || !this.wallet.balance) return '0.0000000';
        return parseFloat(this.wallet.balance).toFixed(7);
    }
    
    getMaxAmount() {
        if (!this.wallet || !this.wallet.balance) return 0;
        // Leave 1 XLM for minimum balance requirement
        return Math.max(0, parseFloat(this.wallet.balance) - 1).toFixed(7);
    }
}

customElements.define('stellar-payment', StellarPayment); 