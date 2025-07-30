<?php
require_once __DIR__ . '/../lib/autoload.php';

class DonationApiTest {
    private $baseUrl;
    private $cronKey;
    
    public function __construct() {
        $this->baseUrl = 'http://localhost/donation-api.php';
        $this->cronKey = getenv('CRON_API_KEY');
        
        if (!$this->cronKey) {
            throw new Exception('CRON_API_KEY environment variable not set');
        }
    }
    
    public function runTests() {
        $this->testSquarePayment();
        $this->testCryptoDonation();
        $this->testDonationStatus();
        $this->testRecurringDonations();
    }
    
    private function testSquarePayment() {
        echo "Testing Square payment...\n";
        
        $data = [
            'nonce' => 'fake-valid-nonce', // In tests this would be mocked
            'amount' => 10.00,
            'campaignId' => 'test-campaign-123',
            'donorInfo' => [
                'name' => 'Test User',
                'email' => 'test@example.com'
            ]
        ];
        
        $response = $this->makeRequest('square', 'POST', $data);
        
        if (!isset($response['transactionId'])) {
            throw new Exception('Square payment failed: ' . print_r($response, true));
        }
        
        echo "Square payment test passed\n";
    }
    
    private function testCryptoDonation() {
        echo "Testing crypto donation...\n";
        
        $data = [
            'cryptoType' => 'XLM',
            'amount' => 50,
            'campaignId' => 'test-campaign-123',
            'donorInfo' => [
                'name' => 'Crypto Donor',
                'email' => 'crypto@example.com'
            ]
        ];
        
        $response = $this->makeRequest('crypto', 'POST', $data);
        
        if (!isset($response['walletAddress'])) {
            throw new Exception('Crypto donation failed: ' . print_r($response, true));
        }
        
        echo "Crypto donation test passed\n";
    }
    
    private function testDonationStatus() {
        echo "Testing donation status check...\n";
        
        // First create a test donation
        $data = [
            'nonce' => 'fake-valid-nonce',
            'amount' => 5.00,
            'campaignId' => 'test-campaign-123',
            'donorInfo' => [
                'name' => 'Status Check',
                'email' => 'status@example.com'
            ]
        ];
        
        $createResponse = $this->makeRequest('square', 'POST', $data);
        $transactionId = $createResponse['transactionId'];
        
        // Now check status
        $response = $this->makeRequest('status', 'GET', null, ['id' => $transactionId]);
        
        if (!isset($response['status'])) {
            throw new Exception('Status check failed: ' . print_r($response, true));
        }
        
        echo "Donation status test passed\n";
    }
    
    private function testRecurringDonations() {
        echo "Testing recurring donations...\n";
        
        // Test processing due donations (requires cron key)
        $response = $this->makeRequest('recurring', 'GET', null, ['key' => $this->cronKey]);
        
        if (!isset($response['processed'])) {
            throw new Exception('Recurring donations test failed: ' . print_r($response, true));
        }
        
        echo "Recurring donations test passed\n";
    }
    
    private function makeRequest($action, $method, $data = null, $queryParams = []) {
        $url = $this->baseUrl . '?action=' . $action;
        
        if (!empty($queryParams)) {
            $url .= '&' . http_build_query($queryParams);
        }
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => "Content-Type: application/json\r\n",
                'ignore_errors' => true
            ]
        ];
        
        if ($data !== null) {
            $options['http']['content'] = json_encode($data);
        }
        
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception('Request failed');
        }
        
        return json_decode($response, true);
    }
}

// Run tests
try {
    $tester = new DonationApiTest();
    $tester->runTests();
    echo "All donation API tests passed successfully\n";
} catch (Exception $e) {
    echo "Test failed: " . $e->getMessage() . "\n";
    exit(1);
}
