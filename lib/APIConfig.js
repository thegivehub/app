// API Configuration Module
(function() {
    const apiConfig = {
        BASE_URL:location.protocol + "//" + location.hostname + '/api',
        ENDPOINTS: {
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
            // Add KYC endpoints
            KYC: {
                INITIATE: '/kyc/initiate',
                STATUS: '/kyc/status',
                ADMIN_OVERRIDE: '/kyc/admin-override',
                REPORT: '/kyc/report'
            }
        },
        
        // Helper method to build URLs
        buildUrl(endpoint, params = {}) {
            const url = new URL(this.BASE_URL + endpoint);
            Object.keys(params).forEach(key => {
                url.searchParams.append(key, params[key]);
            });
            return url.toString();
        },

        // Helper method for making authenticated requests
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

            // Handle 401 (Unauthorized) - Token expired
            if (response.status === 401) {
                const refreshed = await this.refreshToken();
                if (refreshed) {
                    // Retry original request with new token
                    return this.fetchWithAuth(endpoint, options);
                }
                // Redirect to login if refresh failed
                window.location.href = '/login.html';
                return null;
            }

            if (!response.ok) {
                top.location.href = '/login.html';
                return null;
                //throw new Error(`API Error: ${response.status}`);
            }

            return response.json();
        },

        // Token refresh handler
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
        },
        // Add this to the apiConfig object
        decodeToken(token) {
            try {
                // Split the token and get the payload part
                const base64Url = token.split('.')[1];
                const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
                const payload = JSON.parse(window.atob(base64));
                return payload;
            } catch (error) {
                console.error('Error decoding token:', error);
                return null;
            }
        }
    };

    // Add to global app object
    window.app = window.app || {};
    window.app.api = apiConfig;
})();
