// Admin Campaign Review JavaScript
document.addEventListener('DOMContentLoaded', () => {
    // Initialize the admin campaign review functionality
    AdminCampaignReview.init();
});

// Main admin module for campaign review
const AdminCampaignReview = {
    // App state
    state: {
        campaigns: [],
        currentCampaign: null,
        filter: 'pending', // Default filter to pending reviews
        searchQuery: '',
        stats: {
            pending: 0,
            active: 0,
            rejected: 0,
            total: 0
        }
    },

    // Configuration
    config: {
        apiBase: '/api/admin/campaigns'
    },

    // Initialize the module
    init() {
        this.setupEventListeners();
        this.loadCampaigns();
    },
    
    // Set up event listeners
    setupEventListeners() {
        // Filter by status
        document.getElementById('status-filter').addEventListener('change', (e) => {
            this.state.filter = e.target.value;
            this.renderCampaigns();
        });

        // Search campaigns
        document.getElementById('search-input').addEventListener('input', (e) => {
            this.state.searchQuery = e.target.value.toLowerCase();
            this.renderCampaigns();
        });

        // Refresh campaigns
        document.getElementById('refresh-btn').addEventListener('click', () => {
            this.loadCampaigns();
        });

        // Back to list from detail view
        document.getElementById('back-to-list').addEventListener('click', () => {
            this.showSection('campaign-list');
        });

        // Review form submission
        document.getElementById('review-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitReview();
        });
    },

    // Show loading indicator
    showLoading(show = true) {
        const loadingOverlay = document.getElementById('loading-overlay');
        if (show) {
            loadingOverlay.classList.add('active');
        } else {
            loadingOverlay.classList.remove('active');
        }
    },

    // Show notification
    showNotification(message, type = 'success') {
        const notification = document.getElementById('notification');
        const notificationMessage = document.getElementById('notification-message');
        
        notification.className = 'notification';
        notification.classList.add(type);
        notification.classList.add('show');
        
        notificationMessage.textContent = message;
        
        // Hide notification after 3 seconds
        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    },

    // Load campaigns from API
    async loadCampaigns() {
        try {
            this.showLoading(true);
            
            // Include authorization header with admin token
            const response = await fetch(this.config.apiBase, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                }
            });
                
            if (!response.ok) {
                throw new Error(`Failed to load campaigns: ${response.statusText}`);
            }
            
            const campaigns = await response.json();
            
            // Update state
            this.state.campaigns = campaigns;
            
            // Calculate statistics
            this.calculateStats();
            
            // Render campaigns
            this.renderCampaigns();
            this.renderStats();
            
            this.showLoading(false);
        } catch (error) {
            console.error('Error loading campaigns:', error);
            this.showNotification(error.message, 'error');
            this.showLoading(false);
        }
    },

    // Calculate campaign statistics
    calculateStats() {
        // Reset stats
        this.state.stats = {
            pending: 0,
            active: 0,
            rejected: 0,
            total: 0
        };
        
        // Count campaigns by status
        this.state.campaigns.forEach(campaign => {
            const status = campaign.status || 'pending';
            if (this.state.stats[status] !== undefined) {
                this.state.stats[status]++;
            }
            this.state.stats.total++;
        });
    },

    // Render statistics
    renderStats() {
        document.getElementById('pending-count').textContent = this.state.stats.pending;
        document.getElementById('approved-count').textContent = this.state.stats.active;
        document.getElementById('rejected-count').textContent = this.state.stats.rejected;
        document.getElementById('total-count').textContent = this.state.stats.total;
    },

    // Render campaigns based on current filter and search
    renderCampaigns() {
        const campaignGrid = document.getElementById('campaign-grid');
        campaignGrid.innerHTML = '';
        
        // Apply filters
        let filteredCampaigns = [...this.state.campaigns];
        
        // Filter by status
        if (this.state.filter !== 'all') {
            filteredCampaigns = filteredCampaigns.filter(campaign => 
                campaign.status === this.state.filter
            );
        }
        
        // Apply search query
        if (this.state.searchQuery) {
            filteredCampaigns = filteredCampaigns.filter(campaign => 
                (campaign.title && campaign.title.toLowerCase().includes(this.state.searchQuery)) ||
                (campaign.description && campaign.description.toLowerCase().includes(this.state.searchQuery))
            );
        }
        
        // Render campaign cards
        if (filteredCampaigns.length === 0) {
            campaignGrid.innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                    <p style="color: var(--gray-600);">No campaigns found</p>
                </div>
            `;
            return;
        }
        
        // Sort campaigns by creation date (newest first)
        filteredCampaigns.sort((a, b) => {
            const dateA = new Date(a.createdAt || 0);
            const dateB = new Date(b.createdAt || 0);
            return dateB - dateA;
        });
        
        filteredCampaigns.forEach(campaign => {
            campaignGrid.appendChild(this.createCampaignCard(campaign));
        });
    },

    // Create campaign card element
    createCampaignCard(campaign) {
        const card = document.createElement('div');
        card.className = 'campaign-card';
        
        // Determine badge class based on status
        let badgeClass = 'pending';
        let statusText = 'Pending';
        
        if (campaign.status === 'active') {
            badgeClass = 'active';
            statusText = 'Approved';
        } else if (campaign.status === 'rejected') {
            badgeClass = 'rejected';
            statusText = 'Rejected';
        }
        
        // Format date
        const createdDate = campaign.createdAt ? 
            new Date(campaign.createdAt).toLocaleDateString() : 
            'Unknown Date';
        
        // Calculate funding goal
        const fundingGoal = this.formatCurrency(campaign.fundingGoal || 0);
        
        // Get first image or use placeholder
        const imageUrl = campaign.images && campaign.images.length > 0 
            ? campaign.images[0] 
            : '/img/placeholder.jpg';
        
        card.innerHTML = `
            <div class="campaign-image" style="background-image: url('${imageUrl}')">
                <div class="campaign-badge ${badgeClass}">
                    ${statusText}
                </div>
            </div>
            <div class="campaign-content">
                <h3 class="campaign-title">${campaign.title || 'Untitled Campaign'}</h3>
                <div class="campaign-meta">
                    <span>Created: ${createdDate}</span>
                    <span>${campaign.type || 'Crowdfunding'}</span>
                </div>
                <p class="campaign-description">${campaign.description || 'No description provided'}</p>
            </div>
            <div class="campaign-footer">
                <div class="campaign-goal">Goal: ${fundingGoal}</div>
                <div class="campaign-actions">
                    <button class="btn btn-primary btn-sm review-btn">Review</button>
                </div>
            </div>
        `;
        
        // Add click event to review button
        card.querySelector('.review-btn').addEventListener('click', () => {
            this.viewCampaignDetails(campaign._id);
        });
        
        return card;
    },

    // View campaign details
    async viewCampaignDetails(campaignId) {
        try {
            this.showLoading(true);
            
            // Fetch campaign details
            const response = await fetch(`${this.config.apiBase}/details?id=${campaignId}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                }
            });
            
            if (!response.ok) {
                throw new Error(`Failed to load campaign details: ${response.statusText}`);
            }
            
            const campaign = await response.json();
            this.state.currentCampaign = campaign;
            
            // Populate the campaign details view
            this.populateCampaignDetails(campaign);
            
            // Show the campaign review section
            this.showSection('campaign-review');
            
            this.showLoading(false);
        } catch (error) {
            console.error('Error fetching campaign details:', error);
            this.showNotification(error.message, 'error');
            this.showLoading(false);
        }
    },

    // Populate campaign details in the review view
    populateCampaignDetails(campaign) {
        // Set banner image
        const bannerEl = document.getElementById('campaign-banner');
        if (campaign.images && campaign.images.length > 0) {
            bannerEl.style.backgroundImage = `url('${campaign.images[0]}')`;
        } else {
            bannerEl.style.backgroundImage = `url('/img/placeholder.jpg')`;
        }
        
        // Set basic info
        document.getElementById('campaign-title').textContent = campaign.title || 'Untitled Campaign';
        document.getElementById('campaign-creator').textContent = campaign.creatorName || 'Unknown Creator';
        document.getElementById('campaign-created').textContent = this.formatDate(campaign.createdAt);
        document.getElementById('campaign-location').textContent = 
            campaign.location?.region ? 
            `${campaign.location.region}, ${campaign.location.country || ''}` : 
            'Unknown Location';
        document.getElementById('campaign-description').textContent = campaign.description || 'No description provided';
        
        // Set campaign details
        document.getElementById('campaign-goal').textContent = this.formatCurrency(campaign.fundingGoal || 0);
        document.getElementById('campaign-type').textContent = this.capitalizeFirstLetter(campaign.type || 'Crowdfunding');
        document.getElementById('campaign-deadline').textContent = this.formatDate(campaign.deadline);
        document.getElementById('campaign-min-contribution').textContent = this.formatCurrency(campaign.minContribution || 0);
        
        // Display media gallery
        this.renderMediaGallery(campaign.images || []);
        
        // Set the review form's initial state
        document.getElementById('review-status').value = campaign.status || 'pending';
        document.getElementById('review-notes').value = campaign.adminNotes || '';
        document.getElementById('review-feedback').value = campaign.feedback || '';
    },

    // Render media gallery
    renderMediaGallery(images) {
        const mediaContainer = document.getElementById('campaign-media');
        mediaContainer.innerHTML = '';
        
        if (images.length === 0) {
            mediaContainer.innerHTML = '<p>No media provided</p>';
            return;
        }
        
        // Create a grid for images
        const mediaGrid = document.createElement('div');
        mediaGrid.style.display = 'grid';
        mediaGrid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(150px, 1fr))';
        mediaGrid.style.gap = '1rem';
        mediaGrid.style.marginTop = '1rem';
        
        images.forEach(imageUrl => {
            const mediaItem = document.createElement('div');
            mediaItem.style.aspectRatio = '16/9';
            mediaItem.style.backgroundImage = `url('${imageUrl}')`;
            mediaItem.style.backgroundSize = 'cover';
            mediaItem.style.backgroundPosition = 'center';
            mediaItem.style.borderRadius = '6px';
            
            mediaGrid.appendChild(mediaItem);
        });
        
        mediaContainer.appendChild(mediaGrid);
    },

    // Submit campaign review
    async submitReview() {
        try {
            if (!this.state.currentCampaign) {
                throw new Error('No campaign selected for review');
            }
            
            this.showLoading(true);
            
            const campaignId = this.state.currentCampaign._id;
            const status = document.getElementById('review-status').value;
            const adminNotes = document.getElementById('review-notes').value;
            const feedback = document.getElementById('review-feedback').value;
            
            // Prepare the update data
            const updateData = {
                status,
                adminNotes,
                feedback,
                reviewedAt: new Date().toISOString(),
            };
            
            // Submit the review
            const response = await fetch(`${this.config.apiBase}/update?id=${campaignId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                },
                body: JSON.stringify(updateData)
            });
            
            if (!response.ok) {
                throw new Error(`Failed to update campaign: ${response.statusText}`);
            }
            
            const updatedCampaign = await response.json();
            
            // Update the campaign in the list
            this.state.campaigns = this.state.campaigns.map(campaign => {
                if (campaign._id === campaignId) {
                    return {...campaign, ...updateData};
                }
                return campaign;
            });
            
            // Recalculate stats
            this.calculateStats();
            this.renderStats();
            
            // Show success message
            this.showNotification(`Campaign ${status === 'active' ? 'approved' : 'rejected'} successfully`);
            
            // Return to campaign list
            this.showSection('campaign-list');
            this.renderCampaigns();
            
            this.showLoading(false);
        } catch (error) {
            console.error('Error submitting review:', error);
            this.showNotification(error.message, 'error');
            this.showLoading(false);
        }
    },

    // Show specified section and hide others
    showSection(sectionId) {
        document.querySelectorAll('.admin-section').forEach(section => {
            section.style.display = 'none';
        });
        
        document.getElementById(sectionId).style.display = 'block';
        
        // Scroll to top
        window.scrollTo(0, 0);
    },

    // Utility: Format currency
    formatCurrency(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    },

    // Utility: Format date
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    },

    // Utility: Capitalize first letter
    capitalizeFirstLetter(string) {
        if (!string) return '';
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
};
