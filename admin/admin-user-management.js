// Admin User Management JavaScript
document.addEventListener('DOMContentLoaded', () => {
    // Initialize the admin user management functionality
    AdminUserManagement.init();
});

// Main admin module for user management
const AdminUserManagement = {
    // App state
    state: {
        users: [],
        filteredUsers: [],
        currentUser: null,
        filter: 'all',
        searchQuery: '',
        currentPage: 1,
        itemsPerPage: 10,
        stats: {
            active: 0,
            pending: 0,
            suspended: 0,
            total: 0
        }
    },

    // Configuration
    config: {
        apiBase: '/api.php/admin/users'
    },

    // Initialize the module
    init() {
        this.setupEventListeners();
        this.loadUsers();
    },

    // Set up event listeners
    setupEventListeners() {
        // Filter by status
        document.getElementById('status-filter').addEventListener('change', (e) => {
            this.state.filter = e.target.value;
            this.state.currentPage = 1;
            this.loadUsers();
        });

        // Search users
        document.getElementById('search-input').addEventListener('input', (e) => {
            this.state.searchQuery = e.target.value.toLowerCase();
            this.state.currentPage = 1;
            this.loadUsers();
        });

        // Refresh users
        document.getElementById('refresh-btn').addEventListener('click', () => {
            this.loadUsers();
        });

        // Add user button
        document.getElementById('add-user-btn').addEventListener('click', () => {
            this.showAddUserModal();
        });

        // Close user modal
        document.getElementById('close-modal').addEventListener('click', () => {
            this.hideUserModal();
        });

        // Close add user modal
        document.getElementById('close-add-user-modal').addEventListener('click', () => {
            this.hideAddUserModal();
        });

        // Cancel add user
        document.getElementById('cancel-add-user').addEventListener('click', () => {
            this.hideAddUserModal();
        });

        // Cancel user edit
        document.getElementById('cancel-user-edit').addEventListener('click', () => {
            this.hideUserModal();
        });

        // Save new user
        document.getElementById('save-new-user').addEventListener('click', () => {
            this.saveNewUser();
        });

        // Save user changes
        document.getElementById('save-user-changes').addEventListener('click', () => {
            this.saveUserChanges();
        });

        // Reset password
        document.getElementById('btn-reset-password').addEventListener('click', () => {
            this.resetUserPassword();
        });

        // Toggle status
        document.getElementById('btn-toggle-status').addEventListener('click', () => {
            this.toggleUserStatus();
        });

        // Toggle admin role
        document.getElementById('btn-toggle-admin').addEventListener('click', () => {
            this.toggleAdminRole();
        });

        // Delete user modal events
        if (document.getElementById('close-delete-modal')) {
            document.getElementById('close-delete-modal').addEventListener('click', () => {
                this.hideDeleteModal();
            });
            
            document.getElementById('cancel-delete-user').addEventListener('click', () => {
                this.hideDeleteModal();
            });
            
            document.getElementById('confirm-delete-user').addEventListener('click', () => {
                this.deleteUser();
            });
        }

        // Export users modal events
        if (document.getElementById('close-export-modal')) {
            document.getElementById('close-export-modal').addEventListener('click', () => {
                this.hideExportModal();
            });
            
            document.getElementById('cancel-export').addEventListener('click', () => {
                this.hideExportModal();
            });
            
            document.getElementById('confirm-export').addEventListener('click', () => {
                this.exportUsers();
            });
        }

        // Import users modal events
        if (document.getElementById('close-import-modal')) {
            document.getElementById('close-import-modal').addEventListener('click', () => {
                this.hideImportModal();
            });
            
            document.getElementById('cancel-import').addEventListener('click', () => {
                this.hideImportModal();
            });
            
            document.getElementById('confirm-import').addEventListener('click', () => {
                this.importUsers();
            });
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

    // Load users from API with filtering applied directly on the backend
    async loadUsers() {
        try {
            this.showLoading(true);
            
            // Build query parameters for filtering and pagination
            const params = new URLSearchParams();
            
            // Add filter if it's not 'all'
            if (this.state.filter !== 'all') {
                if (this.state.filter === 'admin') {
                    params.append('role', 'admin');
                } else {
                    params.append('status', this.state.filter);
                }
            }
            
            // Add search query if provided
            if (this.state.searchQuery) {
                params.append('q', this.state.searchQuery);
            }
            
            // Add pagination
            params.append('page', this.state.currentPage);
            params.append('limit', this.state.itemsPerPage);
            
            // Add sorting
            params.append('sort', 'created');
            params.append('order', 'desc');
            
            // Include authorization header with admin token
            const response = await fetch(`${this.config.apiBase}?${params.toString()}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                }
            });
                
            if (!response.ok) {
                throw new Error(`Failed to load users: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            // Update state with received data
            this.state.users = data.users;
            this.state.filteredUsers = data.users;
            this.state.stats = data.stats;
            
            // Update pagination info
            const pagination = data.pagination || {};
            const totalPages = pagination.pages || 1;
            
            // Ensure currentPage doesn't exceed total pages
            if (this.state.currentPage > totalPages && totalPages > 0) {
                this.state.currentPage = totalPages;
                // Reload with corrected page
                this.loadUsers();
                return;
            }
            
            // Render users and stats
            this.renderUsers();
            this.renderStats();
            
            this.showLoading(false);
        } catch (error) {
            console.error('Error loading users:', error);
            this.showNotification(error.message, 'error');
            this.showLoading(false);
        }
    },

    // Render statistics
    renderStats() {
        document.getElementById('active-count').textContent = this.state.stats.active;
        document.getElementById('pending-count').textContent = this.state.stats.pending;
        document.getElementById('suspended-count').textContent = this.state.stats.suspended;
        document.getElementById('total-count').textContent = this.state.stats.total;
    },

    // Render users based on data from API
    renderUsers() {
        const userTableBody = document.getElementById('user-table-body');
        userTableBody.innerHTML = '';
        
        // Check if we have users to display
        if (!this.state.users || this.state.users.length === 0) {
            userTableBody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2rem;">
                        <p style="color: var(--gray-600);">No users found</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        // Render user rows
        this.state.users.forEach(user => {
            userTableBody.appendChild(this.createUserRow(user));
        });
        
        // Update pagination
        this.renderPagination();
    },

    // Create user row element
    createUserRow(user) {
        const row = document.createElement('tr');
        
        // Create initials for avatar
        const name = user.displayName || user.username || 'User';
        const initials = name
            .split(' ')
            .map(n => n[0])
            .join('')
            .toUpperCase();
        
        // Determine role
        let role = 'User';
        if (user.roles && user.roles.includes('admin')) {
            role = 'Admin';
        }
        
        // Determine status and class
        let statusClass = 'pending';
        let statusText = 'Pending';
        
        if (user.status === 'active') {
            statusClass = 'active';
            statusText = 'Active';
        } else if (user.status === 'suspended') {
            statusClass = 'suspended';
            statusText = 'Suspended';
        }
        
        // Format date - handle both string and MongoDB date formats
        let joinedDate = 'Unknown';
        if (user.created) {
            // Check if it's already a string date or needs conversion
            if (typeof user.created === 'string') {
                joinedDate = new Date(user.created).toLocaleDateString();
            } else if (user.created.$date) {
                joinedDate = new Date(user.created.$date).toLocaleDateString();
            } else {
                joinedDate = new Date(user.created).toLocaleDateString();
            }
        }
        
        // Build row content
        row.innerHTML = `
            <td>
                <div class="user-name-cell">
                    <div class="user-avatar">${initials}</div>
                    <div>
                        <div class="user-name">${user.displayName || 'Unnamed User'}</div>
                        <div class="user-email">${user.email || 'No email'}</div>
                    </div>
                </div>
            </td>
            <td>${user.username || 'N/A'}</td>
            <td>${role}</td>
            <td><span class="user-status ${statusClass}">${statusText}</span></td>
            <td>${joinedDate}</td>
            <td>
                <div class="user-actions">
                    <button class="btn btn-sm btn-outline view-user-btn" data-id="${user._id}">View</button>
                    <button class="btn btn-sm btn-outline edit-user-btn" data-id="${user._id}">Edit</button>
                </div>
            </td>
        `;
        
        // Add event listeners to buttons
        row.querySelector('.view-user-btn').addEventListener('click', () => {
            this.viewUserDetails(user._id);
        });
        
        row.querySelector('.edit-user-btn').addEventListener('click', () => {
            this.editUser(user._id);
        });
        
        return row;
    },

    // Render pagination based on API response data
    renderPagination() {
        const paginationContainer = document.getElementById('pagination');
        paginationContainer.innerHTML = '';
        
        const totalPages = Math.ceil(this.state.stats.total / this.state.itemsPerPage);
        
        // Update page info
        const startItem = (this.state.currentPage - 1) * this.state.itemsPerPage + 1;
        const endItem = Math.min(startItem + this.state.itemsPerPage - 1, this.state.stats.total);
        document.getElementById('page-info').textContent = 
            `Showing ${startItem} to ${endItem} of ${this.state.stats.total} users`;
        
        if (totalPages <= 1) {
            paginationContainer.style.display = 'none';
            return;
        } else {
            paginationContainer.style.display = 'flex';
        }
        
        // Previous page button
        const prevButton = document.createElement('button');
        prevButton.className = 'pagination-btn';
        prevButton.innerHTML = `
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        `;
        prevButton.disabled = this.state.currentPage === 1;
        prevButton.addEventListener('click', () => {
            if (this.state.currentPage > 1) {
                this.state.currentPage--;
                this.loadUsers();
            }
        });
        paginationContainer.appendChild(prevButton);
        
        // Page number buttons
        let startPage = Math.max(1, this.state.currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageButton = document.createElement('button');
            pageButton.className = 'pagination-btn';
            pageButton.textContent = i;
            
            if (i === this.state.currentPage) {
                pageButton.classList.add('active');
            }
            
            pageButton.addEventListener('click', () => {
                this.state.currentPage = i;
                this.loadUsers();
            });
            
            paginationContainer.appendChild(pageButton);
        }
        
        // Next page button
        const nextButton = document.createElement('button');
        nextButton.className = 'pagination-btn';
        nextButton.innerHTML = `
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        `;
        nextButton.disabled = this.state.currentPage === totalPages;
        nextButton.addEventListener('click', () => {
            if (this.state.currentPage < totalPages) {
                this.state.currentPage++;
                this.loadUsers();
            }
        });
        paginationContainer.appendChild(nextButton);
    },

    // View user details
    async viewUserDetails(userId) {
        try {
            this.showLoading(true);
            
            // Fetch detailed user information from API
            const response = await fetch(`${this.config.apiBase}/details?id=${userId}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                }
            });
            
            if (!response.ok) {
                throw new Error(`Failed to load user details: ${response.statusText}`);
            }
            
            const user = await response.json();
            
            if (!user) {
                throw new Error('User not found');
            }
            
            this.state.currentUser = user;
            
            // Populate user details in the view modal
            this.populateUserDetails(user);
            
            // Show user modal
            this.showUserModal();
            
            this.showLoading(false);
        } catch (error) {
            console.error('Error viewing user details:', error);
            this.showNotification(error.message, 'error');
            this.showLoading(false);
        }
    },

    // Edit user
    async editUser(userId) {
        try {
            this.showLoading(true);
            
            // Fetch user information from API
            const response = await fetch(`${this.config.apiBase}?id=${userId}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                }
            });
            
            if (!response.ok) {
                throw new Error(`Failed to load user: ${response.statusText}`);
            }
            
            const user = await response.json();
            
            if (!user) {
                throw new Error('User not found');
            }
            
            this.state.currentUser = user;
            
            // Populate edit form with user data
            this.populateEditForm(user);
            
            // Show add/edit user modal with edit title
            document.getElementById('add-user-title').textContent = 'Edit User';
            this.showAddUserModal();
            
            this.showLoading(false);
        } catch (error) {
            console.error('Error editing user:', error);
            this.showNotification(error.message, 'error');
            this.showLoading(false);
        }
    },

    // Populate user details in the view modal
    populateUserDetails(user) {
        // Create initials for avatar
        const name = user.displayName || user.username || 'User';
        const initials = name
            .split(' ')
            .map(n => n[0])
            .join('')
            .toUpperCase();
        
        // Set user info
        document.getElementById('user-detail-avatar').textContent = initials;
        document.getElementById('user-detail-name').textContent = user.displayName || 'Unnamed User';
        document.getElementById('user-detail-username').textContent = user.username ? `@${user.username}` : 'No username';
        
        // Set user status
        const statusEl = document.getElementById('user-detail-status');
        statusEl.className = 'user-status';
        
        if (user.status === 'active') {
            statusEl.classList.add('active');
            statusEl.textContent = 'Active';
            document.getElementById('btn-toggle-status').textContent = 'Suspend User';
        } else if (user.status === 'suspended') {
            statusEl.classList.add('suspended');
            statusEl.textContent = 'Suspended';
            document.getElementById('btn-toggle-status').textContent = 'Activate User';
        } else {
            statusEl.classList.add('pending');
            statusEl.textContent = 'Pending';
            document.getElementById('btn-toggle-status').textContent = 'Activate User';
        }
        
        // Set admin button text
        if (user.roles && user.roles.includes('admin')) {
            document.getElementById('btn-toggle-admin').textContent = 'Remove Admin';
        } else {
            document.getElementById('btn-toggle-admin').textContent = 'Make Admin';
        }
        
        // Set personal info
        const personalInfo = user.personalInfo || {};
        document.getElementById('user-detail-firstname').textContent = personalInfo.firstName || 'N/A';
        document.getElementById('user-detail-lastname').textContent = personalInfo.lastName || 'N/A';
        document.getElementById('user-detail-email').textContent = user.email || 'N/A';
        document.getElementById('user-detail-phone').textContent = personalInfo.phone || 'N/A';
        
        // Format and display location from address if available
        let locationText = 'N/A';
        if (personalInfo.address) {
            const address = personalInfo.address;
            const cityState = [];
            if (address.city) cityState.push(address.city);
            if (address.state) cityState.push(address.state);
            
            if (cityState.length > 0) {
                locationText = cityState.join(', ');
                if (address.country) {
                    locationText += `, ${address.country}`;
                }
            } else if (address.country) {
                locationText = address.country;
            }
        }
        document.getElementById('user-detail-location').textContent = locationText;
        
        // Format and display created date
        let createdDate = 'N/A';
        if (user.created) {
            // Handle different date formats
            let date;
            if (typeof user.created === 'string') {
                date = new Date(user.created);
            } else if (user.created.$date) {
                date = new Date(user.created.$date);
            } else {
                date = new Date(user.created);
            }
                
            createdDate = date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
        document.getElementById('user-detail-created').textContent = createdDate;
        
        // Render user activity
        this.renderUserActivity(user);
    },

    // Populate edit form with user data
    populateEditForm(user) {
        const personalInfo = user.personalInfo || {};
        
        document.getElementById('new-user-firstname').value = personalInfo.firstName || '';
        document.getElementById('new-user-lastname').value = personalInfo.lastName || '';
        document.getElementById('new-user-email').value = user.email || '';
        document.getElementById('new-user-username').value = user.username || '';
        document.getElementById('new-user-password').value = ''; // Don't populate password
        document.getElementById('new-user-status').value = user.status || 'pending';
        document.getElementById('new-user-phone').value = personalInfo.phone || '';
        
        // Set role
        if (user.roles && user.roles.includes('admin')) {
            document.getElementById('new-user-role').value = 'admin';
        } else {
            document.getElementById('new-user-role').value = 'user';
        }
    },

    // Render user activity (campaigns, logins, etc.)
    async renderUserActivity(user) {
        try {
            // Fetch user activity data from API
            const response = await fetch(`${this.config.apiBase}/activity?id=${user._id}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                }
            });
            
            if (!response.ok) {
                throw new Error(`Failed to load user activity: ${response.statusText}`);
            }
            
            const activity = await response.json();
            const activityList = document.getElementById('user-activity-list');
            
            // Clear existing activity
            activityList.innerHTML = '';
            
            // Display campaigns if available
            if (activity.campaigns && activity.campaigns.length > 0) {
                activity.campaigns.forEach(campaign => {
                    const activityItem = this.createActivityItem(
                        'Campaign',
                        `Created campaign "${campaign.title || 'Untitled Campaign'}"`,
                        campaign.createdAt ? new Date(campaign.createdAt).toLocaleDateString() : 'Unknown date'
                    );
                    activityList.appendChild(activityItem);
                });
            }
            
            // Display donations if available
            if (activity.donations && activity.donations.length > 0) {
                activity.donations.forEach(donation => {
                    const activityItem = this.createActivityItem(
                        'Donation',
                        `Donated ${this.formatCurrency(donation.amount)} to "${donation.campaignTitle || 'Unknown Campaign'}"`,
                        donation.createdAt ? new Date(donation.createdAt).toLocaleDateString() : 'Unknown date'
                    );
                    activityList.appendChild(activityItem);
                });
            }
            
            // Display logins if available
            if (activity.logins && activity.logins.length > 0) {
                activity.logins.forEach(login => {
                    const activityItem = this.createActivityItem(
                        'Login',
                        `Logged in from ${login.ipAddress || 'Unknown IP'}`,
                        login.timestamp ? new Date(login.timestamp).toLocaleDateString() : 'Unknown date'
                    );
                    activityList.appendChild(activityItem);
                });
            }
            
            // If no activity found, display a message
            if (activityList.children.length === 0) {
                const noActivityItem = document.createElement('div');
                noActivityItem.style.textAlign = 'center';
                noActivityItem.style.padding = '1rem';
                noActivityItem.style.color = 'var(--gray-600)';
                noActivityItem.textContent = 'No recent activity found';
                activityList.appendChild(noActivityItem);
            }
            
        } catch (error) {
            console.error('Error rendering user activity:', error);
            
            // Display error message
            const activityList = document.getElementById('user-activity-list');
            activityList.innerHTML = `
                <div style="text-align: center; padding: 1rem; color: var(--gray-600);">
                    Failed to load activity: ${error.message}
                </div>
            `;
        }
    },

    // Create activity item element
    createActivityItem(icon, title, date) {
        const item = document.createElement('div');
        item.className = 'user-activity-item';
        
        // Determine icon SVG
        let iconSvg = '';
        switch (icon) {
            case 'Campaign':
                iconSvg = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>';
                break;
            case 'Donation':
                iconSvg = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9" y2="9"></line><line x1="15" y1="9" x2="15" y2="9"></line></svg>';
                break;
            case 'Login':
                iconSvg = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>';
                break;
            default:
                iconSvg = '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12" y2="16"></line></svg>';
        }
        
        item.innerHTML = `
            <div class="user-activity-icon">
                ${iconSvg}
            </div>
            <div class="user-activity-content">
                <div class="user-activity-title">${title}</div>
                <div class="user-activity-meta">
                    <span>${date}</span>
                </div>
            </div>
        `;
        
        return item;
    },

    // Show user modal
    showUserModal() {
        document.getElementById('user-modal').classList.add('active');
    },

    // Hide user modal
    hideUserModal() {
        document.getElementById('user-modal').classList.remove('active');
    },

    // Show add user modal
    showAddUserModal() {
        // If it's a new user, clear the form
        if (!this.state.currentUser) {
            document.getElementById('add-user-form').reset();
            document.getElementById('add-user-title').textContent = 'Add New User';
        }
        
        document.getElementById('add-user-modal').classList.add('active');
    },

    // Hide add user modal
    hideAddUserModal() {
        document.getElementById('add-user-modal').classList.remove('active');
    },

    // Show delete user confirmation modal
    showDeleteModal(user) {
        this.state.currentUser = user;
        
        // Set user info in the modal
        document.getElementById('delete-user-username').textContent = user.username || 'N/A';
        document.getElementById('delete-user-email').textContent = user.email || 'N/A';
        
        // Show the modal
        document.getElementById('delete-user-modal').classList.add('active');
    },

    // Hide delete user modal
    hideDeleteModal() {
        document.getElementById('delete-user-modal').classList.remove('active');
    },

    // Show export users modal
    showExportModal() {
        document.getElementById('export-users-modal').classList.add('active');
    },

    // Hide export users modal
    hideExportModal() {
        document.getElementById('export-users-modal').classList.remove('active');
    },

    // Show import users modal
    showImportModal() {
        document.getElementById('import-users-modal').classList.add('active');
    },

    // Hide import users modal
    hideImportModal() {
        document.getElementById('import-users-modal').classList.remove('active');
    },

    // Save changes from user detail view
    async saveUserChanges() {
        // This would handle saving changes from the user details modal
        // For now, we'll just close the modal
        this.hideUserModal();
        this.showNotification('Changes saved successfully');
    },

    // Reset user password
    async resetUserPassword() {
        try {
            if (!this.state.currentUser) {
                throw new Error('No user selected');
            }

            // Confirm password reset
            const confirmed = confirm('Are you sure you want to reset this user\'s password?');
            if (!confirmed) {
                return;
            }

            this.showLoading(true);

            // Send password reset request to API
            const response = await fetch(`${this.config.apiBase}/reset-password?id=${this.state.currentUser._id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                }
            });

            if (!response.ok) {
                throw new Error(`Failed to reset password: ${response.statusText}`);
            }

            const result = await response.json();

            // Show success message with temporary password
            this.showNotification(`Password reset to: ${result.tempPassword}`, 'success');

            this.showLoading(false);
        } catch (error) {
            console.error('Error resetting password:', error);
            this.showNotification(error.message, 'error');
            this.showLoading(false);
        }
    },

    // Toggle user status (active/suspended)
    async toggleUserStatus() {
        try {
            if (!this.state.currentUser) {
                throw new Error('No user selected');
            }

            this.showLoading(true);

            // Determine new status
            const currentStatus = this.state.currentUser.status || 'pending';
            const newStatus = currentStatus === 'active' ? 'suspended' : 'active';

            // Send status update to API
            const response = await fetch(`${this.config.apiBase}?id=${this.state.currentUser._id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                },
                body: JSON.stringify({ status: newStatus })
            });

            if (!response.ok) {
                throw new Error(`Failed to update status: ${response.statusText}`);
            }

            // Get updated user data
            const updatedUser = await response.json();

            // Update current user
            this.state.currentUser = updatedUser;

            // Update UI
            this.populateUserDetails(updatedUser);

            // Reload users to refresh the list
            this.loadUsers();

            // Show success message
            this.showNotification(`User ${newStatus === 'active' ? 'activated' : 'suspended'} successfully`);

            this.showLoading(false);
        } catch (error) {
            console.error('Error toggling status:', error);
            this.showNotification(error.message, 'error');
            this.showLoading(false);
        }
    },

    // Toggle admin role
    async toggleAdminRole() {
        try {
            if (!this.state.currentUser) {
                throw new Error('No user selected');
            }

            this.showLoading(true);

            // Determine if user is already an admin
            const isAdmin = this.state.currentUser.roles && this.state.currentUser.roles.includes('admin');

            // Prepare new roles
            let newRoles;
            if (isAdmin) {
                // Remove admin role
                newRoles = ['user'];
            } else {
                // Add admin role
                newRoles = ['user', 'admin'];
            }

            // Send role update to API
            const response = await fetch(`${this.config.apiBase}?id=${this.state.currentUser._id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                },
                body: JSON.stringify({ roles: newRoles })
            });

            if (!response.ok) {
                throw new Error(`Failed to update roles: ${response.statusText}`);
            }

            // Get updated user data
            const updatedUser = await response.json();

            // Update current user
            this.state.currentUser = updatedUser;

            // Update UI
            this.populateUserDetails(updatedUser);

            // Reload users to refresh the list
            this.loadUsers();

            // Show success message
            const actionText = isAdmin ? 'removed from' : 'added to';
            this.showNotification(`Admin privileges ${actionText} user successfully`);

            this.showLoading(false);
        } catch (error) {
            console.error('Error toggling admin role:', error);
            this.showNotification(error.message, 'error');
            this.showLoading(false);
        }
    },

    // Delete user
    async deleteUser() {
        try {
            if (!this.state.currentUser) {
                throw new Error('No user selected');
            }

            this.showLoading(true);

            // Send delete request to API
            const response = await fetch(`${this.config.apiBase}?id=${this.state.currentUser._id}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                }
            });

            if (!response.ok) {
                throw new Error(`Failed to delete user: ${response.statusText}`);
            }

            // Hide delete modal
            this.hideDeleteModal();

            // Reload users to refresh the list
            this.loadUsers();

            // Show success message
            this.showNotification('User deleted successfully');

            // Clear current user
            this.state.currentUser = null;

            this.showLoading(false);
        } catch (error) {
            console.error('Error deleting user:', error);
            this.showNotification(error.message, 'error');
            this.showLoading(false);
        }
    },

    // Export users
    async exportUsers() {
        try {
            const format = document.getElementById('export-format').value;
            const filter = document.getElementById('export-filter').value;

            // Get selected fields
            const fields = [];
            document.querySelectorAll('[id^="export-field-"]').forEach(checkbox => {
                if (checkbox.checked) {
                    fields.push(checkbox.id.replace('export-field-', ''));
                }
            });

            if (fields.length === 0) {
                this.showNotification('Please select at least one field to export', 'error');
                return;
            }

            this.showLoading(true);

            // Build query parameters
            const params = new URLSearchParams();
            params.append('format', format);
            params.append('fields', fields.join(','));

            // Apply filter
            if (filter !== 'current') {
                if (filter === 'all') {
                    // No filter needed
                } else if (filter === 'admin') {
                    params.append('role', 'admin');
                } else {
                    params.append('status', filter);
                }
            } else {
                // Use current filters
                if (this.state.filter !== 'all') {
                    if (this.state.filter === 'admin') {
                        params.append('role', 'admin');
                    } else {
                        params.append('status', this.state.filter);
                    }
                }

                if (this.state.searchQuery) {
                    params.append('q', this.state.searchQuery);
                }
            }

            // Make export request
            const response = await fetch(`${this.config.apiBase}/export?${params.toString()}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                }
            });

            if (!response.ok) {
                throw new Error(`Failed to export users: ${response.statusText}`);
            }

            // Process response based on format
            if (format === 'csv') {
                const csvContent = await response.text();

                // Create download link
                const downloadLink = document.createElement('a');
                downloadLink.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvContent);
                downloadLink.download = `user-export-${new Date().toISOString().split('T')[0]}.csv`;

                // Trigger download
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
            } else if (format === 'json') {
                const jsonData = await response.json();

                // Convert to JSON string
                const jsonContent = JSON.stringify(jsonData, null, 2);

                // Create download link
                const downloadLink = document.createElement('a');
                downloadLink.href = 'data:application/json;charset=utf-8,' + encodeURIComponent(jsonContent);
                downloadLink.download = `user-export-${new Date().toISOString().split('T')[0]}.json`;

                // Trigger download
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
            }

            // Hide export modal
            this.hideExportModal();

            // Show success message
            this.showNotification('Users exported successfully');

            this.showLoading(false);
        } catch (error) {
            console.error('Error exporting users:', error);
            this.showNotification(error.message, 'error');
            this.showLoading(false);
        }
    },

    // Populate role checkboxes based on user data
    populateRoleCheckboxes(user) {
        // Get all role checkboxes
        const roleCheckboxes = document.querySelectorAll('[name="user-roles"]');
        
        // Reset all checkboxes (except 'user' which is always checked)
        roleCheckboxes.forEach(checkbox => {
            if (checkbox.value !== 'user') {
                checkbox.checked = false;
            }
        });
        
        // Check boxes based on user roles
        if (user && user.roles && Array.isArray(user.roles)) {
            user.roles.forEach(role => {
                const checkbox = document.getElementById(`role-${role}`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
        }
        
        // Update the role description
        this.updateRoleDescription();
    },

    // Update role description based on selected roles
    updateRoleDescription() {
        const roleCheckboxes = document.querySelectorAll('[name="user-roles"]');
        const roleDescriptionText = document.getElementById('role-description-text');
        
        const selectedRoles = Array.from(roleCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);
        
        // Always ensure 'user' role is included
        if (!selectedRoles.includes('user')) {
            selectedRoles.push('user');
        }
        
        // Update description based on selected roles
        if (selectedRoles.includes('admin')) {
            roleDescriptionText.textContent = 'This user will have full administrative access.';
        } else if (selectedRoles.includes('campaigner') && selectedRoles.includes('donor')) {
            roleDescriptionText.textContent = 'This user can both create campaigns and make donations.';
        } else if (selectedRoles.includes('campaigner')) {
            roleDescriptionText.textContent = 'This user can create and manage campaigns.';
        } else if (selectedRoles.includes('donor')) {
            roleDescriptionText.textContent = 'This user can make donations to campaigns.';
        } else {
            roleDescriptionText.textContent = 'Standard user with basic permissions.';
        }
    },

    // Get selected roles from checkboxes
    getSelectedRoles() {
        const roleCheckboxes = document.querySelectorAll('[name="user-roles"]');
        
        // Get all checked role values
        const selectedRoles = Array.from(roleCheckboxes)
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.value);
        
        // Ensure 'user' role is always included
        if (!selectedRoles.includes('user')) {
            selectedRoles.push('user');
        }
        
        return selectedRoles;
    },

    async saveNewUser() {
        try {
            const firstName = document.getElementById('new-user-firstname').value;
            const lastName = document.getElementById('new-user-lastname').value;
            const email = document.getElementById('new-user-email').value;
            const username = document.getElementById('new-user-username').value;
            const password = document.getElementById('new-user-password').value;
            const status = document.getElementById('new-user-status').value;
            const phone = document.getElementById('new-user-phone').value;
            
            // Get roles from checkboxes
            const roles = this.getSelectedRoles();
            
            // Validate required fields
            if (!firstName || !lastName || !email || !username) {
                this.showNotification('Please fill in all required fields', 'error');
                return;
            }
            
            // For a new user, password is required
            if (!this.state.currentUser && !password) {
                this.showNotification('Password is required for new users', 'error');
                return;
            }
            
            this.showLoading(true);
            
            // Prepare user data
            const userData = {
                email: email,
                username: username,
                status: status,
                personalInfo: {
                    firstName: firstName,
                    lastName: lastName,
                    phone: phone
                },
                roles: roles
            };
            
            // Add password if provided
            if (password) {
                userData.password = password;
            }
            
            let response;
            
            if (this.state.currentUser) {
                // Update existing user
                response = await fetch(`${this.config.apiBase}?id=${this.state.currentUser._id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                    },
                    body: JSON.stringify(userData)
                });
            } else {
                // Create new user
                response = await fetch(this.config.apiBase, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                    },
                    body: JSON.stringify(userData)
                });
            }
            
            if (!response.ok) {
                throw new Error(`Failed to ${this.state.currentUser ? 'update' : 'create'} user: ${response.statusText}`);
            }
            
            // Reload users to refresh the list
            this.loadUsers();
            
            // Show success message
            this.showNotification(`User ${this.state.currentUser ? 'updated' : 'created'} successfully`);
            
            // Hide modal and clear current user
            this.hideAddUserModal();
            this.state.currentUser = null;
            
            this.showLoading(false);
        } catch (error) {
            console.error('Error saving user:', error);
            this.showNotification(error.message, 'error');
            this.showLoading(false);
        }
    },
    // Import users
    async importUsers() {
        try {
            const fileInput = document.getElementById('import-file');

            if (!fileInput.files || fileInput.files.length === 0) {
                this.showNotification('Please select a CSV file to import', 'error');
                return;
            }

            const file = fileInput.files[0];

            if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
                this.showNotification('Please select a valid CSV file', 'error');
                return;
            }

            this.showLoading(true);

            // Get import options
            const generatePasswords = document.getElementById('import-generate-passwords').checked;
            const updateExisting = document.getElementById('import-update-existing').checked;
            const sendNotifications = document.getElementById('import-send-notifications').checked;

            // Create form data
            const formData = new FormData();
            formData.append('file', file);
            formData.append('generatePasswords', generatePasswords);
            formData.append('updateExisting', updateExisting);
            formData.append('sendNotifications', sendNotifications);

            // Send import request
            const response = await fetch(`${this.config.apiBase}/import`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('adminToken')}`
                },
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Failed to import users: ${response.statusText}`);
            }

            const result = await response.json();

            // Hide import modal
            this.hideImportModal();

            // Reload users to show the imported ones
            this.loadUsers();

            // Show success message
            this.showNotification(`Successfully imported ${result.imported} users (${result.created} created, ${result.updated} updated)`);

            this.showLoading(false);
        } catch (error) {
            console.error('Error importing users:', error);
            this.showNotification(error.message, 'error');
            this.showLoading(false);
        }
    },

    // Format currency
    formatCurrency(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    },

    // Format date
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
};

// Initialize the module when the DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    AdminUserManagement.init();
});
