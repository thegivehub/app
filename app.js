let app = {
    init() {
        this.setupEventListeners();
        this.loadInitialContent();
        this.setupRouting();
        this.loadUserData();
    },
    theme: {
        init() {
            const themeToggle = document.getElementById('themeToggle');
            const moonIcon = themeToggle.querySelector('.moon-icon');
            const sunIcon = themeToggle.querySelector('.sun-icon');

            // Check for saved theme preference or system preference
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            // Set initial theme
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.documentElement.setAttribute('data-theme', 'dark');
                moonIcon.classList.add('hidden');
                sunIcon.classList.remove('hidden');
            }

            // Toggle theme
            themeToggle.addEventListener('click', () => {
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                
                if (isDark) {
                    document.documentElement.removeAttribute('data-theme');
                    localStorage.setItem('theme', 'light');
                    moonIcon.classList.remove('hidden');
                    sunIcon.classList.add('hidden');
                } else {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                    moonIcon.classList.add('hidden');
                    sunIcon.classList.remove('hidden');
                }
            });
        }
    },

    config: {
        account: {
            id: 1,
            name: "Better Living"
        },
        user: {
            id: 1,
            name: "Chris Robison",
            username: "cdr",
            pic: "/img/profilepics/cdr.png"
        },
        navUrls: {
            'overview': '/dashboard.html',
            'campaigns': '/browse.html',
            'donors': '/donors.html',
            'settings': '/settings.html'
        },
        api: {
            baseUrl: 'https://app.thegivehub.com/api',
            endpoints: {
                campaigns: '/campaign',
                donors: '/donors',
                user: '/user'
            }
        }
    },

    elements: {
        menuTrigger: document.getElementById('menuTrigger'),
        sidebar: document.getElementById('sidebar'),
        overlay: document.getElementById('overlay'),
        contentFrame: document.getElementById('contentFrame'),
        frameLoader: document.getElementById('frameLoader'),
        frameError: document.getElementById('frameError'),
        userAvatar: document.querySelector('.user-avatar'),
        navLinks: document.querySelectorAll('.nav-link')
    },

    state: {
        loaded: false,
        currentPage: null,
        sidebarOpen: false,
        lastError: null
    },

    data: {
        campaigns: [],
        donors: [],
        userProfile: null
    },

    setupEventListeners() {
        // Navigation
        this.elements.navLinks.forEach(link => {
            link.addEventListener('click', (e) => this.handleNavigation(e));
        });

        // Sidebar
        this.elements.menuTrigger.addEventListener('click', () => this.toggleSidebar());
        this.elements.overlay.addEventListener('click', () => this.toggleSidebar());

        // Iframe events
        this.elements.contentFrame.addEventListener('load', () => this.handleFrameLoad());
        window.addEventListener('message', (event) => this.handleFrameMessage(event));

        // Responsive handling
        window.addEventListener('resize', () => this.handleResize());

        // Error handling
        window.addEventListener('error', (event) => this.handleError(event));
    },

    setupRouting() {
        window.addEventListener('popstate', (event) => {
            if (event.state && event.state.url) {
                this.loadContent(event.state.url, false);
            }
        });
    },

    async loadUserData() {
        try {
            const response = await fetch(`${this.config.api.baseUrl}${this.config.api.endpoints.user}`);
            const userData = await response.json();
            this.data.userProfile = userData;
            this.updateUserInterface();
        } catch (error) {
            console.error('Failed to load user data:', error);
        }
    },

    updateUserInterface() {
        // Update user avatar
        if (this.data.userProfile) {
            this.elements.userAvatar.textContent = this.getInitials(this.data.userProfile.name);
        }
    },

    handleNavigation(event) {
        event.preventDefault();
        const link = event.currentTarget;
        
        this.elements.navLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');

        if (window.innerWidth <= 768) {
            this.toggleSidebar(false);
        }

        const url = link.getAttribute('href');
        if (url && url !== '#') {
            this.loadContent(url, true);
            this.state.currentPage = url;
        }
    },

    handleFrameLoad() {
        this.elements.frameLoader.classList.remove('active');
        this.state.loaded = true;
        
        try {
            // Attempt to communicate with the frame
            this.elements.contentFrame.contentWindow.postMessage({
                type: 'app-ready',
                config: this.config
            }, '*');
        } catch (error) {
            console.warn('Frame communication failed:', error);
        }
    },

    handleFrameMessage(event) {
        // Handle messages from iframe content
        const { type, data } = event.data;
        
        switch (type) {
            case 'content-height':
                this.adjustFrameHeight(data.height);
                break;
            case 'navigation':
                this.loadContent(data.url, true);
                break;
            case 'error':
                this.handleError(data.error);
                break;
        }
    },

    handleResize() {
        if (window.innerWidth > 768 && this.state.sidebarOpen) {
            this.toggleSidebar(false);
        }
    },

    handleError(error) {
        this.state.lastError = error;
        this.elements.frameLoader.classList.remove('active');
        this.elements.frameError.classList.add('active');
        console.error('Application error:', error);
    },

    toggleSidebar(show = null) {
        const newState = show === null ? !this.state.sidebarOpen : show;
        this.state.sidebarOpen = newState;
        
        this.elements.sidebar.classList.toggle('active', newState);
        this.elements.overlay.classList.toggle('active', newState);
    },

    loadContent(url, addToHistory = true) {
        this.elements.frameLoader.classList.add('active');
        this.elements.frameError.classList.remove('active');

        if (addToHistory) {
            history.pushState({ url }, '', url);
        }

        try {
            this.elements.contentFrame.src = url;
        } catch (error) {
            this.handleError(error);
        }
    },

    loadInitialContent() {
        const defaultPage = this.config.navUrls.overview;
        this.loadContent(defaultPage, true);
    },

    reloadFrame() {
        if (this.elements.contentFrame.src !== 'about:blank') {
            this.loadContent(this.elements.contentFrame.src, false);
        }
    },

    // Utility functions
    getInitials(name) {
        return name
            .split(' ')
            .map(part => part.charAt(0))
            .join('')
            .toUpperCase();
    },

    adjustFrameHeight(height) {
        this.elements.contentFrame.style.height = `${height}px`;
    }
};

// Initialize the application
window.app = app;
document.addEventListener('DOMContentLoaded', () => app.init());
