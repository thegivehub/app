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
        },
        pagination: {
            page: 1,
            pageSize: 12,
            hasMore: true,
            loading: false
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
            this.resetPagination();
            this.renderCampaigns();
        });

        // Search campaigns
        document.getElementById('search-input').addEventListener('input', (e) => {
            this.state.searchQuery = e.target.value.toLowerCase();
            this.resetPagination();
            this.renderCampaigns();
        });

        // Refresh campaigns
        document.getElementById('refresh-btn').addEventListener('click', () => {
            this.resetPagination();
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

        // Infinite scroll event listener
        window.addEventListener('scroll', this.handleScroll.bind(this));
    },
    
    // Reset pagination state
    resetPagination() {
        this.state.pagination = {
            page: 1,
            pageSize: 12,
            hasMore: true,
            loading: false
        };
    },

    // Handle scroll event for infinite scrolling
    handleScroll() {
        // Check if we're in the campaign list view
        if (document.getElementById('campaign-list').style.display === 'none') {
            return;
        }
        
        // Check if we're already loading or don't have more data
        if (this.state.pagination.loading || !this.state.pagination.hasMore) {
            return;
        }
        
        // Calculate scroll position
        const scrollHeight = document.documentElement.scrollHeight;
        const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
        const clientHeight = document.documentElement.clientHeight;
        
        // If we're near the bottom (200px threshold), load more
        if (scrollTop + clientHeight >= scrollHeight - 200) {
            this.loadMoreCampaigns();
        }
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
    showNotification(message, type = 'success', options = {}) {
        const notification = document.getElementById('notification');
        const notificationMessage = document.getElementById('notification-message');
        const notificationActions = document.getElementById('notification-actions');
        
        notification.className = 'notification';
        notification.classList.add(type);
        notification.classList.add('show');
        
        notificationMessage.textContent = message;
        
        // Add action buttons if provided
        if (options.actions) {
            notificationActions.innerHTML = '';
            options.actions.forEach(action => {
                const button = document.createElement('button');
                button.textContent = action.label;
                button.addEventListener('click', action.handler);
                notificationActions.appendChild(button);
            });
            notificationActions.style.display = 'flex';
        } else {
            notificationActions.style.display = 'none';
        }
        
        // Auto-hide only if not persistent
        if (!options.persistent) {
            setTimeout(() => {
                notification.classList.remove('show');
            }, options.duration || 5000);
        }
        
        // Return dismiss function
        return () => {
            notification.classList.remove('show');
        };
    },

    // Load campaigns from API (first page)
    async loadCampaigns() {
        try {
            this.showLoading(true);
            
            // Reset campaigns array
            this.state.campaigns = [];
            
            // Build URL with pagination parameters
            const url = new URL(this.config.apiBase, window.location.origin);
            url.searchParams.append('page', this.state.pagination.page);
            url.searchParams.append('pageSize', this.state.pagination.pageSize);
            
            // Include authorization header with admin token
            const response = await fetch(url, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                }
            });
                
            if (!response.ok) {
                throw new Error(`Failed to load campaigns: ${response.statusText}`);
            }
            
            const data = await response.json();
            const campaigns = Array.isArray(data.campaigns) ? data.campaigns : data;
            
            // Update state
            this.state.campaigns = campaigns;
            
            // Update pagination state
            this.state.pagination.hasMore = campaigns.length >= this.state.pagination.pageSize;
            
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
    
    // Load more campaigns (next page)
    async loadMoreCampaigns() {
        // Mark as loading to prevent multiple requests
        this.state.pagination.loading = true;
        
        try {
            // Increment page number
            this.state.pagination.page++;
            
            // Show loading indicator in the grid
            const campaignGrid = document.getElementById('campaign-grid');
            const loadingIndicator = document.createElement('div');
            loadingIndicator.id = 'infinite-scroll-loading';
            loadingIndicator.style.gridColumn = '1 / -1';
            loadingIndicator.style.textAlign = 'center';
            loadingIndicator.style.padding = '1.5rem';
            loadingIndicator.innerHTML = '<div class="spinner" style="border-top-color: var(--primary)"></div>';
            campaignGrid.appendChild(loadingIndicator);
            
            // Build URL with pagination parameters
            const url = new URL(this.config.apiBase, window.location.origin);
            url.searchParams.append('page', this.state.pagination.page);
            url.searchParams.append('pageSize', this.state.pagination.pageSize);
            
            // Include authorization header with admin token
            const response = await fetch(url, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                }
            });
                
            if (!response.ok) {
                throw new Error(`Failed to load more campaigns: ${response.statusText}`);
            }
            
            const data = await response.json();
            const newCampaigns = Array.isArray(data.campaigns) ? data.campaigns : data;
            
            // Remove loading indicator
            const loadingElement = document.getElementById('infinite-scroll-loading');
            if (loadingElement) {
                loadingElement.remove();
            }
            
            // Update state with new campaigns
            this.state.campaigns = [...this.state.campaigns, ...newCampaigns];
            
            // Update pagination state
            this.state.pagination.hasMore = newCampaigns.length >= this.state.pagination.pageSize;
            
            // Recalculate statistics
            this.calculateStats();
            
            // Render all campaigns
            this.renderCampaigns();
            this.renderStats();
        } catch (error) {
            console.error('Error loading more campaigns:', error);
            this.showNotification(error.message, 'error');
            
            // Remove loading indicator
            const loadingElement = document.getElementById('infinite-scroll-loading');
            if (loadingElement) {
                loadingElement.remove();
            }
        } finally {
            // Reset loading state
            this.state.pagination.loading = false;
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
        
        // Clear grid only for initial load (page 1), preserve when loading more
        if (this.state.pagination.page === 1) {
            campaignGrid.innerHTML = '';
        } else {
            // Preserve existing content, but remove any loading indicators
            const loadingElement = document.getElementById('infinite-scroll-loading');
            if (loadingElement) {
                loadingElement.remove();
            }
        }
        
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
        
        // Prevent duplicate rendering of campaigns that are already in the DOM
        const existingCampaignIds = new Set();
        document.querySelectorAll('.campaign-card').forEach(card => {
            const campaignId = card.getAttribute('data-campaign-id');
            if (campaignId) {
                existingCampaignIds.add(campaignId);
            }
        });
        
        // Filter out campaigns that are already rendered
        const newCampaigns = filteredCampaigns.filter(campaign => 
            !existingCampaignIds.has(campaign._id)
        );
        
        // If there are no campaigns to show (filtered or new)
        if (filteredCampaigns.length === 0) {
            campaignGrid.innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                    <p style="color: var(--gray-600);">No campaigns found</p>
                </div>
            `;
            return;
        }
        
        // Sort new campaigns by creation date (newest first)
        newCampaigns.sort((a, b) => {
            const dateA = new Date(a.createdAt || 0);
            const dateB = new Date(b.createdAt || 0);
            return dateB - dateA;
        });
        
        // Add new campaign cards to the grid
        newCampaigns.forEach(campaign => {
            campaignGrid.appendChild(this.createCampaignCard(campaign));
        });
        
        // Show a message when there are no more campaigns to load
        if (!this.state.pagination.hasMore && this.state.pagination.page > 1) {
            const endMessage = document.createElement('div');
            endMessage.style.gridColumn = '1 / -1';
            endMessage.style.textAlign = 'center';
            endMessage.style.padding = '1.5rem';
            endMessage.style.color = 'var(--gray-600)';
            endMessage.textContent = 'All campaigns loaded';
            campaignGrid.appendChild(endMessage);
        }
    },

    // Create campaign card element
    createCampaignCard(campaign) {
        const card = document.createElement('div');
        card.className = 'campaign-card';
        
        // Add campaign ID as data attribute for tracking
        if (campaign._id) {
            card.setAttribute('data-campaign-id', campaign._id);
        }
        
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
        
        console.log("imageUrl", imageUrl);
        let imgurl = (imageUrl && imageUrl.url) ? imageUrl.url : imageUrl;

        card.innerHTML = `
            <div class="campaign-image" style="background-image: url('${imgurl}')">
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
            bannerEl.style.backgroundImage = `url('${campaign.images[0].url}')`;
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
