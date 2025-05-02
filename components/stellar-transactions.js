// stellar-transactions.js
// Web component for displaying Stellar transaction history

class StellarTransactions extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        
        // Initialize properties
        this.transactions = [];
        this.isLoading = false;
        this.page = 1;
        this.limit = 10;
        this.hasMore = false;
        
        this.render();
    }
    
    static get observedAttributes() {
        return ['wallet-id', 'limit'];
    }
    
    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'wallet-id' && newValue) {
            this.loadTransactions();
        }
        if (name === 'limit' && newValue) {
            this.limit = parseInt(newValue);
            this.loadTransactions();
        }
    }
    
    async loadTransactions(reset = true) {
        if (this.isLoading) return;
        
        const walletId = this.getAttribute('wallet-id');
        if (!walletId) return;
        
        this.isLoading = true;
        if (reset) this.page = 1;
        this.render();
        
        try {
            // Use the proper API endpoint
            const response = await fetch(
                `/api/wallets/getTransactions?walletId=${walletId}&page=${this.page}&limit=${this.limit}`
            );
            const data = await response.json();
            
            if (Array.isArray(data)) {
                // Direct array response
                const formatted = data.map(tx => this.formatTransactionData(tx));
                if (reset) {
                    this.transactions = formatted;
                } else {
                    this.transactions = [...this.transactions, ...formatted];
                }
                this.hasMore = data.length >= this.limit;
                console.log('Loaded transactions from array:', this.transactions);
            } else if (data && data.success) {
                // Success response with transactions array
                if (reset) {
                    this.transactions = data.transactions || [];
                } else {
                    this.transactions = [...this.transactions, ...(data.transactions || [])];
                }
                
                this.hasMore = data.pagination?.hasMore || false;
                console.log('Loaded transactions from success object:', this.transactions);
            } else {
                this.showError(data?.error || 'Failed to load transactions');
            }
        } catch (error) {
            console.error('Error loading transactions:', error);
            this.showError('Failed to load transactions');
        } finally {
            this.isLoading = false;
            this.render();
        }
    }
    
    formatTransactionData(tx) {
        // Convert raw transaction data to expected format
        return {
            type: tx.type || 'payment',
            amount: {
                value: tx.amount || '0.0000000',
                currency: tx.asset || 'XLM'
            },
            createdAt: tx.createdAt || tx.created_at || new Date().toISOString(),
            transaction: {
                txHash: tx.txHash || tx.hash || 'Processing...',
                ledger: tx.ledger || 0
            },
            status: tx.status || 'completed',
            memo: tx.memo || ''
        };
    }
    
    loadMore() {
        if (this.hasMore && !this.isLoading) {
            this.page++;
            this.loadTransactions(false);
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
    
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString();
    }
    
    formatAmount(amount, asset = 'XLM') {
        return `${parseFloat(amount).toFixed(7)} ${asset}`;
    }
    
    getTransactionTypeIcon(type) {
        switch (type) {
            case 'payment':
                return '💸';
            case 'create_account':
                return '🆕';
            case 'donation':
                return '🎁';
            case 'milestone':
                return '🎯';
            default:
                return '📝';
        }
    }
    
    getTransactionStatusClass(status) {
        switch (status.toLowerCase()) {
            case 'completed':
                return 'status-completed';
            case 'pending':
                return 'status-pending';
            case 'failed':
                return 'status-failed';
            default:
                return '';
        }
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
                
                .transactions-container {
                    max-width: 800px;
                }
                
                .transaction-list {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }
                
                .transaction-item {
                    padding: 15px;
                    background: #f5f5f5;
                    border-radius: 4px;
                    display: grid;
                    grid-template-columns: auto 1fr auto;
                    gap: 15px;
                    align-items: center;
                }
                
                .transaction-icon {
                    font-size: 24px;
                }
                
                .transaction-details {
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                }
                
                .transaction-amount {
                    font-size: 18px;
                    font-weight: bold;
                }
                
                .transaction-date {
                    font-size: 12px;
                    color: #666;
                }
                
                .transaction-hash {
                    font-family: monospace;
                    font-size: 12px;
                    color: #666;
                    word-break: break-all;
                }
                
                .transaction-memo {
                    font-style: italic;
                    color: #666;
                }
                
                .status-badge {
                    padding: 5px 10px;
                    border-radius: 15px;
                    font-size: 12px;
                    font-weight: bold;
                }
                
                .status-completed {
                    background: #4CAF50;
                    color: white;
                }
                
                .status-pending {
                    background: #FFC107;
                    color: black;
                }
                
                .status-failed {
                    background: #f44336;
                    color: white;
                }
                
                .load-more {
                    margin-top: 20px;
                    text-align: center;
                }
                
                button {
                    background: #2196F3;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 4px;
                    cursor: pointer;
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
                
                .empty-state {
                    text-align: center;
                    padding: 40px;
                    color: #666;
                }
                
                .loading {
                    text-align: center;
                    padding: 20px;
                    color: #666;
                }
            </style>
            
            <div class="transactions-container">
                <div class="error"></div>
                
                ${this.isLoading && this.page === 1 ? `
                    <div class="loading">Loading transactions...</div>
                ` : ''}
                
                ${!this.isLoading && this.transactions.length === 0 ? `
                    <div class="empty-state">
                        No transactions found
                    </div>
                ` : ''}
                
                ${this.transactions.length > 0 ? `
                    <div class="transaction-list">
                        ${this.transactions.map(tx => `
                            <div class="transaction-item">
                                <div class="transaction-icon">
                                    ${this.getTransactionTypeIcon(tx.type)}
                                </div>
                                
                                <div class="transaction-details">
                                    <div class="transaction-amount">
                                        ${this.formatAmount(tx.amount.value, tx.amount.currency)}
                                    </div>
                                    
                                    <div class="transaction-date">
                                        ${this.formatDate(tx.createdAt)}
                                    </div>
                                    
                                    <div class="transaction-hash">
                                        ${tx.transaction?.txHash || 'Processing...'}
                                    </div>
                                    
                                    ${tx.memo ? `
                                        <div class="transaction-memo">
                                            Memo: ${tx.memo}
                                        </div>
                                    ` : ''}
                                </div>
                                
                                <div class="status-badge ${this.getTransactionStatusClass(tx.status)}">
                                    ${tx.status}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                    
                    ${this.hasMore ? `
                        <div class="load-more">
                            <button @click="${this.loadMore.bind(this)}"
                                    ?disabled="${this.isLoading}">
                                ${this.isLoading ? 'Loading...' : 'Load More'}
                            </button>
                        </div>
                    ` : ''}
                ` : ''}
            </div>
        `;
        
        // Add event listeners
        const loadMoreButton = this.shadowRoot.querySelector('button[\\@click]');
        if (loadMoreButton) {
            loadMoreButton.addEventListener('click', this.loadMore.bind(this));
        }
    }
}

customElements.define('stellar-transactions', StellarTransactions); 
