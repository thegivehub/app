<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Campaign;
use MongoDB\BSON\ObjectId;

class CampaignTest extends TestCase {
    private $campaign;
    private $testData;
    private $testUserId;

    protected function setUp(): void {
        parent::setUp();
        $this->campaign = new Campaign();
        $this->testUserId = (string) new ObjectId();
        
        // Test campaign data
        $this->testData = [
            'title' => 'Test Campaign',
            'description' => 'Test Campaign Description',
            'targetAmount' => 1000.00,
            'category' => 'education',
            'status' => 'pending',
            'creatorId' => $this->testUserId,
            'location' => [
                'country' => 'US',
                'region' => 'California',
                'coordinates' => [
                    'latitude' => 37.7749,
                    'longitude' => -122.4194
                ]
            ]
        ];
    }

    protected function tearDown(): void {
        // Clean up test data
        if (isset($this->testData['_id'])) {
            $this->campaign->delete($this->testData['_id']);
        }
        parent::tearDown();
    }

    public function testCreate() {
        $result = $this->campaign->create($this->testData);
        
        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['id']);
        $this->assertArrayHasKey('campaign', $result);
        $this->assertEquals($this->testData['title'], $result['campaign']['title']);
        $this->assertEquals($this->testData['creatorId'], $result['campaign']['creatorId']);
    }

    public function testCreateWithImage() {
        // Test data with base64 image
        $imageData = 'data:image/jpeg;base64,' . base64_encode('test image data');
        $this->testData['image'] = $imageData;
        
        $result = $this->campaign->create($this->testData);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('imageUrl', $result['campaign']);
        $this->assertNotEmpty($result['campaign']['imageUrl']);
    }

    public function testRead() {
        // Create test campaign
        $created = $this->campaign->create($this->testData);
        $this->assertTrue($created['success']);
        
        // Test reading single campaign
        $campaign = $this->campaign->read($created['id']);
        $this->assertNotNull($campaign);
        $this->assertEquals($this->testData['title'], $campaign['title']);
        
        // Test reading all campaigns
        $campaigns = $this->campaign->read(null, ['limit' => 10]);
        $this->assertIsArray($campaigns);
        $this->assertGreaterThan(0, count($campaigns));
    }

    public function testUpdate() {
        // Create test campaign
        $created = $this->campaign->create($this->testData);
        $this->assertTrue($created['success']);
        
        // Update data
        $updateData = [
            'title' => 'Updated Campaign Title',
            'targetAmount' => 2000.00
        ];
        
        $result = $this->campaign->update($created['id'], $updateData);
        $this->assertTrue($result['success']);
        
        // Verify update
        $updated = $this->campaign->read($created['id']);
        $this->assertEquals('Updated Campaign Title', $updated['title']);
        $this->assertEquals(2000.00, $updated['targetAmount']);
    }

    public function testDelete() {
        // Create test campaign
        $created = $this->campaign->create($this->testData);
        $this->assertTrue($created['success']);
        
        // Delete campaign
        $result = $this->campaign->delete($created['id']);
        $this->assertTrue($result['success']);
        
        // Verify deletion
        $deleted = $this->campaign->read($created['id']);
        $this->assertNull($deleted);
    }

    public function testGetMyCampaigns() {
        // Create test campaign
        $this->testData['creatorId'] = $this->testUserId;
        $created = $this->campaign->create($this->testData);
        $this->assertTrue($created['success']);
        
        // Mock JWT token
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->generateTestToken($this->testUserId);
        
        // Get user's campaigns
        $campaigns = $this->campaign->getMyCampaigns();
        $this->assertIsArray($campaigns);
        $this->assertGreaterThan(0, count($campaigns));
        $this->assertEquals($this->testUserId, $campaigns[0]['creatorId']);
    }

    public function testUploadCampaignImage() {
        // Create test campaign
        $created = $this->campaign->create($this->testData);
        $this->assertTrue($created['success']);
        
        // Mock file upload
        $testFile = [
            'tmp_name' => tempnam(sys_get_temp_dir(), 'test'),
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'size' => 1024,
            'error' => 0
        ];
        
        // Write test image data
        file_put_contents($testFile['tmp_name'], 'test image data');
        
        $result = $this->campaign->uploadCampaignImage($testFile, $created['id']);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('url', $result);
        $this->assertNotEmpty($result['url']);
        
        // Clean up test file
        unlink($testFile['tmp_name']);
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