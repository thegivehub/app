// stellar-users.js
// Web component for managing and displaying Stellar wallet users

class StellarUsers extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        
        // Initialize properties
        this.users = [];
        this.isLoading = false;
        
        this.render();
        this.loadUsers();
    }
    
    async loadUsers() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.render();
        
        try {
            // Use the proper API endpoint
            const response = await fetch('/api.php/users');
            const data = await response.json();
            
            if (data && Array.isArray(data)) {
                this.users = data.map(user => ({
                    id: user._id || user.id,
                    name: user.name || user.username || user.id,
                    wallet: user.wallet || null
                }));
                console.log('Loaded users:', this.users);
            } else if (data && data.success) {
                this.users = data.users || [];
            } else {
                this.showError('Invalid response format');
            }
        } catch (error) {
            console.error('Error loading users:', error);
            this.showError('Failed to load users');
        } finally {
            this.isLoading = false;
            this.render();
        }
    }
    
    selectUser(userId) {
        this.dispatchEvent(new CustomEvent('user-selected', {
            detail: { userId },
            bubbles: true,
            composed: true
        }));
    }
    
    showError(message) {
        const errorElement = this.shadowRoot.querySelector('.error');
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        setTimeout(() => {
            errorElement.style.display = 'none';
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
                
                .users-container {
                    max-width: 600px;
                }
                
                .user-list {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                    margin-top: 15px;
                }
                
                .user-item {
                    padding: 15px;
                    background: #f5f5f5;
                    border-radius: 4px;
                    cursor: pointer;
                    transition: background-color 0.2s;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .user-item:hover {
                    background: #e0e0e0;
                }
                
                .user-info {
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                }
                
                .user-id {
                    font-weight: bold;
                    color: #2196F3;
                }
                
                .user-balance {
                    font-size: 14px;
                    color: #666;
                }
                
                .error {
                    color: #f44336;
                    padding: 10px;
                    margin: 10px 0;
                    border: 1px solid #f44336;
                    border-radius: 4px;
                    display: none;
                }
                
                .loading {
                    text-align: center;
                    padding: 20px;
                    color: #666;
                }
                
                .empty-state {
                    text-align: center;
                    padding: 40px;
                    color: #666;
                }
                
                button {
                    background: #2196F3;
                    color: white;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 4px;
                    cursor: pointer;
                }
                
                button:hover {
                    background: #1976D2;
                }
            </style>
            
            <div class="users-container">
                <h2>Available Users</h2>
                <div class="error"></div>
                
                ${this.isLoading ? `
                    <div class="loading">Loading users...</div>
                ` : ''}
                
                ${!this.isLoading && this.users.length === 0 ? `
                    <div class="empty-state">
                        No users found
                    </div>
                ` : ''}
                
                ${this.users.length > 0 ? `
                    <div class="user-list">
                        ${this.users.map(user => `
                            <div class="user-item" data-user-id="${user.id}">
                                <div class="user-info">
                                    <div class="user-id">${user.id}</div>
                                    <div class="user-balance">${this.formatBalance(user.wallet?.balance)} XLM</div>
                                </div>
                                <button class="select-user-btn" data-user-id="${user.id}">Select</button>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}
            </div>
        `;
        
        // Add event listeners for both the user items and their buttons
        this.shadowRoot.querySelectorAll('.user-item').forEach(item => {
            const userId = item.getAttribute('data-user-id');
            item.addEventListener('click', () => this.selectUser(userId));
        });
        
        this.shadowRoot.querySelectorAll('.select-user-btn').forEach(button => {
            const userId = button.getAttribute('data-user-id');
            button.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent the parent item's click event
                this.selectUser(userId);
            });
        });
    }
    
    formatBalance(balance) {
        if (!balance) return '0.0000000';
        return parseFloat(balance).toFixed(7);
    }
}

customElements.define('stellar-users', StellarUsers); 