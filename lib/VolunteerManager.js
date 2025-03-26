// VolunteerManager.js
(function() {
    class VolunteerManager {
        constructor() {
            // Add volunteer endpoints to api config
            app.api.ENDPOINTS.VOLUNTEER = {
                PROFILE: '/volunteer/me',
                OPPORTUNITIES: '/volunteer_opportunities',
                APPLICATIONS: '/volunteer/applications',
                SCHEDULE: '/volunteer/schedule',
                HOURS: '/volunteer/hours',
                STATS: '/volunteer/stats',
                CERTIFICATIONS: '/volunteer/certifications'
            };
        }

        // Get volunteer profile and statistics
        async getProfile() {
            try {
                const [profile, stats] = await Promise.all([
                    app.api.fetchWithAuth(app.api.ENDPOINTS.VOLUNTEER.PROFILE),
                    app.api.fetchWithAuth(app.api.ENDPOINTS.VOLUNTEER.STATS)
                ]);

                return {
                    ...profile,
                    stats
                };
            } catch (error) {
                console.error('Error fetching volunteer profile:', error);
                throw new Error('Failed to fetch volunteer profile');
            }
        }

        // Get available opportunities
        async getOpportunities(filters = {}) {
            try {
                const response = await app.api.fetchWithAuth(
                    app.api.ENDPOINTS.VOLUNTEER.OPPORTUNITIES,
                    { params: filters }
                );
                return response;
            } catch (error) {
                console.error('Error fetching opportunities:', error);
                throw new Error('Failed to fetch opportunities');
            }
        }

        // Apply for an opportunity
        async applyForOpportunity(opportunityId, availability = []) {
            try {
                const response = await app.api.fetchWithAuth(
                    app.api.ENDPOINTS.VOLUNTEER.APPLICATIONS,
                    {
                        method: 'POST',
                        body: JSON.stringify({
                            opportunityId,
                            availability
                        })
                    }
                );
                return response;
            } catch (error) {
                console.error('Error submitting application:', error);
                throw new Error('Failed to submit application');
            }
        }

        // Cancel an application
        async cancelApplication(applicationId, reason = '') {
            try {
                const response = await app.api.fetchWithAuth(
                    `${app.api.ENDPOINTS.VOLUNTEER.APPLICATIONS}/cancel`,
                    {
                        method: 'PUT',
                        body: JSON.stringify({
                            applicationId,
                            reason
                        })
                    }
                );
                return response;
            } catch (error) {
                console.error('Error canceling application:', error);
                throw new Error('Failed to cancel application');
            }
        }

        // Get volunteer's applications
        async getApplications(status = null) {
            try {
                const params = status ? { status } : {};
                const response = await app.api.fetchWithAuth(
                    app.api.ENDPOINTS.VOLUNTEER.APPLICATIONS,
                    { params }
                );
                return response;
            } catch (error) {
                console.error('Error fetching applications:', error);
                throw new Error('Failed to fetch applications');
            }
        }

        // Update volunteer schedule
        async updateSchedule(scheduleData) {
            try {
                const response = await app.api.fetchWithAuth(
                    app.api.ENDPOINTS.VOLUNTEER.SCHEDULE,
                    {
                        method: 'PUT',
                        body: JSON.stringify(scheduleData)
                    }
                );
                return response;
            } catch (error) {
                console.error('Error updating schedule:', error);
                throw new Error('Failed to update schedule');
            }
        }

        // Log volunteer hours
        async logHours(opportunityId, hours, description = '') {
            try {
                const response = await app.api.fetchWithAuth(
                    app.api.ENDPOINTS.VOLUNTEER.HOURS,
                    {
                        method: 'POST',
                        body: JSON.stringify({
                            opportunityId,
                            hours,
                            description,
                            date: new Date().toISOString()
                        })
                    }
                );
                return response;
            } catch (error) {
                console.error('Error logging hours:', error);
                throw new Error('Failed to log hours');
            }
        }

        // Get volunteer hours
        async getHours(timeframe = 'all') {
            try {
                const response = await app.api.fetchWithAuth(
                    app.api.ENDPOINTS.VOLUNTEER.HOURS,
                    { params: { timeframe } }
                );
                return response;
            } catch (error) {
                console.error('Error fetching hours:', error);
                throw new Error('Failed to fetch hours');
            }
        }

        // Upload certification
        async uploadCertification(certificationData, file) {
            try {
                const formData = new FormData();
                formData.append('file', file);
                formData.append('data', JSON.stringify(certificationData));

                const response = await app.api.fetchWithAuth(
                    app.api.ENDPOINTS.VOLUNTEER.CERTIFICATIONS,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': undefined // Let the browser set proper content type for FormData
                        },
                        body: formData
                    }
                );
                return response;
            } catch (error) {
                console.error('Error uploading certification:', error);
                throw new Error('Failed to upload certification');
            }
        }

        // Update volunteer profile
        async updateProfile(profileData) {
            try {
                const response = await app.api.fetchWithAuth(
                    app.api.ENDPOINTS.VOLUNTEER.PROFILE,
                    {
                        method: 'PUT',
                        body: JSON.stringify(profileData)
                    }
                );
                return response;
            } catch (error) {
                console.error('Error updating profile:', error);
                throw new Error('Failed to update profile');
            }
        }
    }

    // Initialize volunteer manager
    app.volunteerManager = new VolunteerManager();
})();
