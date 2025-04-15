// Settings Manager Module
class SettingsManager {
    constructor() {
        // Initialize any required state
    }

    async getUserProfile() {
        return window.app.api.fetchWithAuth(window.app.api.ENDPOINTS.USER.PROFILE);
    }

    async updateProfile(profileData) {
        return window.app.api.fetchWithAuth(window.app.api.ENDPOINTS.USER.PROFILE, {
            method: 'PUT',
            body: JSON.stringify(profileData)
        });
    }

    async getPreferences() {
        return window.app.api.fetchWithAuth(window.app.api.ENDPOINTS.USER.PREFERENCES);
    }

    async updatePreferences(preferences) {
        return window.app.api.fetchWithAuth(window.app.api.ENDPOINTS.USER.PREFERENCES, {
            method: 'PUT',
            body: JSON.stringify(preferences)
        });
    }

    async updatePassword(currentPassword, newPassword) {
        return window.app.api.fetchWithAuth(window.app.api.ENDPOINTS.USER.PASSWORD, {
            method: 'PUT',
            body: JSON.stringify({
                currentPassword,
                newPassword
            })
        });
    }

    async uploadAvatar(file) {
        const formData = new FormData();
        formData.append('avatar', file);

        return window.app.api.fetchWithAuth(window.app.api.ENDPOINTS.USER.AVATAR, {
            method: 'POST',
            headers: {
                // Let browser set content type for form data
                'Content-Type': undefined
            },
            body: formData
        });
    }
}

// Create global instance
window.SettingsManager = SettingsManager;
window.app = window.app || {};
window.app.settingsManager = window.app.settingsManager || new SettingsManager();
