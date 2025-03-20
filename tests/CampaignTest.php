<?php
// tests/CampaignTest.php

use PHPUnit\Framework\TestCase;

class CampaignTest extends TestCase {
    private $campaign;
    private $db;
    private $testUser;
    
    protected function setUp(): void {
        $this->campaign = new Campaign();
        $this->db = new Database();
        
        // Create a test user
        $this->testUser = createTestUser([
            'email' => 'campaigncreator@example.com',
            'username' => 'campaigncreator'
        ]);
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->db->getCollection('users')->deleteMany([
            'email' => 'campaigncreator@example.com'
        ]);
        
        $this->db->getCollection('campaigns')->deleteMany([
            'title' => ['$regex' => '^Test Campaign']
        ]);
    }
    
    public function testCreate() {
        $campaignData = [
            'title' => 'Test Campaign Create',
            'description' => 'This is a test campaign for unit testing',
            'category' => 'education',
            'creator' => $this->testUser['_id'],
            'funding' => [
                'targetAmount' => 1000,
                'raisedAmount' => 0,
                'donorCount' => 0
            ],
            'location' => [
                'country' => 'Test Country',
                'region' => 'Test Region'
            ],
            'stellarAddress' => 'GABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
        ];
        
        $result = $this->campaign->create($campaignData);
        
        $this->assertTrue($result['success'], "Campaign creation should succeed");
        $this->assertNotEmpty($result['id'], "Result should contain campaign ID");
        
        // Verify the campaign was created in the database
        $createdCampaign = $this->db->getCollection('campaigns')->findOne([
            '_id' => new MongoDB\BSON\ObjectId($result['id'])
        ]);
        
        $this->assertNotNull($createdCampaign, "Campaign should exist in database");
        $this->assertEquals($campaignData['title'], $createdCampaign['title'], "Campaign title should match");
        $this->assertEquals($campaignData['description'], $createdCampaign['description'], "Campaign description should match");
        $this->assertEquals($campaignData['category'], $createdCampaign['category'], "Campaign category should match");
        $this->assertEquals($campaignData['stellarAddress'], $createdCampaign['stellarAddress'], "Campaign Stellar address should match");
    }
    
    public function testRead() {
        // Create a test campaign
        $testCampaign = createTestCampaign([
            'title' => 'Test Campaign Read',
            'creator' => $this->testUser['_id']
        ]);
        
        // Test reading single campaign
        $campaign = $this->campaign->read($testCampaign['_id']);
        
        $this->assertNotNull($campaign, "Should retrieve the campaign");
        $this->assertEquals($testCampaign['_id'], $campaign['_id'], "Retrieved campaign should have correct ID");
        $this->assertEquals($testCampaign['title'], $campaign['title'], "Retrieved campaign should have correct title");
        
        // Test reading all campaigns
        $campaigns = $this->campaign->read();
        
        $this->assertIsArray($campaigns, "Should retrieve an array of campaigns");
        $this->assertGreaterThan(0, count($campaigns), "Should retrieve at least one campaign");
        
        // Find our test campaign in the results
        $found = false;
        foreach ($campaigns as $c) {
            if ($c['_id'] == $testCampaign['_id']) {
                $found = true;
                break;
            }
        }
        
        $this->assertTrue($found, "Test campaign should be in the list of all campaigns");
    }
    
    public function testUpdate() {
        // Create a test campaign
        $testCampaign = createTestCampaign([
            'title' => 'Test Campaign Update - Original',
            'creator' => $this->testUser['_id']
        ]);
        
        // Prepare update data
        $updateData = [
            'title' => 'Test Campaign Update - Modified',
            'description' => 'Updated description for testing',
            'status' => 'active'
        ];
        
        $result = $this->campaign->update($testCampaign['_id'], $updateData);
        
        $this->assertTrue($result['success'], "Campaign update should succeed");
        
        // Verify the campaign was updated in the database
        $updatedCampaign = $this->db->getCollection('campaigns')->findOne([
            '_id' => new MongoDB\BSON\ObjectId($testCampaign['_id'])
        ]);
        
        $this->assertNotNull($updatedCampaign, "Campaign should exist in database");
        $this->assertEquals($updateData['title'], $updatedCampaign['title'], "Campaign title should be updated");
        $this->assertEquals($updateData['description'], $updatedCampaign['description'], "Campaign description should be updated");
        $this->assertEquals($updateData['status'], $updatedCampaign['status'], "Campaign status should be updated");
    }
    
    public function testDelete() {
        // Create a test campaign
        $testCampaign = createTestCampaign([
            'title' => 'Test Campaign Delete',
            'creator' => $this->testUser['_id']
        ]);
        
        // First verify it exists
        $campaign = $this->db->getCollection('campaigns')->findOne([
            '_id' => new MongoDB\BSON\ObjectId($testCampaign['_id'])
        ]);
        
        $this->assertNotNull($campaign, "Campaign should exist before deletion");
        
        // Delete the campaign
        $result = $this->campaign->delete($testCampaign['_id']);
        
        $this->assertTrue($result['success'], "Campaign deletion should succeed");
        
        // Verify it was deleted
        $deletedCampaign = $this->db->getCollection('campaigns')->findOne([
            '_id' => new MongoDB\BSON\ObjectId($testCampaign['_id'])
        ]);
        
        $this->assertNull($deletedCampaign, "Campaign should not exist after deletion");
    }
    
    public function testGetMyCampaigns() {
        // Create multiple campaigns for the test user
        $campaigns = [];
        for ($i = 0; $i < 3; $i++) {
            $campaigns[] = createTestCampaign([
                'title' => "Test Campaign User {$i}",
                'creator' => $this->testUser['_id']
            ]);
        }
        
        // Create a campaign for another user
        $otherUser = createTestUser([
            'email' => 'othercreator@example.com',
            'username' => 'othercreator'
        ]);
        
        $otherCampaign = createTestCampaign([
            'title' => 'Other User Campaign',
            'creator' => $otherUser['_id']
        ]);
        
        // Mock the session to set the current user
        $_SESSION['user'] = ['id' => $this->testUser['_id']];
        
        // Get user's campaigns
        $userCampaigns = $this->campaign->getMyCampaigns();
        
        // Should return array even if wrapped in success response
        $campaigns = is_array($userCampaigns) ? $userCampaigns : $userCampaigns['campaigns'] ?? [];
        
        $this->assertNotEmpty($campaigns, "Should retrieve user's campaigns");
        $this->assertGreaterThanOrEqual(3, count($campaigns), "Should retrieve all user's campaigns");
        
        // Verify all campaigns belong to the user
        foreach ($campaigns as $campaign) {
            $this->assertEquals($this->testUser['_id'], $campaign['creator'], "Campaign should belong to the user");
        }
        
        // Clean up the other user
        $this->db->getCollection('users')->deleteMany([
            'email' => 'othercreator@example.com'
        ]);
        
        $this->db->getCollection('campaigns')->deleteMany([
            'title' => 'Other User Campaign'
        ]);
    }
}
