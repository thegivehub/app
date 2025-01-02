<?php
// api/developer/register.php

header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/lib/DeveloperRegistration.php';

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid input data');
    }
    
    // Initialize registration handler
    $registration = new DeveloperRegistration();
    
    // Process registration
    $result = $registration->register($input);
    
    // Return response
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Registration failed: ' . $e->getMessage()
    ]);
}
