<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use User;
use MongoDB\BSON\ObjectId;

class UserTest extends TestCase {
    private $user;
    private $testData;
    private $testUserId;

    protected function setUp(): void {
        parent::setUp();
        $this->user = new User();
        $this->testUserId = (string) new ObjectId();
        
        // Test user data
        $this->testData = [
            'email' => 'test@example.com',
            'username' => 'testuser',
            'password' => 'TestPassword123!',
            'firstName' => 'Test',
            'lastName' => 'User',
            'type' => 'donor',
            'personalInfo' => [
                'language' => 'en',
                'address' => [
                    'street' => '123 Test St',
                    'city' => 'Test City',
                    'state' => 'CA',
                    'country' => 'US',
                    'postalCode' => '12345'
                ]
            ],
            'profile' => [
                'bio' => 'Test bio',
                'preferences' => [
                    'emailNotifications' => true,
                    'currency' => 'USD'
                ]
            ]
        ];
    }

    protected function tearDown(): void {
        // Clean up test data
        if (isset($this->testData['_id'])) {
            $this->user->delete($this->testData['_id']);
        }
        parent::tearDown();
    }

    public function testRegister() {
        $result = $this->user->register($this->testData);
        
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['id']);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($this->testData['email'], $result['user']['email']);
        $this->assertEquals($this->testData['username'], $result['user']['username']);
        $this->assertArrayNotHasKey('password', $result['user']); // Password should not be returned
    }

    public function testUpdateProfile() {
        // Register test user first
        $registered = $this->user->register($this->testData);
        $this->assertTrue($registered['success']);
        
        // Update profile data
        $updateData = [
            'displayName' => 'Updated Name',
            'personalInfo' => [
                'firstName' => 'Updated',
                'lastName' => 'User',
                'language' => 'es'
            ],
            'profile' => [
                'bio' => 'Updated bio',
                'preferences' => [
                    'emailNotifications' => false
                ]
            ],
            'email' => 'updated@example.com'
        ];
        
        $result = $this->user->updateProfile($registered['id'], $updateData);
        
        $this->assertNotNull($result);
        $this->assertEquals('Updated Name', $result['displayName']);
        $this->assertEquals('updated@example.com', $result['email']);
    }

    public function testUploadProfileImage() {
        // Register test user
        $registered = $this->user->register($this->testData);
        $this->assertTrue($registered['success']);
        
        // Mock file upload
        $testFile = [
            'tmp_name' => tempnam(sys_get_temp_dir(), 'test'),
            'name' => 'profile.jpg',
            'type' => 'image/jpeg',
            'size' => 1024,
            'error' => 0
        ];
        
        // Write test image data
        file_put_contents($testFile['tmp_name'], 'test image data');
        
        // Mock JWT token for authentication
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->generateTestToken($registered['id']);
        
        $result = $this->user->uploadProfileImage($testFile);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('url', $result);
        $this->assertNotEmpty($result['url']);
        
        // Clean up test file
        unlink($testFile['tmp_name']);
    }

    public function testMe() {
        // Register test user
        $registered = $this->user->register($this->testData);
        $this->assertTrue($registered['success']);
        
        // Mock JWT token
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->generateTestToken($registered['id']);
        
        $result = $this->user->me();
        
        $this->assertNotNull($result);
        $this->assertEquals($this->testData['email'], $result['email']);
        $this->assertEquals($this->testData['username'], $result['username']);
    }

    public function testFindActive() {
        // Register test user
        $this->testData['status'] = 'active';
        $registered = $this->user->register($this->testData);
        $this->assertTrue($registered['success']);
        
        $activeUsers = $this->user->findActive();
        
        $this->assertIsArray($activeUsers);
        $this->assertGreaterThan(0, count($activeUsers));
        $this->assertEquals('active', $activeUsers[0]['status']);
    }

    public function testFindByEmail() {
        // Register test user
        $registered = $this->user->register($this->testData);
        $this->assertTrue($registered['success']);
        
        $users = $this->user->findByEmail($this->testData['email']);
        
        $this->assertIsArray($users);
        $this->assertGreaterThan(0, count($users));
        $this->assertEquals($this->testData['email'], $users[0]['email']);
    }

    public function testGetPostCounts() {
        // Register test user
        $registered = $this->user->register($this->testData);
        $this->assertTrue($registered['success']);
        
        $results = $this->user->getPostCounts();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('name', $results[0]);
        $this->assertArrayHasKey('postCount', $results[0]);
    }

    public function testGetProfile() {
        // Register test user
        $registered = $this->user->register($this->testData);
        $this->assertTrue($registered['success']);
        
        // Mock JWT token
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->generateTestToken($registered['id']);
        
        $profile = $this->user->getProfile();
        
        $this->assertIsArray($profile);
        $this->assertEquals($this->testData['email'], $profile['email']);
        $this->assertEquals($this->testData['username'], $profile['username']);
    }

    private function generateTestToken($userId) {
        $payload = [
            'sub' => $userId,
            'exp' => time() + 3600,
            'iat' => time()
        ];
        return \Firebase\JWT\JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
    }
} 