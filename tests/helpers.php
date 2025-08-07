<?php
// tests/helpers.php

/**
 * Create a test user in the database
 * 
 * @param array $userData Optional custom user data
 * @return array User data with ID
 */
function createTestUser($userData = []) {
    $db = new Database();
    $usersCollection = $db->getCollection('users');
    
    $defaultUserData = [
        'email' => 'test' . uniqid() . '@example.com',
        'username' => 'testuser' . uniqid(),
        'displayName' => 'Test User',
        'personalInfo' => [
            'firstName' => 'Test',
            'lastName' => 'User',
            'phone' => '123-456-7890',
            'location' => 'Test City'
        ],
        'auth' => [
            'passwordHash' => password_hash('password123', PASSWORD_DEFAULT),
            'verified' => true
        ],
        'profile' => [
            'bio' => 'Test bio',
            'avatar' => null
        ],
        'roles' => ['user'],
        'status' => 'active',
        'created' => new MongoDB\BSON\UTCDateTime(),
        'updated' => new MongoDB\BSON\UTCDateTime()
    ];
    
    // Merge with provided user data
    $userData = array_merge($defaultUserData, $userData);
    
    $result = $usersCollection->insertOne($userData);
    
    // Return user data with ID
    $userData['_id'] = $result['id'];
    return $userData;
}

/**
 * Create a test campaign in the database
 * 
 * @param array $campaignData Optional custom campaign data
 * @return array Campaign data with ID
 */
function createTestCampaign($campaignData = []) {
    $db = new Database();
    $campaignsCollection = $db->getCollection('campaigns');
    
    // Create user if creator not provided
    if (!isset($campaignData['creator'])) {
        $user = createTestUser();
        $campaignData['creator'] = $user['_id'];
    }
    
    $defaultCampaignData = [
        'title' => 'Test Campaign ' . uniqid(),
        'description' => 'Test campaign description',
        'category' => 'education',
        'status' => 'active',
        'stellarAddress' => 'GABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        'funding' => [
            'targetAmount' => 1000,
            'raisedAmount' => 0,
            'donorCount' => 0
        ],
        'location' => [
            'country' => 'Test Country',
            'region' => 'Test Region'
        ],
        'created' => new MongoDB\BSON\UTCDateTime(),
        'updated' => new MongoDB\BSON\UTCDateTime()
    ];
    
    // Merge with provided campaign data
    $campaignData = array_merge($defaultCampaignData, $campaignData);
    
    $result = $campaignsCollection->insertOne($campaignData);
    
    // Return campaign data with ID
    $campaignData['_id'] = $result['id'];
    return $campaignData;
}

/**
 * Create a test donation in the database
 * 
 * @param array $donationData Optional custom donation data
 * @return array Donation data with ID
 */
function createTestDonation($donationData = []) {
    $db = new Database();
    $donationsCollection = $db->getCollection('donations');
    
    // Create user and campaign if not provided
    if (!isset($donationData['userId'])) {
        $user = createTestUser();
        $donationData['userId'] = $user['_id'];
    }
    
    if (!isset($donationData['campaignId'])) {
        $campaign = createTestCampaign();
        $donationData['campaignId'] = $campaign['_id'];
    }
    
    $defaultDonationData = [
        'amount' => [
            'value' => 50,
            'currency' => 'XLM'
        ],
        'transaction' => [
            'txHash' => 'tx' . uniqid(),
            'stellarAddress' => 'GABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            'status' => 'completed',
            'timestamp' => new MongoDB\BSON\UTCDateTime()
        ],
        'type' => 'one-time',
        'status' => 'completed',
        'visibility' => 'public',
        'created' => new MongoDB\BSON\UTCDateTime(),
        'updated' => new MongoDB\BSON\UTCDateTime()
    ];
    
    // Merge with provided donation data
    $donationData = array_merge($defaultDonationData, $donationData);
    
    $result = $donationsCollection->insertOne($donationData);
    
    // Return donation data with ID
    $donationData['_id'] = $result['id'];
    return $donationData;
}

/**
 * Generate a JWT token for testing
 * 
 * @param string $userId User ID to include in token
 * @return string JWT token
 */
function generateTestToken($userId) {
    $issuedAt = time();
    $expire = $issuedAt + 3600; // 1 hour
    
    $payload = [
        'iat' => $issuedAt,
        'exp' => $expire,
        'sub' => (string)$userId
    ];
    
    $jwtSecret = getenv('JWT_SECRET') ?: 'test_secret_key';
    return \Firebase\JWT\JWT::encode($payload, $jwtSecret, 'HS256');
}

/**
 * Mock Stellar API responses for testing
 */
class StellarMock {
    public static function mockSubmitTransaction() {
        // Code to mock Stellar transaction submission
        // This would return a predefined successful response
        return [
            'success' => true,
            'result' => [
                'hash' => 'mock_tx_hash_' . uniqid(),
                'ledger' => 12345,
                'result_xdr' => 'AAAAAAAAAGQAAAAAAAAAAQAAAAAAAAABAAAAAAAAAAA='
            ]
        ];
    }
}

/**
 * Reset all mock behaviors
 */
function resetMocks() {
    // Reset any static mocks
}
