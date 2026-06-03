// language-switcher.js
// Web component for switching between campaign languages

class LanguageSwitcher extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: 'open' });

        // Initialize properties
        this.currentLanguage = 'en';
        this.availableLanguages = [];
        this.languageNames = {
            'en': 'English',
            'es': 'Español',
            'fr': 'Français',
            'de': 'Deutsch',
            'pt': 'Português',
            'zh': '中文',
            'ar': 'العربية',
            'it': 'Italiano',
            'ja': '日本語',
            'ko': '한국어',
            'ru': 'Русский',
            'hi': 'हिन्दी'
        };

        // Flag emoji mapping
        this.flagEmojis = {
            'en': '🇬🇧',
            'es': '🇪🇸',
            'fr': '🇫🇷',
            'de': '🇩🇪',
            'pt': '🇵🇹',
            'zh': '🇨🇳',
            'ar': '🇸🇦',
            'it': '🇮🇹',
            'ja': '🇯🇵',
            'ko': '🇰🇷',
            'ru': '🇷🇺',
            'hi': '🇮🇳'
        };

        this.render();
    }

    static get observedAttributes() {
        return ['current-language', 'available-languages'];
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'current-language' && newValue) {
            this.currentLanguage = newValue;
            this.render();
        }
        if (name === 'available-languages' && newValue) {
            try {
                this.availableLanguages = JSON.parse(newValue);
                this.render();
            } catch (e) {
                console.error('Invalid available-languages JSON:', e);
            }
        }
    }

    setLanguages(languages) {
        this.availableLanguages = languages;
        this.render();
    }

    setCurrentLanguage(lang) {
        this.currentLanguage = lang;
        this.render();
    }

    handleLanguageChange(lang) {
        if (lang === this.currentLanguage) return;

        this.currentLanguage = lang;
        this.render();

        // Dispatch custom event for parent to handle
        this.dispatchEvent(new CustomEvent('language-change', {
            detail: { language: lang },
            bubbles: true,
            composed: true
        }));
    }

    render() {
        const hasMultipleLanguages = this.availableLanguages.length > 1;

        if (!hasMultipleLanguages) {
            this.shadowRoot.innerHTML = '';
            return;
        }

        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: inline-block;
                    font-family: inherit;
                }

                .language-switcher {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    background-color: white;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 4px;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                }

                .language-button {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    padding: 6px 12px;
                    border: none;
                    background: transparent;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 14px;
                    font-family: inherit;
                    transition: all 0.2s ease;
                    color: #64748b;
                }

                .language-button:hover {
                    background-color: #f1f5f9;
                    color: #334155;
                }

                .language-button.active {
                    background-color: #2563eb;
                    color: white;
                    font-weight: 500;
                }

                .language-button.active:hover {
                    background-color: #1e40af;
                }

                .flag {
                    font-size: 18px;
                    line-height: 1;
                }

                .language-name {
                    font-size: 14px;
                    white-space: nowrap;
                }

                @media (max-width: 768px) {
                    .language-name {
                        display: none;
                    }

                    .language-button {
                        padding: 8px;
                    }

                    .flag {
                        font-size: 20px;
                    }
                }
            </style>

            <div class="language-switcher">
                ${this.availableLanguages.map(lang => `
                    <button
                        class="language-button ${lang === this.currentLanguage ? 'active' : ''}"
                        data-lang="${lang}"
                        title="${this.languageNames[lang] || lang.toUpperCase()}"
                    >
                        <span class="flag">${this.flagEmojis[lang] || '🌐'}</span>
                        <span class="language-name">${this.languageNames[lang] || lang.toUpperCase()}</span>
                    </button>
                `).join('')}
            </div>
        `;

        // Add event listeners to buttons
        this.shadowRoot.querySelectorAll('.language-button').forEach(button => {
            button.addEventListener('click', (e) => {
                const lang = e.currentTarget.getAttribute('data-lang');
                this.handleLanguageChange(lang);
            });
        });
    }
}

// Register the custom element
customElements.define('language-switcher', LanguageSwitcher);
