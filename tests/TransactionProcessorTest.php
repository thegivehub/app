<?php
// tests/TransactionProcessorTest.php

use PHPUnit\Framework\TestCase;

class TransactionProcessorTest extends TestCase {
    private $transactionProcessor;
    private $db;
    private $testUser;
    private $testCampaign;
    
    protected function setUp(): void {
        $this->transactionProcessor = $this->createMock(TransactionProcessor::class);
        $this->db = new Database();
        
        // Create test user and campaign
        $this->testUser = createTestUser([
            'email' => 'donor@example.com',
            'username' => 'testdonor'
        ]);
        
        $this->testCampaign = createTestCampaign([
            'title' => 'Test Donation Campaign',
            'stellarAddress' => 'GABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
        ]);
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->db->getCollection('users')->deleteMany([
            'email' => 'donor@example.com'
        ]);
        
        $this->db->getCollection('campaigns')->deleteMany([
            'title' => 'Test Donation Campaign'
        ]);
        
        $this->db->getCollection('donations')->deleteMany([
            'campaignId' => new MongoDB\BSON\ObjectId($this->testCampaign['_id'])
        ]);
    }
    
    public function testProcessDonation() {
        // Since we can't easily mock Stellar SDK, we'll test the database operations
        // First, create a real TransactionProcessor instance
        $processor = new TransactionProcessor(true); // true for testnet
        
        // Mock the Stellar transaction submission by monkey patching the class
        $reflection = new ReflectionClass($processor);
        $initiateTransactionMethod = $reflection->getMethod('initiateTransaction');
        $initiateTransactionMethod->setAccessible(true);
        
        // Create a new reflection method to update the mock behavior
        $mockSubmitMethod = function($donationData) {
            // Mock a successful transaction with a fake hash
            $txHash = 'mock_tx_hash_' . uniqid();
            return [
                'success' => true,
                'transactionHash' => $txHash
            ];
        };
        
        // Mock the initiateTransaction method
        $processorMock = $this->getMockBuilder(TransactionProcessor::class)
            ->setConstructorArgs([true]) // true for testnet
            ->onlyMethods(['initiateTransaction'])
            ->getMock();
        
        $processorMock->method('initiateTransaction')
            ->willReturn([
                'success' => true,
                'transactionHash' => 'mock_tx_hash_' . uniqid()
            ]);
        
        // Create donation parameters
        $donationParams = [
            'donorId' => $this->testUser['_id'],
            'campaignId' => $this->testCampaign['_id'],
            'amount' => '50.0',
            'sourceSecret' => 'S0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ABCDEFG', // Fake secret key
            'isAnonymous' => false,
            'message' => 'Test donation'
        ];
        
        // Process the donation with our mocked object
        $result = $processorMock->processDonation($donationParams);
        
        // Check if the result is successful
        $this->assertTrue($result['success'], "Donation should be processed successfully");
        $this->assertArrayHasKey('transactionHash', $result, "Result should contain transaction hash");
        $this->assertArrayHasKey('transactionId', $result, "Result should contain transaction ID");
        
        // Check if the donation was recorded in the database
        $donation = $this->db->getCollection('donations')->findOne([
            'transaction.txHash' => $result['transactionHash']
        ]);
        
        $this->assertNotNull($donation, "Donation should be recorded in database");
        $this->assertEquals($this->testUser['_id'], $donation['userId'], "Donation should have correct user ID");
        $this->assertEquals($this->testCampaign['_id'], $donation['campaignId'], "Donation should have correct campaign ID");
        $this->assertEquals(50.0, $donation['amount']['value'], "Donation should have correct amount");
        $this->assertEquals('completed', $donation['status'], "Donation should have status 'completed'");
    }
    
    public function testGetTransactionDetails() {
        // Create a test donation in the database
        $donation = createTestDonation([
            'userId' => $this->testUser['_id'],
            'campaignId' => $this->testCampaign['_id'],
            'amount' => [
                'value' => 75.5,
                'currency' => 'XLM'
            ],
            'transaction' => [
                'txHash' => 'test_tx_hash_' . uniqid(),
                'status' => 'completed'
            ]
        ]);
        
        // Create a TransactionProcessor instance
        $processor = new TransactionProcessor(true); // true for testnet
        
        // Get transaction details
        $result = $processor->getTransactionDetails($donation['_id']);
        
        // Verify the result
        $this->assertTrue($result['success'], "Should retrieve transaction details successfully");
        $this->assertArrayHasKey('transaction', $result, "Result should contain transaction data");
        $this->assertEquals(75.5, $result['transaction']['amount'], "Transaction should have correct amount");
        $this->assertEquals('completed', $result['transaction']['status'], "Transaction should have correct status");
        $this->assertEquals($donation['transaction']['txHash'], $result['transaction']['txHash'], "Transaction should have correct hash");
    }
    
    public function testCreateCampaignDonationReport() {
        // Create multiple test donations for the same campaign
        $donations = [];
        $amounts = [10, 20, 30, 40, 50];
        $types = ['one-time', 'one-time', 'recurring', 'one-time', 'recurring'];
        $visibilities = ['public', 'anonymous', 'public', 'public', 'anonymous'];
        
        foreach ($amounts as $i => $amount) {
            $donations[] = createTestDonation([
                'userId' => $this->testUser['_id'],
                'campaignId' => $this->testCampaign['_id'],
                'amount' => [
                    'value' => $amount,
                    'currency' => 'XLM'
                ],
                'type' => $types[$i],
                'visibility' => $visibilities[$i],
                // Space donations over time for reporting
                'created' => new MongoDB\BSON\UTCDateTime((time() - ($i * 86400)) * 1000) // Separate by days
            ]);
        }
        
        // Create a TransactionProcessor instance
        $processor = new TransactionProcessor(true); // true for testnet
        
        // Get donation report
        $result = $processor->createCampaignDonationReport($this->testCampaign['_id']);
        
        // Verify the result
        $this->assertTrue($result['success'], "Should generate report successfully");
        $this->assertArrayHasKey('report', $result, "Result should contain report data");
        $this->assertArrayHasKey('summary', $result['report'], "Report should include summary");
        
        // Check summary calculations
        $summary = $result['report']['summary'];
        $this->assertEquals(150, $summary['totalAmount'], "Total amount should be correct");
        $this->assertEquals(1, $summary['uniqueDonors'], "Unique donors count should be correct");
        $this->assertEquals(5, $summary['totalDonations'], "Total donations count should be correct");
        $this->assertEquals(3, $summary['oneTimeDonations'], "One-time donations count should be correct");
        $this->assertEquals(2, $summary['recurringDonations'], "Recurring donations count should be correct");
        $this->assertEquals(2, $summary['anonymousDonations'], "Anonymous donations count should be correct");
        $this->assertEquals(3, $summary['publicDonations'], "Public donations count should be correct");
        $this->assertEquals(30, $summary['averageDonation'], "Average donation should be correct");
        
        // Check time series data
        $this->assertArrayHasKey('charts', $result['report'], "Report should include charts data");
        $this->assertArrayHasKey('monthly', $result['report']['charts'], "Charts should include monthly data");
        $this->assertArrayHasKey('weekly', $result['report']['charts'], "Charts should include weekly data");
    }
}
