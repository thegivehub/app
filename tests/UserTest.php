<?php
// tests/UserTest.php

use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;

class UserTest extends TestCase {
    private $user;
    private $db;
    private $testUserId;
    private $testToken;
    
    protected function setUp(): void {
        $this->user = new User();
        $this->db = new Database();
        
        // Create a test user
        $testUser = createTestUser([
            'email' => 'usertest@example.com',
            'username' => 'usertest',
            'displayName' => 'User Test'
        ]);
        
        $this->testUserId = $testUser['_id'];
        
        // Generate a test token
        $this->testToken = generateTestToken($this->testUserId);
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->db->getCollection('users')->deleteMany([
            'email' => 'usertest@example.com'
        ]);
    }
    
    public function testUpdateProfile() {
        // Mock the request with token in header
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->testToken;
        
        // Set up profile data to update
        $profileData = [
            'displayName' => 'Updated User Name',
            'email' => 'usertest@example.com', // keep same email
            'personalInfo' => [
                'firstName' => 'Updated',
                'lastName' => 'User',
                'phone' => '987-654-3210',
                'location' => 'Updated City'
            ],
            'profile' => [
                'bio' => 'Updated bio for testing'
            ]
        ];
        
        // Call updateProfile method
        $result = $this->user->updateProfile($this->testUserId, $profileData);
        
        // Verify the result
        $this->assertNotNull($result, "Update should return user data");
        $this->assertEquals($profileData['displayName'], $result['displayName'], "Display name should be updated");
        $this->assertEquals($profileData['personalInfo']['firstName'], $result['personalInfo']['firstName'], "First name should be updated");
        $this->assertEquals($profileData['personalInfo']['lastName'], $result['personalInfo']['lastName'], "Last name should be updated");
        $this->assertEquals($profileData['profile']['bio'], $result['profile']['bio'], "Bio should be updated");
        
        // Verify in database
        $updatedUser = $this->db->getCollection('users')->findOne([
            '_id' => new MongoDB\BSON\ObjectId($this->testUserId)
        ]);
        
        $this->assertNotNull($updatedUser, "User should exist in database");
        $this->assertEquals($profileData['displayName'], $updatedUser['displayName'], "Display name should be updated in database");
    }
    
    public function testGetUserIdFromToken() {
        // Mock the request with token in header
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->testToken;
        
        // Call the method
        $result = $this->user->getUserIdFromToken();
        
        // Verify the result
        $this->assertEquals($this->testUserId, $result, "Should extract correct user ID from token");
    }
    
    public function testGetUserIdFromInvalidToken() {
        // Mock the request with invalid token in header
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid.token.string';
        
        // Call the method
        $result = $this->user->getUserIdFromToken();
        
        // Verify the result
        $this->assertNull($result, "Should return null for invalid token");
    }
    
    public function testMe() {
        // Mock the request with token in header
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->testToken;
        
        // Call the me() method
        $result = $this->user->me();
        
        // Verify the result
        $this->assertNotNull($result, "Should return user data");
        $this->assertEquals($this->testUserId, $result['_id'], "Should return correct user ID");
        $this->assertEquals('usertest@example.com', $result['email'], "Should return correct email");
        $this->assertEquals('usertest', $result['username'], "Should return correct username");
    }
    
    public function testUpdateAddress() {
        // Test address update (requires AddressValidator to be functional)
        $addressData = [
            'street' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TS',
            'zip' => '12345',
            'country' => 'US'
        ];
        
        $result = $this->user->updateAddress($this->testUserId, $addressData);
        
        $this->assertTrue($result['success'], "Address update should succeed");
        $this->assertArrayHasKey('address', $result, "Result should include normalized address");
        
        // Verify in database
        $updatedUser = $this->db->getCollection('users')->findOne([
            '_id' => new MongoDB\BSON\ObjectId($this->testUserId)
        ]);
        
        $this->assertNotNull($updatedUser, "User should exist in database");
        $this->assertArrayHasKey('personalInfo', $updatedUser, "User should have personalInfo");
        $this->assertArrayHasKey('address', $updatedUser['personalInfo'], "User should have address in personalInfo");
        $this->assertEquals($result['address']['street'], $updatedUser['personalInfo']['address']['street'], "Street should match");
    }
    
    public function testUpdateAddressWithInvalidData() {
        // Test with invalid address (missing required fields)
        $invalidAddress = [
            'street' => '123 Test St',
            // Missing city
            'country' => 'US'
        ];
        
        $result = $this->user->updateAddress($this->testUserId, $invalidAddress);
        
        $this->assertFalse($result['success'], "Address update should fail with invalid data");
        $this->assertArrayHasKey('errors', $result, "Result should include validation errors");
    }
}
