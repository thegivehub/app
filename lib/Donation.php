<?php
// lib/Donation.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Auth.php';

class Donation {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
    }
    
    /**
     * Get donations for a specific campaign
     * 
     * @param string $campaignId The ID of the campaign
     * @return array Donation data including total amount and donor count
     */
    public function getCampaignDonations($campaignId = null) {
        if (!$campaignId) {
            // Try to get campaignId from the query parameters
            $campaignId = $_GET['campaignId'] ?? null;
            
            if (!$campaignId) {
                return [
                    'success' => false,
                    'error' => 'Campaign ID is required'
                ];
            }
        }
        
        try {
            // Convert to MongoDB ObjectId
            $campaignObjId = new MongoDB\BSON\ObjectId($campaignId);
            
            // Get all completed donations for this campaign
            $donationsCollection = $this->db->getCollection('donations');
            $donations = $donationsCollection->find([
                'campaignId' => $campaignObjId,
                'status' => 'completed'
            ]);
            
            // Calculate totals
            $totalAmount = 0;
            $donorCount = 0;
            $uniqueDonors = [];
            $recentDonations = [];
            
            foreach ($donations as $donation) {
                if (isset($donation['amount']['value'])) {
                    $totalAmount += (float)$donation['amount']['value'];
                }
                
                // Count unique donors
                $donorId = isset($donation['userId']) ? (string)$donation['userId'] : 
                          (isset($donation['donor']['email']) ? $donation['donor']['email'] : null);
                          
                if ($donorId && !in_array($donorId, $uniqueDonors)) {
                    $uniqueDonors[] = $donorId;
                    $donorCount++;
                }
                
                // Collect the 5 most recent donations for display
                if (count($recentDonations) < 5) {
                    $recentDonations[] = [
                        'amount' => $donation['amount']['value'],
                        'currency' => $donation['amount']['currency'],
                        'donor' => isset($donation['donor']['name']) ? $donation['donor']['name'] : 'Anonymous',
                        'date' => isset($donation['created']) ? $donation['created']->toDateTime()->format('Y-m-d H:i:s') : null,
                        'visibility' => $donation['visibility'] ?? 'public'
                    ];
                }
            }
            
            // Now also check for pending donations
            $pendingDonations = $donationsCollection->find([
                'campaignId' => $campaignObjId,
                'status' => 'pending'
            ]);
            
            $pendingTotal = 0;
            foreach ($pendingDonations as $donation) {
                if (isset($donation['amount']['value'])) {
                    $pendingTotal += (float)$donation['amount']['value'];
                }
            }
            
            return [
                'success' => true,
                'data' => [
                    'totalAmount' => $totalAmount,
                    'pendingAmount' => $pendingTotal,
                    'donorCount' => $donorCount,
                    'recentDonations' => $recentDonations
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Error fetching campaign donations: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve donation data: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get all donations (with optional filtering)
     * 
     * @param array $filter Filter criteria
     * @param array $options Query options (limit, skip, sort)
     * @return array List of donations
     */
    public function getAllDonations($filter = [], $options = []) {
        try {
            // Verify admin permissions if necessary
            // This could be enhanced with proper role-based permissions
            
            $donationsCollection = $this->db->getCollection('donations');
            
            // Apply default options if not provided
            if (!isset($options['limit'])) {
                $options['limit'] = 50;
            }
            
            if (!isset($options['skip'])) {
                $options['skip'] = 0;
            }
            
            if (!isset($options['sort'])) {
                $options['sort'] = ['created' => -1]; // Sort by creation date, newest first
            }
            
            // Get the total count (without pagination)
            $totalCount = $donationsCollection->count($filter);
            
            // Get the paginated results
            $donations = $donationsCollection->find($filter, $options);
            
            return [
                'success' => true,
                'data' => [
                    'donations' => $donations,
                    'total' => $totalCount,
                    'limit' => $options['limit'],
                    'skip' => $options['skip']
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Error fetching donations: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve donations: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get a single donation by ID
     * 
     * @param string $donationId The ID of the donation
     * @return array The donation data
     */
    public function getDonation($donationId = null) {
        if (!$donationId) {
            // Try to get donationId from the query parameters
            $donationId = $_GET['id'] ?? null;
            
            if (!$donationId) {
                return [
                    'success' => false,
                    'error' => 'Donation ID is required'
                ];
            }
        }
        
        try {
            $donationsCollection = $this->db->getCollection('donations');
            $donation = $donationsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($donationId)]);
            
            if (!$donation) {
                return [
                    'success' => false,
                    'error' => 'Donation not found'
                ];
            }
            
            return [
                'success' => true,
                'data' => $donation
            ];
            
        } catch (Exception $e) {
            error_log('Error fetching donation: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve donation: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get donations by user ID
     * 
     * @param string $userId The ID of the user
     * @return array The user's donations
     */
    public function getUserDonations($userId = null) {
        if (!$userId) {
            // Try to get from auth token
            $userId = $this->auth->getAuthenticatedUserId();
            
            if (!$userId) {
                return [
                    'success' => false,
                    'error' => 'User ID is required or user is not authenticated'
                ];
            }
        }
        
        try {
            $donationsCollection = $this->db->getCollection('donations');
            $donations = $donationsCollection->find(['userId' => new MongoDB\BSON\ObjectId($userId)]);
            
            return [
                'success' => true,
                'data' => $donations
            ];
            
        } catch (Exception $e) {
            error_log('Error fetching user donations: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve user donations: ' . $e->getMessage()
            ];
        }
    }
}