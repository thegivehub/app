<?php
// tests/AuthTest.php

use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase {
    private $auth;
    private $testUser;
    private $db;
    
    protected function setUp(): void {
        $this->auth = new Auth();
        $this->db = new Database();
        
        // Create a test user for authentication tests
        $userData = [
            'email' => 'authtest@example.com',
            'username' => 'authtest',
            'personalInfo' => [
                'firstName' => 'Auth',
                'lastName' => 'Test'
            ],
            'auth' => [
                'passwordHash' => password_hash('password123', PASSWORD_DEFAULT),
                'verified' => true
            ],
            'status' => 'active'
        ];
        
        $this->testUser = createTestUser($userData);
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->db->getCollection('users')->deleteMany([
            'email' => 'authtest@example.com'
        ]);
    }
    
    public function testLogin() {
        $loginData = [
            'username' => 'authtest@example.com', // Using email as username
            'password' => 'password123'
        ];
        
        $result = $this->auth->login($loginData);
        
        $this->assertTrue($result['success'], "Login should succeed with valid credentials");
        $this->assertArrayHasKey('tokens', $result, "Login response should contain tokens");
        $this->assertArrayHasKey('accessToken', $result['tokens'], "Tokens should include access token");
        $this->assertArrayHasKey('refreshToken', $result['tokens'], "Tokens should include refresh token");
        $this->assertArrayHasKey('user', $result, "Login response should contain user data");
    }
    
    public function testLoginWithInvalidPassword() {
        $loginData = [
            'username' => 'authtest@example.com',
            'password' => 'wrongpassword'
        ];
        
        $result = $this->auth->login($loginData);
        
        $this->assertFalse($result['success'], "Login should fail with invalid password");
        $this->assertArrayHasKey('error', $result, "Error message should be provided");
    }
    
    public function testLoginWithNonexistentUser() {
        $loginData = [
            'username' => 'nonexistent@example.com',
            'password' => 'password123'
        ];
        
        $result = $this->auth->login($loginData);
        
        $this->assertFalse($result['success'], "Login should fail with nonexistent user");
        $this->assertArrayHasKey('error', $result, "Error message should be provided");
    }
    
    public function testTokenGeneration() {
        $userId = $this->testUser['_id'];
        
        // Generate tokens using the auth class method
        $tokens = $this->invokeMethod($this->auth, 'generateTokens', [$userId]);
        
        $this->assertArrayHasKey('accessToken', $tokens, "Tokens should include access token");
        $this->assertArrayHasKey('refreshToken', $tokens, "Tokens should include refresh token");
        $this->assertArrayHasKey('expires', $tokens, "Tokens should include expiration timestamp");
        
        // Decode and verify token
        $decodedToken = $this->auth->decodeToken($tokens['accessToken']);
        $this->assertEquals($userId, $decodedToken->sub, "Token should contain correct user ID");
    }
    
    public function testTokenDecoding() {
        $userId = $this->testUser['_id'];
        $token = generateTestToken($userId);
        
        $decoded = $this->auth->decodeToken($token);
        
        $this->assertNotNull($decoded, "Token should be decoded successfully");
        $this->assertEquals($userId, $decoded->sub, "Decoded token should have correct user ID");
    }
    
    public function testTokenDecodingWithInvalidToken() {
        $token = "invalid.token.string";
        
        $decoded = $this->auth->decodeToken($token);
        
        $this->assertNull($decoded, "Invalid token should return null when decoded");
    }
    
    public function testRegister() {
        $registerData = [
            'email' => 'newuser@example.com',
            'username' => 'newuser',
            'password' => 'password123',
            'firstName' => 'New',
            'lastName' => 'User',
            'personalInfo' => [
                'language' => 'en'
            ]
        ];
        
        $result = $this->auth->register($registerData);
        
        $this->assertTrue($result['success'], "Registration should succeed");
        $this->assertArrayHasKey('userId', $result, "Registration response should include user ID");
        $this->assertArrayHasKey('message', $result, "Registration response should include a message");
        
        // Clean up the test user
        $this->db->getCollection('users')->deleteMany([
            'email' => 'newuser@example.com'
        ]);
    }
    
    public function testRegisterWithExistingEmail() {
        $registerData = [
            'email' => 'authtest@example.com', // Already exists
            'username' => 'newusername',
            'password' => 'password123',
            'firstName' => 'New',
            'lastName' => 'User',
            'personalInfo' => [
                'language' => 'en'
            ]
        ];
        
        $result = $this->auth->register($registerData);
        
        $this->assertFalse($result['success'], "Registration should fail with existing email");
        $this->assertArrayHasKey('error', $result, "Error message should be provided");
    }
    
    /**
     * Helper method to invoke private/protected methods
     */
    private function invokeMethod($object, $methodName, array $parameters = []) {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($object, $parameters);
    }
}
