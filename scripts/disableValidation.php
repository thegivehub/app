<?php
// disableValidation.php - Script to completely disable MongoDB validation for the documents collection

require_once __DIR__ . '/lib/db.php';

echo "Disabling MongoDB validation for documents collection...\n";

try {
    // Get MongoDB native driver access
    $db = new Database();
    
    // Run the command to disable validation completely
    $command = [
        'collMod' => 'documents',
        'validationLevel' => 'off'  // Turn off validation completely
    ];
    
    $result = $db->db->command($command);
    
    if ($result['ok'] == 1) {
        echo "Successfully disabled validation for documents collection!\n";
    } else {
        echo "Failed to disable validation: " . json_encode($result) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 