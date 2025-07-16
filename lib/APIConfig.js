// API Configuration Module
class APIConfig {
    constructor() {
        this.BASE_URL = location.protocol + "//" + location.hostname + '/api';
        this.ENDPOINTS = {
            AUTH: {
                LOGIN: '/auth/login',
                REGISTER: '/auth/register',
                VERIFY: '/auth/verify-code',
                REFRESH: '/auth/refresh',
                LOGOUT: '/auth/logout',
                FORGOT_PASSWORD: '/auth/forgot-password',
                RESET_PASSWORD: '/auth/reset-password'
            },
            USER: {
                PROFILE: '/user/me',
                SETTINGS: '/user/settings',
                PREFERENCES: '/preferences/me',
                PASSWORD: '/user/password',
                AVATAR: '/user/avatar'
            },
            KYC: {
                INITIATE: '/kyc/initiate',
                STATUS: '/kyc/status',
                ADMIN_OVERRIDE: '/kyc/admin-override',
                REPORT: '/kyc/report',
                COMPLIANCE: '/kyc/compliance'
            }
        };
    }

    buildUrl(endpoint, params = {}) {
        const url = new URL(this.BASE_URL + endpoint);
        Object.keys(params).forEach(key => {
            url.searchParams.append(key, params[key]);
        });
        return url.toString();
    }

    async fetchWithAuth(endpoint, options = {}) {
        const token = localStorage.getItem('accessToken');
        if (!token) {
            throw new Error('No authentication token found');
        }

        const defaultHeaders = {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        };

        const response = await fetch(this.buildUrl(endpoint), {
            ...options,
            headers: {
                ...defaultHeaders,
                ...options.headers
            }
        });

        if (response.status === 401) {
            const refreshed = await this.refreshToken();
            if (refreshed) {
                return this.fetchWithAuth(endpoint, options);
            }
            window.location.href = '/pages/login.html';
            return null;
        }

        if (!response.ok) {
            window.location.href = '/pages/login.html';
            return null;
        }

        return response.json();
    }

    async refreshToken() {
        const refreshToken = localStorage.getItem('refreshToken');
        if (!refreshToken) return false;

        try {
            const response = await fetch(this.buildUrl(this.ENDPOINTS.AUTH.REFRESH), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ refreshToken })
            });

            if (!response.ok) return false;

            const data = await response.json();
            localStorage.setItem('accessToken', data.accessToken);
            return true;
        } catch (error) {
            console.error('Token refresh failed:', error);
            return false;
        }
    }

    decodeToken(token) {
        try {
            const base64Url = token.split('.')[1];
            const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            return JSON.parse(window.atob(base64));
        } catch (error) {
            console.error('Error decoding token:', error);
            return null;
        }
    }
}

// Create global instance
window.APIConfig = APIConfig;
window.app = window.app || {};
window.app.api = window.app.api || new APIConfig();
