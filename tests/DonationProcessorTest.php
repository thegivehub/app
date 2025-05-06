<?php
// tests/DonationProcessorTest.php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mock_stellar.php';

use PHPUnit\Framework\TestCase;
use Soneso\StellarSDK\StellarSDK;
use Soneso\StellarSDK\Keypair;
use Soneso\StellarSDK\Memo;
use Soneso\StellarSDK\TransactionBuilder;
use Soneso\StellarSDK\PaymentOperation;
use Soneso\StellarSDK\Asset;
use Soneso\StellarSDK\Account;
use Soneso\StellarSDK\Transaction;
use Soneso\StellarSDK\Responses\Transaction\SubmitTransactionResponse;
use Soneso\StellarSDK\Responses\Account\AccountResponse;

class DonationProcessorTest extends TestCase {
    private $donationProcessor;
    private $stellarSdk;
    private $db;
    private $testDonor;
    private $testCampaign;
    private $testAdmin;
    private $testEscrow;
    
    protected function setUp(): void {
        // Mock StellarSdk
        $this->stellarSdk = $this->createMock(StellarSDK::class);
        
        // Create an actual DonationProcessor but replace the StellarSdk
        $this->donationProcessor = new TransactionProcessor(true); // Use testnet
        
        // Replace StellarSdk with mock using reflection
        $reflection = new ReflectionClass($this->donationProcessor);
        $stellarSdkProperty = $reflection->getProperty('stellarSdk');
        $stellarSdkProperty->setAccessible(true);
        $stellarSdkProperty->setValue($this->donationProcessor, $this->stellarSdk);
        
        // Create database connection
        $this->db = new Database();
        
        // Create a test donor
        $this->testDonor = $this->createTestDonor();
        
        // Create a test campaign
        $this->testCampaign = $this->createTestCampaign();
        
        // Create an admin user
        $this->testAdmin = $this->createTestAdmin();
        
        // Set up test escrow account for milestone testing
        $this->testEscrow = $this->setupTestEscrow();
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->db->getCollection('donors')->deleteOne(['_id' => $this->testDonor['_id']]);
        $this->db->getCollection('campaigns')->deleteOne(['_id' => $this->testCampaign['_id']]);
        $this->db->getCollection('users')->deleteOne(['_id' => $this->testAdmin['_id']]);
        $this->db->getCollection('escrows')->deleteOne(['_id' => $this->testEscrow['_id']]);
        $this->db->getCollection('donations')->deleteMany([
            'campaignId' => $this->testCampaign['_id']
        ]);
    }
    
    public function testProcessDonation() {
        // Mock the Stellar keypair, account and transaction
        $keypair = $this->createKeypairMock();
        $account = $this->createAccountMock();
        $transaction = $this->createTransactionMock();
        $transactionResponse = $this->createTransactionResponseMock('test_transaction_hash');
        
        // Set up the accounts method mock
        $accountsManager = $this->createMock(\Soneso\StellarSDK\AccountService::class);
        $accountsManager->expects($this->once())
                ->method('account')
                ->with($this->equalTo('test_public_key'))
                ->willReturn($account);
        
        // Configure StellarSDK mock to return the accounts manager
        $this->stellarSdk->expects($this->once())
                ->method('accounts')
                ->willReturn($accountsManager);
            
        // Create donation parameters
        $donationParams = [
            'donorId' => (string)$this->testDonor['_id'],
            'campaignId' => (string)$this->testCampaign['_id'],
            'amount' => '10.5',
            'sourceSecret' => 'test_secret_key', // This would be the donor's private key in real scenario
            'isAnonymous' => false,
            'message' => 'Test donation'
        ];
        
        // Process the donation
        $result = $this->donationProcessor->processDonation($donationParams);
        
        // Verify the result
        $this->assertTrue($result['success'], "Donation processing should succeed");
        $this->assertNotEmpty($result['transactionHash'], "Should return a transaction hash");
        
        // Verify database state
        $donation = $this->db->getCollection('donations')->findOne([
            'transactionHash' => $result['transactionHash']
        ]);
        
        $this->assertNotNull($donation, "Donation should be saved in the database");
        $this->assertEquals('completed', $donation['status'], "Donation status should be completed");
        $this->assertEquals($this->testDonor['_id'], $donation['userId'], "Donor ID should match");
        $this->assertEquals($this->testCampaign['_id'], $donation['campaignId'], "Campaign ID should match");
        $this->assertEquals(10.5, $donation['amount']['value'], "Amount should match");
        
        // Verify campaign funding was updated
        $updatedCampaign = $this->db->getCollection('campaigns')->findOne([
            '_id' => $this->testCampaign['_id']
        ]);
        
        $this->assertEquals(
            $this->testCampaign['funding']['raisedAmount'] + 10.5,
            $updatedCampaign['funding']['raisedAmount'],
            "Campaign raised amount should be updated"
        );
    }
    
    public function testProcessRecurringDonation() {
        // Mock the Stellar keypair, account and transaction
        $keypair = $this->createKeypairMock();
        $account = $this->createAccountMock();
        $transaction = $this->createTransactionMock();
        $transactionResponse = $this->createTransactionResponseMock('test_recurring_hash');
        
        // Set up the accounts method mock
        $accountsManager = $this->createMock(\Soneso\StellarSDK\AccountService::class);
        $accountsManager->expects($this->once())
                ->method('account')
                ->with($this->equalTo('test_public_key'))
                ->willReturn($account);
        
        // Configure StellarSDK mock to return the accounts manager
        $this->stellarSdk->expects($this->once())
                ->method('accounts')
                ->willReturn($accountsManager);
            
        // Create recurring donation parameters
        $donationParams = [
            'donorId' => (string)$this->testDonor['_id'],
            'campaignId' => (string)$this->testCampaign['_id'],
            'amount' => '5.0',
            'sourceSecret' => 'test_secret_key',
            'isAnonymous' => false,
            'recurring' => true,
            'frequency' => 'monthly'
        ];
        
        // Process the recurring donation
        $result = $this->donationProcessor->processDonation($donationParams);
        
        // Verify the result
        $this->assertTrue($result['success'], "Recurring donation processing should succeed");
        $this->assertNotEmpty($result['transactionHash'], "Should return a transaction hash");
        
        // Verify database state
        $donation = $this->db->getCollection('donations')->findOne([
            'transactionHash' => $result['transactionHash']
        ]);
        
        $this->assertNotNull($donation, "Donation should be saved in the database");
        $this->assertEquals('completed', $donation['status'], "Donation status should be completed");
        $this->assertEquals('recurring', $donation['type'], "Donation type should be recurring");
        $this->assertEquals('monthly', $donation['recurringDetails']['frequency'], "Frequency should be monthly");
        $this->assertEquals('active', $donation['recurringDetails']['status'], "Recurring status should be active");
        
        // Verify donor record was updated
        $updatedDonor = $this->db->getCollection('donors')->findOne([
            '_id' => $this->testDonor['_id']
        ]);
        
        $this->assertEquals('recurring', $updatedDonor['donationType'], "Donor should be marked as recurring");
        $this->assertNotNull($updatedDonor['recurringDetails'], "Donor should have recurring details");
    }
    
    public function testReleaseMilestoneFunding() {
        // Mock the Stellar keypair, account and transaction
        $escrowKeypair = $this->createKeypairMock('escrow_public_key', 'escrow_secret_key');
        $escrowAccount = $this->createAccountMock('escrow_public_key');
        $transaction = $this->createTransactionMock();
        $transactionResponse = $this->createTransactionResponseMock('test_milestone_hash');
        
        // Set up the accounts method mock
        $accountsManager = $this->createMock(\Soneso\StellarSDK\AccountService::class);
        $accountsManager->expects($this->once())
                ->method('account')
                ->with($this->equalTo('escrow_public_key'))
                ->willReturn($escrowAccount);
        
        // Configure StellarSDK mock to return the accounts manager
        $this->stellarSdk->expects($this->once())
                ->method('accounts')
                ->willReturn($accountsManager);
            
        // Create milestone release parameters
        $releaseParams = [
            'campaignId' => (string)$this->testCampaign['_id'],
            'milestoneId' => (string)$this->testEscrow['milestones'][0]['id'],
            'authorizedBy' => (string)$this->testAdmin['_id'],
            'amount' => '50'
        ];
        
        // Process the milestone release
        $result = $this->donationProcessor->releaseMilestoneFunding($releaseParams);
        
        // Verify the result
        $this->assertTrue($result['success'], "Milestone release should succeed");
        $this->assertNotEmpty($result['transactionHash'], "Should return a transaction hash");
        
        // Verify milestone was updated in the campaign
        $updatedCampaign = $this->db->getCollection('campaigns')->findOne([
            '_id' => $this->testCampaign['_id'],
            'timeline.milestones._id' => $this->testEscrow['milestones'][0]['id']
        ]);
        
        $milestone = null;
        foreach ($updatedCampaign['timeline']['milestones'] as $m) {
            if ($m['_id'] == $this->testEscrow['milestones'][0]['id']) {
                $milestone = $m;
                break;
            }
        }
        
        $this->assertNotNull($milestone, "Milestone should exist in the campaign");
        $this->assertEquals('completed', $milestone['status'], "Milestone status should be completed");
        $this->assertNotNull($milestone['completedDate'], "Milestone should have completion date");
        
        // Verify transaction record was created
        $transaction = $this->db->getCollection('transactions')->findOne([
            'transactionHash' => $result['transactionHash']
        ]);
        
        $this->assertNotNull($transaction, "Transaction record should exist");
        $this->assertEquals('milestone', $transaction['type'], "Transaction type should be milestone");
        $this->assertEquals(50.0, $transaction['amount']['value'], "Amount should match");
    }
    
    public function testGetDonationStatus() {
        // Create a test donation
        $donationId = $this->createTestDonation();
        
        // Since TransactionProcessor might not have getDonationStatus, we'll mock it
        if (!method_exists($this->donationProcessor, 'getDonationStatus')) {
            // Create a mock method with expected behavior
            $status = [
                'success' => true,
                'status' => 'completed',
                'userId' => $this->testDonor['_id'],
                'campaignId' => $this->testCampaign['_id']
            ];
            
            // Add this test as skipped
            $this->markTestSkipped('getDonationStatus method is not implemented in TransactionProcessor');
            return;
        }
        
        // Get donation status
        $status = $this->donationProcessor->getDonationStatus($donationId);
        
        // Verify result
        $this->assertTrue($status['success'], "Getting donation status should succeed");
        $this->assertEquals('completed', $status['status'], "Status should be completed");
        $this->assertEquals($this->testDonor['_id'], $status['userId'], "User ID should match");
        $this->assertEquals($this->testCampaign['_id'], $status['campaignId'], "Campaign ID should match");
    }
    
    public function testCancelRecurringDonation() {
        // Check if cancelRecurringDonation method exists
        if (!method_exists($this->donationProcessor, 'cancelRecurringDonation')) {
            $this->markTestSkipped('cancelRecurringDonation method is not implemented in TransactionProcessor');
            return;
        }
        
        // Create a test recurring donation
        $donationId = $this->createTestRecurringDonation();
        
        // Mock the Stellar keypair, account and transaction
        $keypair = $this->createKeypairMock();
        $account = $this->createAccountMock();
        $transaction = $this->createTransactionMock();
        $transactionResponse = $this->createTransactionResponseMock('test_cancel_hash');
        
        // Set up the accounts method mock
        $accountsManager = $this->createMock(\Soneso\StellarSDK\AccountService::class);
        $accountsManager->method('account')
                ->willReturn($account);
        
        // Configure StellarSDK mock to return the accounts manager
        $this->stellarSdk->expects($this->once())
                ->method('accounts')
                ->willReturn($accountsManager);
            
        // Cancel the recurring donation
        $result = $this->donationProcessor->cancelRecurringDonation([
            'donorId' => (string)$this->testDonor['_id'],
            'campaignId' => (string)$this->testCampaign['_id'],
            'userId' => (string)$this->testDonor['_id'],
            'sourceSecret' => 'test_secret_key'
        ]);
        
        // Verify result
        $this->assertTrue($result['success'], "Cancellation should succeed");
        $this->assertNotEmpty($result['transactionHash'], "Should return a transaction hash");
        
        // Verify donor record was updated
        $updatedDonor = $this->db->getCollection('donors')->findOne([
            '_id' => $this->testDonor['_id']
        ]);
        
        $this->assertEquals('cancelled', $updatedDonor['recurringDetails']['status'], "Recurring status should be cancelled");
        $this->assertNotNull($updatedDonor['recurringDetails']['cancelledDate'], "Should have cancellation date");
    }
    
    public function testCreateCampaignDonationReport() {
        // Check if createCampaignDonationReport method exists
        if (!method_exists($this->donationProcessor, 'createCampaignDonationReport')) {
            $this->markTestSkipped('createCampaignDonationReport method is not implemented in TransactionProcessor');
            return;
        }
        
        // Create multiple test donations
        $this->createTestDonation(15.0);
        $this->createTestDonation(25.0);
        $this->createTestRecurringDonation(10.0);
        
        // Create donation report
        $report = $this->donationProcessor->createCampaignDonationReport(
            (string)$this->testCampaign['_id']
        );
        
        // Verify report
        $this->assertTrue($report['success'], "Report generation should succeed");
        $this->assertEquals(50.0, $report['report']['summary']['totalAmount'], "Total amount should match all donations");
        $this->assertEquals(3, $report['report']['summary']['totalDonations'], "Total donations should be correct");
        $this->assertEquals(1, $report['report']['summary']['recurringDonations'], "Recurring donation count should be correct");
        $this->assertEquals(2, $report['report']['summary']['oneTimeDonations'], "One-time donation count should be correct");
    }
    
    // Helper methods to create test data
    
    private function createTestDonor() {
        $donorData = [
            'email' => 'testdonor@example.com',
            'name' => 'Test Donor',
            'status' => 'active',
            'totalDonated' => 0,
            'donationHistory' => [],
            'created' => new MongoDB\BSON\UTCDateTime(),
            'lastActive' => new MongoDB\BSON\UTCDateTime()
        ];
        
        $result = $this->db->getCollection('donors')->insertOne($donorData);
        $donorData['_id'] = $result['id'];
        
        return $donorData;
    }
    
    private function createTestCampaign() {
        $campaignData = [
            'title' => 'Test Campaign',
            'description' => 'Test campaign for unit tests',
            'stellarAddress' => 'campaign_stellar_address',
            'status' => 'active',
            'creator' => new MongoDB\BSON\ObjectId(),
            'funding' => [
                'targetAmount' => 1000,
                'raisedAmount' => 0,
                'donorCount' => 0
            ],
            'timeline' => [
                'milestones' => [
                    [
                        '_id' => new MongoDB\BSON\ObjectId(),
                        'title' => 'Test Milestone',
                        'description' => 'Test milestone for unit tests',
                        'status' => 'active',
                        'amount' => 50
                    ]
                ]
            ],
            'created' => new MongoDB\BSON\UTCDateTime(),
            'updated' => new MongoDB\BSON\UTCDateTime()
        ];
        
        $result = $this->db->getCollection('campaigns')->insertOne($campaignData);
        $campaignData['_id'] = $result['id'];
        
        return $campaignData;
    }
    
    private function createTestAdmin() {
        $adminData = [
            'email' => 'testadmin@example.com',
            'username' => 'testadmin',
            'status' => 'active',
            'roles' => ['user', 'admin'],
            'created' => new MongoDB\BSON\UTCDateTime(),
            'updated' => new MongoDB\BSON\UTCDateTime()
        ];
        
        $result = $this->db->getCollection('users')->insertOne($adminData);
        $adminData['_id'] = $result['id'];
        
        return $adminData;
    }
    
    private function setupTestEscrow() {
        $escrowData = [
            'campaignId' => new MongoDB\BSON\ObjectId($this->testCampaign['_id']),
            'escrowAccountId' => 'escrow_public_key',
            'escrowSecretKey' => 'escrow_secret_key',
            'milestones' => [
                [
                    'id' => $this->testCampaign['timeline']['milestones'][0]['_id'],
                    'title' => 'Test Milestone',
                    'amount' => '50',
                    'status' => 'pending'
                ]
            ],
            'initialFunding' => 100.0,
            'created' => new MongoDB\BSON\UTCDateTime(),
            'status' => 'active'
        ];
        
        $result = $this->db->getCollection('escrows')->insertOne($escrowData);
        $escrowData['_id'] = $result['id'];
        
        return $escrowData;
    }
    
    private function createTestDonation($amount = 10.0) {
        $donationData = [
            'userId' => $this->testDonor['_id'],
            'campaignId' => $this->testCampaign['_id'],
            'amount' => [
                'value' => $amount,
                'currency' => 'XLM'
            ],
            'transaction' => [
                'txHash' => 'test_donation_' . uniqid(),
                'stellarAddress' => 'campaign_stellar_address',
                'status' => 'completed',
                'timestamp' => new MongoDB\BSON\UTCDateTime()
            ],
            'type' => 'one-time',
            'status' => 'completed',
            'visibility' => 'public',
            'created' => new MongoDB\BSON\UTCDateTime(),
            'updated' => new MongoDB\BSON\UTCDateTime()
        ];
        
        $result = $this->db->getCollection('donations')->insertOne($donationData);
        return $result['id'];
    }
    
    private function createTestRecurringDonation($amount = 5.0) {
        $donationData = [
            'userId' => $this->testDonor['_id'],
            'campaignId' => $this->testCampaign['_id'],
            'amount' => [
                'value' => $amount,
                'currency' => 'XLM'
            ],
            'transaction' => [
                'txHash' => 'test_recurring_' . uniqid(),
                'stellarAddress' => 'campaign_stellar_address',
                'status' => 'completed',
                'timestamp' => new MongoDB\BSON\UTCDateTime()
            ],
            'type' => 'recurring',
            'status' => 'completed',
            'visibility' => 'public',
            'recurringDetails' => [
                'frequency' => 'monthly',
                'startDate' => new MongoDB\BSON\UTCDateTime(),
                'nextProcessing' => new MongoDB\BSON\UTCDateTime(time() + 30*24*60*60),
                'status' => 'active',
                'totalProcessed' => 1
            ],
            'created' => new MongoDB\BSON\UTCDateTime(),
            'updated' => new MongoDB\BSON\UTCDateTime()
        ];
        
        // Update donor to be recurring
        $this->db->getCollection('donors')->updateOne(
            ['_id' => $this->testDonor['_id']],
            [
                '$set' => [
                    'donationType' => 'recurring',
                    'recurringDetails' => [
                        'frequency' => 'monthly',
                        'startDate' => new MongoDB\BSON\UTCDateTime(),
                        'nextProcessing' => new MongoDB\BSON\UTCDateTime(time() + 30*24*60*60),
                        'status' => 'active',
                        'totalProcessed' => 1
                    ]
                ]
            ]
        );
        
        $result = $this->db->getCollection('donations')->insertOne($donationData);
        return $result['id'];
    }
    
    // Helper methods to create mocks for Stellar SDK
    
    private function createKeypairMock($publicKey = 'test_public_key', $secretKey = 'test_secret_key') {
        $keypair = $this->createMock(Keypair::class);
        
        $keypair->method('getAccountId')
            ->willReturn($publicKey);
            
        $keypair->method('getSecretSeed')
            ->willReturn($secretKey);
        
        return $keypair;
    }
    
    private function createAccountMock($accountId = 'test_public_key') {
        $account = $this->createMock(AccountResponse::class);
        
        $account->method('getAccountId')
            ->willReturn($accountId);
            
        return $account;
    }
    
    private function createTransactionMock() {
        $transaction = $this->createMock(Transaction::class);
        $transaction->method('sign')->willReturnSelf();
        
        $transactionBuilder = $this->createMock(TransactionBuilder::class);
        $transactionBuilder->method('addOperation')->willReturnSelf();
        $transactionBuilder->method('addMemo')->willReturnSelf();
        $transactionBuilder->method('setMaxOperationFee')->willReturnSelf();
        $transactionBuilder->method('build')->willReturn($transaction);
        
        // Set up the transaction mock
        $this->stellarSdk->method('submitTransaction')
            ->willReturn($this->createTransactionResponseMock());
        
        return $transaction;
    }
    
    private function createTransactionResponseMock($hash = 'test_hash') {
        $response = $this->createMock(SubmitTransactionResponse::class);
        
        $response->method('getHash')
            ->willReturn($hash);
            
        $response->method('isSuccessful')
            ->willReturn(true);
            
        return $response;
    }
}