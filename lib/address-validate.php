<?php
// api/address/validate.php
require_once __DIR__ . '/../../lib/AddressValidator.php';

// Handler for address validation API endpoint
function handleAddressValidation() {
    // Set headers for JSON response
    header('Content-Type: application/json');
    
    // Handle CORS if needed
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed. Use POST.']);
        exit;
    }
    
    try {
        // Get JSON input
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data) {
            throw new Exception('Invalid JSON input');
        }
        
        // Extract address data
        $address = [
            'street' => $data['street'] ?? '',
            'unit' => $data['unit'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
            'zip' => $data['zip'] ?? '',
            'country' => $data['country'] ?? ''
        ];
        
        // Create validator instance
        $validator = new AddressValidator();
        
        // Validate address
        $result = $validator->validate($address);
        
        // Return validation result
        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Server error: ' . $e->getMessage()
        ]);
    }
}

// Execute the handler
handleAddressValidation();
