<?php
use PHPUnit\Framework\TestCase;

class RiskScoringServiceTest extends TestCase {
    private $db;
    private $service;
    private $user;

    protected function setUp(): void {
        parent::setUp();
        $this->db = new Database();
        $this->service = new RiskScoringService();
        $this->user = createTestUser(['personalInfo' => ['country' => 'US']]);
    }

    protected function tearDown(): void {
        $this->db->getCollection('users')->deleteMany(['_id' => new MongoDB\BSON\ObjectId($this->user['_id'])]);
        $this->db->getCollection('blockchain_transactions')->deleteMany(['userId' => $this->user['_id']]);
        $this->db->getCollection('kyc_verifications')->deleteMany(['userId' => new MongoDB\BSON\ObjectId($this->user['_id'])]);
        parent::tearDown();
    }

    public function testLowRiskScore() {
        $this->db->getCollection('kyc_verifications')->insertOne([
            'userId' => new MongoDB\BSON\ObjectId($this->user['_id']),
            'verificationResult' => 'APPROVED',
            'status' => 'APPROVED',
            'createdAt' => new MongoDB\BSON\UTCDateTime()
        ]);

        $result = $this->service->calculateRiskScore($this->user['_id']);
        $this->assertTrue($result['success']);
        $this->assertEquals('low', $result['level']);
    }

    public function testHighRiskScore() {
        $highUser = createTestUser(['personalInfo' => ['country' => 'IR']]);
        for ($i = 0; $i < 15; $i++) {
            $this->db->getCollection('blockchain_transactions')->insertOne([
                'userId' => $highUser['_id'],
                'createdAt' => new MongoDB\BSON\UTCDateTime()
            ]);
        }
        $this->db->getCollection('kyc_verifications')->insertOne([
            'userId' => new MongoDB\BSON\ObjectId($highUser['_id']),
            'verificationResult' => 'PENDING',
            'status' => 'PENDING',
            'createdAt' => new MongoDB\BSON\UTCDateTime()
        ]);

        $result = $this->service->calculateRiskScore($highUser['_id']);
        $this->assertTrue($result['success']);
        $this->assertEquals('high', $result['level']);

        $this->db->getCollection('users')->deleteMany(['_id' => new MongoDB\BSON\ObjectId($highUser['_id'])]);
        $this->db->getCollection('blockchain_transactions')->deleteMany(['userId' => $highUser['_id']]);
        $this->db->getCollection('kyc_verifications')->deleteMany(['userId' => new MongoDB\BSON\ObjectId($highUser['_id'])]);
    }
}
