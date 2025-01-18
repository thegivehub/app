// User Manager Module
(function() {
    const userManager = {
        async getCurrentUser() {
            try {
                const token = localStorage.getItem('accessToken');
                if (!token) {
                    throw new Error('No authentication token found');
                }

                const response = await fetch(`${app.config.apiBase}/users/me`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    if (response.status === 401) {
                        // Token expired, redirect to login
                        window.location.href = '/login.html';
                        return null;
                    }
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const userData = await response.json();
                return userData;
            } catch (error) {
                console.error('Error fetching user data:', error);
                return null;
            }
        },

        async updateUserProfile(profileData) {
            try {
                const token = localStorage.getItem('accessToken');
                if (!token) {
                    throw new Error('No authentication token found');
                }

                const response = await fetch(`${app.config.apiBase}/users/profile`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(profileData)
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                return await response.json();
            } catch (error) {
                console.error('Error updating profile:', error);
                throw error;
            }
        },

        async getUserPreferences() {
            const token = localStorage.getItem('accessToken');
            if (!token) {
                throw new Error('No authentication token found');
            }

            const response = await fetch(`${app.config.apiBase}/preferences/me`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return response.json();
        } 
    };

    // Add to global app object
    window.app = window.app || {};
    window.app.userManager = userManager;
})();
