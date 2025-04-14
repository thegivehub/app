// KYC Manager Module
class KycManager {
    constructor() {
        // Initialize any required state
    }

    /**
     * Initialize verification process
     * @param {Object} userInfo Optional additional user information
     * @returns {Promise<Object>} Response with redirect URL
     */
    async initiateVerification(userInfo = {}) {
        try {
            const response = await fetch(`${window.app.api.buildUrl('/kyc/initiate')}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('accessToken')}`
                },
                body: JSON.stringify({ userInfo })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Failed to initiate verification');
            }

            return await response.json();
        } catch (error) {
            console.error('KYC initiation error:', error);
            throw error;
        }
    }

    /**
     * Get verification status for current user
     * @returns {Promise<Object>} Verification status information
     */
    async getVerificationStatus() {
        try {
            const response = await fetch(`${window.app.api.buildUrl('/kyc/status')}`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('accessToken')}`
                }
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Failed to get verification status');
            }

            return await response.json();
        } catch (error) {
            console.error('KYC status error:', error);
            throw error;
        }
    }

    /**
     * Handle redirect back from Jumio
     * @param {URLSearchParams} params URL search parameters
     * @returns {Object} Result information
     */
    handleRedirect(params) {
        const status = params.get('status');
        const errorCode = params.get('errorCode');
        
        if (status === 'SUCCESS') {
            return {
                success: true,
                message: 'Verification submitted successfully. We will notify you once the verification is complete.'
            };
        } else {
            return {
                success: false,
                error: errorCode ? `Verification failed: ${errorCode}` : 'Verification failed or was cancelled'
            };
        }
    }

    /**
     * Open the Jumio verification in a new window
     * @param {string} redirectUrl Jumio redirect URL
     * @returns {Window} Opened window reference
     */
    openVerificationWindow(redirectUrl) {
        const width = 850;
        const height = 650;
        const left = (window.innerWidth - width) / 2;
        const top = (window.innerHeight - height) / 2;
        
        return window.open(
            redirectUrl,
            'jumio_verification',
            `width=${width},height=${height},top=${top},left=${left},resizable=yes,scrollbars=yes,status=yes`
        );
    }

    /**
     * Open verification flow
     * Handles the entire process of initiating and opening verification
     * @param {Object} options Configuration options
     * @returns {Promise<Object>} Result information
     */
    async startVerification(options = {}) {
        try {
            // Get current user profile to pass to Jumio
            let userInfo = {};
            
            if (options.includeUserInfo) {
                const userProfile = await app.settingsManager.getUserProfile();
                
                if (userProfile && userProfile.personalInfo) {
                    userInfo = {
                        firstName: userProfile.personalInfo.firstName,
                        lastName: userProfile.personalInfo.lastName,
                        email: userProfile.email
                    };
                }
            }
            
            // Initiate verification
            const initResult = await this.initiateVerification(userInfo);
            
            if (!initResult.success) {
                throw new Error(initResult.error || 'Failed to start verification');
            }
            
            // Open verification window
            if (options.autoOpen !== false && initResult.redirectUrl) {
                this.openVerificationWindow(initResult.redirectUrl);
            }
            
            return {
                success: true,
                redirectUrl: initResult.redirectUrl,
                verificationId: initResult.verificationId,
                message: 'Verification initiated successfully'
            };
        } catch (error) {
            console.error('Verification process error:', error);
            return {
                success: false,
                error: error.message || 'An error occurred during verification'
            };
        }
    }

    /**
     * Check if user needs to complete KYC verification
     * @returns {Promise<boolean>} Whether verification is required
     */
    async isVerificationRequired() {
        try {
            const status = await this.getVerificationStatus();
            return !(status.success && status.verified);
        } catch (error) {
            console.error('Error checking verification requirement:', error);
            // Conservatively return true if we couldn't determine status
            return true;
        }
    }
}

// Create global instance
window.KycManager = KycManager;
window.app = window.app || {};
window.app.kycManager = window.app.kycManager || new KycManager();
