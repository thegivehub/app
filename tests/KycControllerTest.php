<?php
// tests/KycControllerTest.php

use PHPUnit\Framework\TestCase;

class KycControllerTest extends TestCase {
    private $kycController;
    private $jumioService;
    private $auth;
    private $db;
    private $testUser;
    private $testAdmin;
    private $testToken;
    private $adminToken;
    
    protected function setUp(): void {
        // Create mock JumioService
        $this->jumioService = $this->createMock(JumioService::class);
        
        // Create actual Auth instance (we need real token generation)
        $this->auth = new Auth();
        
        // Create test controller with mock service
        $this->kycController = new KycController();
        
        // Replace JumioService with mock using reflection
        $reflection = new ReflectionClass($this->kycController);
        $jumioServiceProperty = $reflection->getProperty('jumioService');
        $jumioServiceProperty->setAccessible(true);
        $jumioServiceProperty->setValue($this->kycController, $this->jumioService);
        
        // Create database connection
        $this->db = new Database();
        
        // Create a regular test user
        $this->testUser = createTestUser([
            'email' => 'kyctest@example.com',
            'username' => 'kyctest'
        ]);
        
        // Create an admin test user
        $this->testAdmin = createTestUser([
            'email' => 'kycadmin@example.com',
            'username' => 'kycadmin',
            'roles' => ['user', 'admin']
        ]);
        
        // Generate tokens
        $this->testToken = generateTestToken($this->testUser['_id']);
        $this->adminToken = generateTestToken($this->testAdmin['_id']);
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->db->getCollection('users')->deleteMany([
            'email' => ['$in' => ['kyctest@example.com', 'kycadmin@example.com']]
        ]);
        
        $this->db->getCollection('kyc_verifications')->deleteMany([
            'userId' => ['$in' => [
                new MongoDB\BSON\ObjectId($this->testUser['_id']),
                new MongoDB\BSON\ObjectId($this->testAdmin['_id'])
            ]]
        ]);
    }
    
    public function testInitiateVerification() {
        // Set request headers
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->testToken;
        
        // Set up JumioService mock expectations
        $this->jumioService->expects($this->once())
            ->method('initiateVerification')
            ->with($this->equalTo($this->testUser['_id']))
            ->willReturn([
                'success' => true,
                'redirectUrl' => 'https://go.jumio.com/test-redirect',
                'verificationId' => 'test-verification-id'
            ]);
        
        // Call the method
        $result = $this->kycController->initiateVerification();
        
        // Verify the result
        $this->assertTrue($result['success'], "Verification initiation should succeed");
        $this->assertEquals('https://go.jumio.com/test-redirect', $result['redirectUrl'], "Should return correct redirect URL");
        $this->assertEquals('test-verification-id', $result['verificationId'], "Should return correct verification ID");
    }
    
    public function testGetVerificationStatus() {
        // Set request headers
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->testToken;
        
        // Set up JumioService mock expectations
        $this->jumioService->expects($this->once())
            ->method('getVerificationStatus')
            ->with($this->equalTo($this->testUser['_id']))
            ->willReturn([
                'success' => true,
                'status' => 'PENDING',
                'result' => 'PENDING',
                'verified' => false,
                'redirectUrl' => 'https://go.jumio.com/test-redirect',
                'lastUpdated' => new MongoDB\BSON\UTCDateTime(),
                'verificationId' => 'test-verification-id'
            ]);
        
        // Call the method
        $result = $this->kycController->getVerificationStatus();
        
        // Verify the result
        $this->assertTrue($result['success'], "Getting verification status should succeed");
        $this->assertEquals('PENDING', $result['status'], "Should return correct status");
        $this->assertFalse($result['verified'], "Should return correct verification state");
    }
    
    public function testHandleWebhook() {
        // Set up webhook payload
        $payload = [
            'transactionReference' => 'test-transaction-reference',
            'verificationStatus' => 'APPROVED_VERIFIED',
            'document' => [
                'type' => 'PASSPORT',
                'country' => 'USA'
            ],
            'transaction' => [
                'date' => '2023-01-01T12:00:00.000Z',
                'status' => 'DONE'
            ]
        ];
        
        // Set up JumioService mock expectations
        $this->jumioService->expects($this->once())
            ->method('processWebhook')
            ->with($this->equalTo($payload))
            ->willReturn([
                'success' => true,
                'message' => 'Webhook processed successfully',
                'status' => 'APPROVED_VERIFIED',
                'result' => 'APPROVED'
            ]);
        
        // Mock the POST request body
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $GLOBALS['HTTP_RAW_POST_DATA'] = json_encode($payload);
        
        // Override file_get_contents to return our mock data
        $this->setFileMock(json_encode($payload));
        
        // Call the method
        $result = $this->kycController->handleWebhook();
        
        // Verify the result
        $this->assertTrue($result['success'], "Webhook processing should succeed");
        $this->assertEquals('Webhook processed successfully', $result['message'], "Should return success message");
        $this->assertEquals('APPROVED', $result['result'], "Should return correct result");
    }
    
    public function testAdminOverride() {
        // Set request headers for admin
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->adminToken;
        
        // Set up override data
        $overrideData = [
            'userId' => $this->testUser['_id'],
            'status' => 'APPROVED',
            'reason' => 'Manual verification by admin'
        ];
        
        // Set up JumioService mock expectations
        $this->jumioService->expects($this->once())
            ->method('adminOverrideVerification')
            ->with(
                $this->equalTo($this->testUser['_id']),
                $this->equalTo('APPROVED'),
                $this->equalTo('Manual verification by admin'),
                $this->equalTo($this->testAdmin['_id'])
            )
            ->willReturn([
                'success' => true,
                'message' => 'Verification status overridden',
                'status' => 'APPROVED'
            ]);
        
        // Mock the POST request body
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setFileMock(json_encode($overrideData));
        
        // Call the method
        $result = $this->kycController->adminOverride();
        
        // Verify the result
        $this->assertTrue($result['success'], "Admin override should succeed");
        $this->assertEquals('Verification status overridden', $result['message'], "Should return success message");
    }
    
    public function testAdminOverrideWithNonAdmin() {
        // Set request headers for regular user (non-admin)
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->testToken;
        
        // Set up override data
        $overrideData = [
            'userId' => $this->testUser['_id'],
            'status' => 'APPROVED',
            'reason' => 'Manual verification by admin'
        ];
        
        // Mock the POST request body
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->setFileMock(json_encode($overrideData));
        
        // Call the method
        $result = $this->kycController->adminOverride();
        
        // Verify the result
        $this->assertFalse($result['success'], "Admin override should fail for non-admin");
        $this->assertArrayHasKey('error', $result, "Result should contain error message");
        $this->assertEquals('Admin access required', $result['error'], "Should return appropriate error");
    }
    
    public function testGenerateReport() {
        // Set request headers for admin
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->adminToken;
        
        // Set up JumioService mock expectations
        $this->jumioService->expects($this->once())
            ->method('generateKycReport')
            ->with($this->isType('array'))
            ->willReturn([
                'success' => true,
                'totalCount' => 10,
                'statusCounts' => [
                    'APPROVED' => 5,
                    'REJECTED' => 2,
                    'PENDING' => 3
                ],
                'completionRate' => 70,
                'verifications' => []
            ]);
        
        // Call the method
        $result = $this->kycController->generateReport();
        
        // Verify the result
        $this->assertTrue($result['success'], "Report generation should succeed");
        $this->assertEquals(10, $result['totalCount'], "Should return correct total count");
        $this->assertEquals(70, $result['completionRate'], "Should return correct completion rate");
    }
    
    /**
     * Helper method to mock file_get_contents for JSON payloads
     */
    private function setFileMock($returnValue) {
        // Create a mock for the global function file_get_contents
        $helper = $this;
        
        // Use namespace function override with runkit extension
        // This approach depends on having runkit extension enabled
        // Alternative: Use PHP-Scoper or similar for isolated testing
        if (function_exists('runkit7_function_redefine')) {
            runkit7_function_redefine('file_get_contents', 'string $filename', 
                'if ($filename === "php://input") { 
                    return "' . addslashes($returnValue) . '"; 
                } else { 
                    return \file_get_contents($filename); 
                }'
            );
        } else {
            // If runkit is not available, we'll have to use another approach
            // One option is to create a test-specific wrapper class for the controller
            // that overrides the method that calls file_get_contents
            // For simplicity in this example, we'll just note that this is a limitation
            echo "Note: file_get_contents mocking requires runkit extension. Test may fail without it.\n";
        }
    }
}
