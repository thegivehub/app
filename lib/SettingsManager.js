// Settings Manager Module
(function() {
    const settingsManager = {
        async getUserProfile() {
            return app.api.fetchWithAuth(app.api.ENDPOINTS.USER.PROFILE);
        },

        async updateProfile(profileData) {
            return app.api.fetchWithAuth(app.api.ENDPOINTS.USER.PROFILE, {
                method: 'PUT',
                body: JSON.stringify(profileData)
            });
        },

        async getPreferences() {
            return app.api.fetchWithAuth(app.api.ENDPOINTS.USER.PREFERENCES);
        },

        async updatePreferences(preferences) {
            return app.api.fetchWithAuth(app.api.ENDPOINTS.USER.PREFERENCES, {
                method: 'PUT',
                body: JSON.stringify(preferences)
            });
        },

        async updatePassword(currentPassword, newPassword) {
            return app.api.fetchWithAuth(app.api.ENDPOINTS.USER.PASSWORD, {
                method: 'PUT',
                body: JSON.stringify({
                    currentPassword,
                    newPassword
                })
            });
        },

        async uploadAvatar(file) {
            const formData = new FormData();
            formData.append('avatar', file);

            return app.api.fetchWithAuth(app.api.ENDPOINTS.USER.AVATAR, {
                method: 'POST',
                headers: {
                    // Let browser set content type for form data
                    'Content-Type': undefined
                },
                body: formData
            });
        },
        async getUserProfile() {
            console.log('Making profile request to:', app.api.ENDPOINTS.USER.PROFILE);
            const response = await app.api.fetchWithAuth(app.api.ENDPOINTS.USER.PROFILE);
            console.log('Profile response:', response);
            return response;
        }
    };

    // Add to global app object
    window.app = window.app || {};
    window.app.settingsManager = settingsManager;
})();
