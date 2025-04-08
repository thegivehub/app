/**
 * DonateButton Web Component
 * 
 * A custom element that creates a donation button and modal form.
 * Usage: <donate-button campaign-id="123"></donate-button>
 * 
 * @element donate-button
 * @attr {string} campaign-id - The ID of the campaign to donate to
 */
class DonateButton extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: "open" });
        this.paymentMethod = "card"; // Default to card payment
        this.supportedCryptos = null;
    }

    static get observedAttributes() {
        return ["campaign-id"];
    }

    async connectedCallback() {
        // Fetch supported cryptocurrencies
        try {
            const response = await fetch("/api/donate/supported-cryptos");
            this.supportedCryptos = await response.json();
        } catch (error) {
            console.error("Failed to fetch supported cryptocurrencies:", error);
            this.supportedCryptos = {};
        }
        
        this.render();
        this.setupEventListeners();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name === "campaign-id" && oldValue !== newValue) {
            this.campaignId = newValue;
        }
    }

    render() {
        const styles = `
            :host {
                display: inline-block;
                --primary-color: #2ecc71;
                --primary-hover: #27ae60;
                --error-color: #e74c3c;
                --border-color: #ddd;
            }

            .donate-btn {
                background-color: var(--primary-color);
                color: white;
                padding: 12px 24px;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                cursor: pointer;
                transition: background-color 0.3s;
            }

            .donate-btn:hover {
                background-color: var(--primary-hover);
            }

            .modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1000;
            }

            .modal.active {
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .modal-content {
                background-color: white;
                padding: 24px;
                border-radius: 8px;
                width: 90%;
                max-width: 500px;
                position: relative;
                max-height: 90vh;
                overflow-y: auto;
            }

            .close-btn {
                position: absolute;
                top: 12px;
                right: 12px;
                font-size: 24px;
                cursor: pointer;
                border: none;
                background: none;
                padding: 0;
            }

            form {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            label {
                display: block;
                margin-bottom: 4px;
                font-weight: bold;
            }

            input, select {
                width: 100%;
                padding: 8px;
                border: 1px solid var(--border-color);
                border-radius: 4px;
                font-size: 16px;
            }

            .payment-methods {
                display: flex;
                gap: 12px;
                margin-bottom: 16px;
                flex-wrap: wrap;
            }

            .payment-method {
                flex: 1;
                min-width: 120px;
                padding: 12px;
                border: 2px solid var(--border-color);
                border-radius: 4px;
                cursor: pointer;
                text-align: center;
                transition: all 0.3s;
            }

            .payment-method.active {
                border-color: var(--primary-color);
                background-color: #f1f9f5;
            }

            .payment-method img {
                width: 32px;
                height: 32px;
                margin-bottom: 8px;
            }

            button[type="submit"] {
                background-color: var(--primary-color);
                color: white;
                padding: 12px;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                cursor: pointer;
            }

            .error-message {
                color: var(--error-color);
                display: none;
                margin-top: 8px;
            }

            .success-message {
                color: var(--primary-color);
                display: none;
                margin-top: 8px;
            }

            .crypto-payment {
                display: none;
                text-align: center;
            }

            .crypto-payment.active {
                display: block;
            }

            .qr-code {
                margin: 20px auto;
                max-width: 200px;
            }

            .qr-code img {
                width: 100%;
                height: auto;
            }

            .wallet-address {
                word-break: break-all;
                padding: 12px;
                background: #f8f9fa;
                border-radius: 4px;
                margin: 12px 0;
                font-family: monospace;
            }

            .copy-btn {
                background: #f1f9f5;
                border: 1px solid var(--primary-color);
                color: var(--primary-color);
                padding: 8px 16px;
                border-radius: 4px;
                cursor: pointer;
                margin: 8px 0;
            }

            .copy-btn:hover {
                background: #e8f5ec;
            }

            .crypto-instructions {
                margin-top: 16px;
                text-align: left;
                white-space: pre-line;
            }
        `;

        const cryptoOptions = this.supportedCryptos ? Object.entries(this.supportedCryptos)
            .map(([symbol, data]) => `
                <option value="${symbol}">${data.name} (${symbol})</option>
            `).join("") : "";

        const html = `
            <button class="donate-btn">Donate Now</button>
            <div class="modal">
                <div class="modal-content">
                    <button class="close-btn">&times;</button>
                    <h2>Make a Donation</h2>
                    
                    <div class="payment-methods">
                        <div class="payment-method active" data-method="card">
                            <img src="/assets/icons/credit-card.svg" alt="Credit Card">
                            <div>Credit Card</div>
                        </div>
                        <div class="payment-method" data-method="crypto">
                            <img src="/assets/icons/crypto.svg" alt="Cryptocurrency">
                            <div>Cryptocurrency</div>
                        </div>
                    </div>

                    <form id="donateForm">
                        <div>
                            <label for="amount">Amount ($)</label>
                            <input type="number" id="amount" name="amount" min="1" step="0.01" required>
                        </div>
                        
                        <div id="cryptoSelect" style="display: none;">
                            <label for="cryptoType">Select Cryptocurrency</label>
                            <select id="cryptoType" name="cryptoType" required>
                                ${cryptoOptions}
                            </select>
                        </div>

                        <div>
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        
                        <div>
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <button type="submit">Complete Donation</button>
                        <div class="error-message"></div>
                        <div class="success-message"></div>
                    </form>

                    <div class="crypto-payment">
                        <h3>Send your donation to:</h3>
                        <div class="qr-code"></div>
                        <div class="wallet-details">
                            <div class="wallet-address"></div>
                            <button class="copy-btn">Copy Address</button>
                        </div>
                        <div class="crypto-instructions"></div>
                    </div>
                </div>
            </div>
        `;

        this.shadowRoot.innerHTML = `
            <style>${styles}</style>
            ${html}
        `;
    }

    setupEventListeners() {
        const donateBtn = this.shadowRoot.querySelector(".donate-btn");
        const modal = this.shadowRoot.querySelector(".modal");
        const closeBtn = this.shadowRoot.querySelector(".close-btn");
        const form = this.shadowRoot.querySelector("#donateForm");
        const errorMessage = this.shadowRoot.querySelector(".error-message");
        const successMessage = this.shadowRoot.querySelector(".success-message");
        const paymentMethods = this.shadowRoot.querySelectorAll(".payment-method");
        const cryptoSelect = this.shadowRoot.querySelector("#cryptoSelect");
        const cryptoPayment = this.shadowRoot.querySelector(".crypto-payment");
        const copyBtn = this.shadowRoot.querySelector(".copy-btn");

        // Payment method selection
        paymentMethods.forEach(method => {
            method.addEventListener("click", () => {
                paymentMethods.forEach(m => m.classList.remove("active"));
                method.classList.add("active");
                this.paymentMethod = method.dataset.method;
                
                // Toggle crypto currency select visibility
                cryptoSelect.style.display = this.paymentMethod === "crypto" ? "block" : "none";
                
                // Reset form and messages
                form.reset();
                errorMessage.style.display = "none";
                successMessage.style.display = "none";
                cryptoPayment.classList.remove("active");
            });
        });

        // Open modal
        donateBtn.addEventListener("click", () => {
            modal.classList.add("active");
        });

        // Close modal
        closeBtn.addEventListener("click", () => {
            this.closeModal(modal, form, errorMessage, successMessage, cryptoPayment);
        });

        // Close modal when clicking outside
        modal.addEventListener("click", (e) => {
            if (e.target === modal) {
                this.closeModal(modal, form, errorMessage, successMessage, cryptoPayment);
            }
        });

        // Copy wallet address
        copyBtn.addEventListener("click", () => {
            const address = this.shadowRoot.querySelector(".wallet-address").textContent;
            navigator.clipboard.writeText(address).then(() => {
                copyBtn.textContent = "Copied!";
                setTimeout(() => {
                    copyBtn.textContent = "Copy Address";
                }, 2000);
            });
        });

        // Handle form submission
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            errorMessage.style.display = "none";
            successMessage.style.display = "none";

            const formData = new FormData(form);
            const data = {
                amount: parseFloat(formData.get("amount")),
                name: formData.get("name"),
                email: formData.get("email"),
                campaignId: this.campaignId
            };

            try {
                if (this.paymentMethod === "crypto") {
                    data.cryptoType = formData.get("cryptoType");
                    const response = await fetch("/api/donate/crypto", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();
                    if (result.success) {
                        form.style.display = "none";
                        cryptoPayment.classList.add("active");
                        
                        // Update crypto payment UI
                        this.shadowRoot.querySelector(".qr-code").innerHTML = `
                            <img src="${result.qrCode}" alt="Payment QR Code">
                        `;
                        this.shadowRoot.querySelector(".wallet-address").textContent = result.walletAddress;
                        this.shadowRoot.querySelector(".crypto-instructions").textContent = result.instructions;
                    } else {
                        throw new Error(result.error);
                    }
                } else {
                    // Handle card payment (existing implementation)
                    const response = await fetch("/api/donate/card", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();
                    if (result.success) {
                        successMessage.textContent = "Thank you for your donation!";
                        successMessage.style.display = "block";
                        form.reset();
                    } else {
                        throw new Error(result.error);
                    }
                }
            } catch (error) {
                errorMessage.textContent = error.message;
                errorMessage.style.display = "block";
            }
        });
    }

    closeModal(modal, form, errorMessage, successMessage, cryptoPayment) {
        modal.classList.remove("active");
        form.reset();
        form.style.display = "block";
        errorMessage.style.display = "none";
        successMessage.style.display = "none";
        cryptoPayment.classList.remove("active");
    }
}

// Register the web component
customElements.define("donate-button", DonateButton); 