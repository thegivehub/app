<?php
// Test script for the Verification API with MongoDB document handling

// Set output type to plain text for better readability
header('Content-Type: text/plain');

// Required classes
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/Verification.php';
require_once __DIR__ . '/lib/Documents.php';
require_once __DIR__ . '/lib/DocumentUploader.php';
require_once __DIR__ . '/lib/db.php';

// Initialize needed components
$verification = new Verification();
$auth = new Auth();
$documents = new Documents();
$db = new Database();

echo "=== MongoDB CONNECTION TEST ===\n";
try {
    // Test if we can connect to MongoDB and list collections
    $collections = $db->db->listCollections();
    $collectionNames = [];
    
    foreach ($collections as $collection) {
        $collectionNames[] = $collection->getName();
    }
    
    echo "Connected to MongoDB successfully\n";
    echo "Available collections: " . implode(", ", $collectionNames) . "\n\n";
    
    // Check if documents collection exists
    if (in_array('documents', $collectionNames)) {
        echo "✓ 'documents' collection exists\n";
        
        // Get documents count
        $count = $db->db->documents->countDocuments([]);
        echo "  - Collection contains {$count} documents\n";
    } else {
        echo "✗ 'documents' collection DOES NOT exist\n";
        echo "  - Attempting to create collection...\n";
        
        try {
            $db->db->createCollection('documents');
            echo "  ✓ Collection created successfully\n";
        } catch (Exception $e) {
            echo "  ✗ Failed to create collection: " . $e->getMessage() . "\n";
        }
    }
    
    // Test MongoDB permissions
    $testCollection = 'test_collection_' . time();
    echo "\nTesting MongoDB write permissions...\n";
    
    try {
        $db->db->createCollection($testCollection);
        echo "✓ Created test collection\n";
        
        $result = $db->db->$testCollection->insertOne(['test' => true, 'timestamp' => new MongoDB\BSON\UTCDateTime()]);
        if ($result->getInsertedId()) {
            echo "✓ Inserted test document\n";
            $db->db->dropCollection($testCollection);
            echo "✓ Dropped test collection\n";
            echo "✓ MongoDB permissions OK\n";
        } else {
            echo "✗ Failed to insert document\n";
        }
    } catch (Exception $e) {
        echo "✗ MongoDB write test failed: " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "✗ MongoDB connection failed: " . $e->getMessage() . "\n";
}

echo "\n=== VERIFICATION CREATION TEST ===\n";

// Mock test data - exactly as sent from the client
$testData = [
    'firstName' => 'Christopher',
    'lastName' => 'Robison',
    'dateOfBirth' => '1970-10-15',
    'address' => '621 Holloway Ave.',
    'city' => 'San Francisco',
    'state' => 'CA',
    'postalCode' => '94112',
    'country' => 'US'
];

// Create a verification directly
try {
    $result = $verification->create($testData);
    
    echo "Verification creation result:\n";
    print_r($result);
    
    if ($result['success']) {
        echo "✓ Verification created successfully with ID: {$result['verificationId']}\n";
        $verificationId = $result['verificationId'];
        
        // Now let's test document creation and referencing
        echo "\n=== DOCUMENT CREATION TEST ===\n";
        
        // Create a test document directly in MongoDB
        try {
            $document = [
                'userId' => new MongoDB\BSON\ObjectId($auth->getUserIdFromToken() ?: '000000000000000000000001'),
                'type' => 'ID_DOCUMENT',
                'subType' => 'passport',
                'filePath' => '/uploads/documents/test_document.jpg',
                'url' => '/uploads/documents/test_document.jpg',
                'fileName' => 'test_document.jpg',
                'fileType' => 'image/jpeg',
                'meta' => [
                    'documentType' => 'passport',
                    'documentNumber' => 'TEST' . rand(10000, 99999),
                    'documentExpiry' => new MongoDB\BSON\UTCDateTime(strtotime('+5 years')),
                    'uploadedAt' => new MongoDB\BSON\UTCDateTime()
                ],
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];
            
            // Use direct MongoDB insertion
            $docResult = $db->db->documents->insertOne($document);
            
            if ($docResult->getInsertedId()) {
                $documentId = (string)$docResult->getInsertedId();
                echo "✓ Document created successfully with ID: {$documentId}\n";
                
                // Now test updating the verification with document reference
                echo "\n=== DOCUMENT REFERENCE TEST ===\n";
                $updateResult = $verification->updateDocumentReference($verificationId, $documentId, 'primaryId');
                
                echo "Document reference update result:\n";
                print_r($updateResult);
                
                if ($updateResult['success']) {
                    echo "✓ Document reference updated successfully\n";
                    
                    // Verify that the reference was saved correctly
                    $updatedVerification = $verification->read($verificationId);
                    
                    if ($updatedVerification && isset($updatedVerification['documents']) &&
                        isset($updatedVerification['documents']['primaryId']) &&
                        $updatedVerification['documents']['primaryId'] == $documentId) {
                        echo "✓ Document reference verified in database\n";
                    } else {
                        echo "✗ Document reference verification failed\n";
                        echo "Verification documents field: " . json_encode($updatedVerification['documents'] ?? 'not set') . "\n";
                    }
                } else {
                    echo "✗ Document reference update failed\n";
                }
            } else {
                echo "✗ Document creation failed\n";
            }
        } catch (Exception $e) {
            echo "✗ Document test exception: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
    } else {
        echo "✗ Verification creation failed: " . ($result['error'] ?? 'Unknown error') . "\n";
        if (isset($result['details'])) {
            echo "Details: " . $result['details'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Verification exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}