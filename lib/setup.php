<?php
require_once 'db.php';

class DatabaseSetup {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function setupCollections() {
        $this->setupUsers();
        $this->setupCampaigns();
        $this->setupDonations();
        $this->setupNotifications();
    }

    private function setupUsers() {
        $users = $this->db->getCollection('users');
        
        // Create indexes
        $users->createIndexes([
            [
                'key' => ['email' => 1],
                'unique' => true
            ],
            [
                'key' => ['username' => 1],
                'unique' => true
            ],
            [
                'key' => ['status' => 1],
                'sparse' => true
            ],
            [
                'key' => ['auth.verificationCode' => 1],
                'sparse' => true,
                'expireAfterSeconds' => 3600 // 1 hour
            ]
        ]);
    }

    private function setupCampaigns() {
        $campaigns = $this->db->getCollection('campaigns');
        
        $campaigns->createIndexes([
            [
                'key' => ['creator_id' => 1]
            ],
            [
                'key' => ['status' => 1]
            ],
            [
                'key' => ['created' => -1]
            ],
            [
                'key' => ['category' => 1]
            ]
        ]);
    }

    private function setupDonations() {
        $donations = $this->db->getCollection('donations');
        
        $donations->createIndexes([
            [
                'key' => ['campaign_id' => 1]
            ],
            [
                'key' => ['user_id' => 1]
            ],
            [
                'key' => ['transaction.txHash' => 1],
                'unique' => true
            ],
            [
                'key' => ['created' => -1]
            ]
        ]);
    }

    private function setupNotifications() {
        $notifications = $this->db->getCollection('notifications');
        
        $notifications->createIndexes([
            [
                'key' => ['user_id' => 1]
            ],
            [
                'key' => ['status' => 1]
            ],
            [
                'key' => ['created' => -1]
            ],
            [
                'key' => ['read' => 1],
                'expireAfterSeconds' => 2592000 // 30 days
            ]
        ]);
    }
}

// Run setup
if (php_sapi_name() === 'cli') {
    $setup = new DatabaseSetup();
    $setup->setupCollections();
    echo "Database setup complete.\n";
}
