<?php
// Debug verification API - this file contains utilities for testing 
// the verification system and creating test data
header('Content-Type: text/plain');
require_once __DIR__ . '/lib/Verification.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/Documents.php';

// Initialize controllers
$verification = new Verification();
$auth = new Auth();
$documents = new Documents();
$db = new Database();

// Get command from query string
$command = $_GET['cmd'] ?? 'help';

// Handle different commands
switch ($command) {
    case 'help':
        echo "Verification Debug Tool\n";
        echo "======================\n\n";
        echo "Available commands:\n";
        echo "?cmd=help - Show this help\n";
        echo "?cmd=stats - Show verification statistics\n";
        echo "?cmd=status - Show status of current user's verification\n";
        echo "?cmd=create - Create a test verification for current user\n";
        echo "?cmd=list - List all verifications\n";
        echo "?cmd=details&id=X - Show details for verification with ID X\n";
        echo "?cmd=mark-submitted&id=X - Mark verification X as SUBMITTED\n";
        echo "?cmd=mark-approved&id=X - Mark verification X as APPROVED\n";
        echo "?cmd=mark-rejected&id=X - Mark verification X as REJECTED\n";
        echo "?cmd=mock-results&id=X - Add mock verification results to verification X\n";
        echo "?cmd=check-collections - Check MongoDB collections and create if missing\n";
        echo "?cmd=create-document - Create a test document directly in documents collection\n";
        break;
        
    case 'stats':
        echo "Verification Statistics\n";
        echo "======================\n\n";
        $stats = $verification->stats();
        foreach ($stats as $status => $count) {
            echo "$status: $count\n";
        }
        break;
        
    case 'status':
        echo "Current User Verification Status\n";
        echo "==============================\n\n";
        try {
            $status = $verification->getUserVerificationStatus();
            echo "User ID: " . $auth->getUserIdFromToken() . "\n\n";
            print_r($status);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        break;
        
    case 'create':
        echo "Creating Test Verification\n";
        echo "========================\n\n";
        try {
            // Sample data that mimics what's being sent from the form
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
            
            $result = $verification->create($testData);
            echo "Create Result:\n";
            print_r($result);
            
            if ($result['success']) {
                echo "\nCreated verification ID: " . $result['verificationId'] . "\n";
                
                // Add document references
                echo "\nAdding document references...\n";
                $verification->updateDocumentReference($result['verificationId'], 'mock-document-id-123', 'primaryId');
                $verification->updateDocumentReference($result['verificationId'], 'mock-selfie-id-456', 'selfie');
                
                echo "\nVerification created successfully with document references.\n";
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
        }
        break;
        
    case 'list':
        echo "All Verifications\n";
        echo "================\n\n";
        try {
            $list = $verification->list(['limit' => 20, 'sort' => ['createdAt' => -1]]);
            foreach ($list as $item) {
                echo "ID: " . $item['_id'] . "\n";
                echo "User: " . $item['userId'] . "\n";
                echo "Status: " . ($item['status'] ?? 'UNKNOWN') . "\n";
                echo "Created: " . date('Y-m-d H:i:s', $item['createdAt']->toDateTime()->getTimestamp()) . "\n";
                echo "Name: " . $item['personalInfo']['firstName'] . " " . $item['personalInfo']['lastName'] . "\n";
                echo "-------------------------------------\n";
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        break;
        
    case 'details':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo "Error: Missing ID parameter\n";
            break;
        }
        
        echo "Verification Details\n";
        echo "===================\n\n";
        try {
            $details = $verification->details($id);
            if (!$details) {
                echo "Verification not found with ID: $id\n";
                break;
            }
            
            echo "ID: " . $details['_id'] . "\n";
            echo "User: " . $details['userId'] . "\n";
            echo "Status: " . ($details['status'] ?? 'UNKNOWN') . "\n";
            echo "Created: " . date('Y-m-d H:i:s', $details['createdAt']->toDateTime()->getTimestamp()) . "\n";
            
            echo "\nPersonal Information:\n";
            echo "Name: " . $details['personalInfo']['firstName'] . " " . $details['personalInfo']['lastName'] . "\n";
            echo "DOB: " . date('Y-m-d', $details['personalInfo']['dateOfBirth']->toDateTime()->getTimestamp()) . "\n";
            echo "Address: " . $details['personalInfo']['address'] . "\n";
            echo "City: " . $details['personalInfo']['city'] . "\n";
            echo "State: " . $details['personalInfo']['state'] . "\n";
            echo "Postal Code: " . $details['personalInfo']['postalCode'] . "\n";
            echo "Country: " . $details['personalInfo']['country'] . "\n";
            
            echo "\nDocuments:\n";
            if (isset($details['documents'])) {
                foreach ($details['documents'] as $type => $docId) {
                    echo "$type: $docId\n";
                }
            } else {
                echo "No documents found\n";
            }
            
            echo "\nVerification Results:\n";
            if (isset($details['verificationResults'])) {
                print_r($details['verificationResults']);
            } else {
                echo "No verification results found\n";
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        break;
        
    case 'mark-submitted':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo "Error: Missing ID parameter\n";
            break;
        }
        
        echo "Marking Verification as SUBMITTED\n";
        echo "================================\n\n";
        try {
            $result = $verification->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                [
                    '$set' => [
                        'status' => 'SUBMITTED', 
                        'submittedAt' => new MongoDB\BSON\UTCDateTime(),
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            echo "Update Result:\n";
            print_r($result);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        break;
        
    case 'mark-approved':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo "Error: Missing ID parameter\n";
            break;
        }
        
        echo "Marking Verification as APPROVED\n";
        echo "==============================\n\n";
        try {
            $result = $verification->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                [
                    '$set' => [
                        'status' => 'APPROVED', 
                        'reviewedAt' => new MongoDB\BSON\UTCDateTime(),
                        'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                        'reviewedBy' => 'DEBUG-ADMIN',
                        'reviewNotes' => 'Approved via debug tool'
                    ]
                ]
            );
            
            echo "Update Result:\n";
            print_r($result);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        break;
        
    case 'mark-rejected':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo "Error: Missing ID parameter\n";
            break;
        }
        
        echo "Marking Verification as REJECTED\n";
        echo "==============================\n\n";
        try {
            $result = $verification->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                [
                    '$set' => [
                        'status' => 'REJECTED', 
                        'reviewedAt' => new MongoDB\BSON\UTCDateTime(),
                        'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                        'reviewedBy' => 'DEBUG-ADMIN',
                        'reviewNotes' => 'Rejected via debug tool'
                    ]
                ]
            );
            
            echo "Update Result:\n";
            print_r($result);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        break;
        
    case 'mock-results':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo "Error: Missing ID parameter\n";
            break;
        }
        
        echo "Adding Mock Verification Results\n";
        echo "==============================\n\n";
        try {
            $result = $verification->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($id)],
                [
                    '$set' => [
                        'verificationResults' => [
                            'success' => true,
                            'similarity' => 0.92,
                            'confidence' => 0.95,
                            'liveness' => 0.97,
                            'timestamp' => new MongoDB\BSON\UTCDateTime(),
                            'provider' => 'DEBUG-TOOL',
                            'message' => 'Mock verification results added via debug tool'
                        ],
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
            
            echo "Update Result:\n";
            print_r($result);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
        break;
        
    case 'check-collections':
        echo "Checking MongoDB Collections\n";
        echo "===========================\n\n";
        try {
            // List all collections
            echo "Listing all MongoDB collections...\n";
            $collections = $db->db->listCollections();
            $collectionNames = [];
            
            foreach ($collections as $collection) {
                $collectionNames[] = $collection->getName();
            }
            
            echo "Found collections: " . implode(", ", $collectionNames) . "\n\n";
            
            // Check for documents collection
            if (in_array('documents', $collectionNames)) {
                echo "✓ 'documents' collection exists\n";
                
                // Get count of documents
                $count = $db->db->documents->countDocuments([]);
                echo "  - Collection contains {$count} documents\n";
                
                // Get a sample document if any exist
                if ($count > 0) {
                    $sample = $db->db->documents->findOne([]);
                    echo "  - Sample document fields: " . implode(', ', array_keys((array)$sample)) . "\n";
                }
            } else {
                echo "⚠ 'documents' collection does not exist\n";
                
                // Try to create the collection
                echo "  - Attempting to create 'documents' collection...\n";
                try {
                    $db->db->createCollection('documents');
                    echo "  ✓ Collection created successfully\n";
                    
                    // Create indexes
                    echo "  - Creating indexes...\n";
                    $db->db->documents->createIndex(['userId' => 1]);
                    $db->db->documents->createIndex(['type' => 1]);
                    echo "  ✓ Indexes created\n";
                } catch (Exception $e) {
                    echo "  ✗ Failed to create collection: " . $e->getMessage() . "\n";
                }
            }
            
            // Check for verifications collection
            if (in_array('verifications', $collectionNames)) {
                echo "\n✓ 'verifications' collection exists\n";
                
                // Get count of verifications
                $count = $db->db->verifications->countDocuments([]);
                echo "  - Collection contains {$count} verifications\n";
                
                // Get a sample verification if any exist
                if ($count > 0) {
                    $sample = $db->db->verifications->findOne([]);
                    echo "  - Sample verification fields: " . implode(', ', array_keys((array)$sample)) . "\n";
                    
                    // Check if verifications have document references
                    if (isset($sample->documents)) {
                        echo "  - Sample verification has 'documents' field\n";
                        
                        $docsField = (array)$sample->documents;
                        if (!empty($docsField)) {
                            echo "  - Document references: " . implode(', ', array_keys($docsField)) . "\n";
                        } else {
                            echo "  - Document references field is empty\n";
                        }
                    } else {
                        echo "  ⚠ Sample verification does not have 'documents' field\n";
                    }
                }
            } else {
                echo "\n⚠ 'verifications' collection does not exist\n";
            }
            
        } catch (Exception $e) {
            echo "Error checking collections: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
        break;
        
    case 'create-document':
        echo "Creating Test Document\n";
        echo "====================\n\n";
        try {
            // Get user ID or use a test ID
            $userId = $auth->getUserIdFromToken();
            if (!$userId) {
                echo "No authenticated user found, using test user ID\n";
                $userId = '000000000000000000000001'; // Test ID
            } else {
                echo "Using authenticated user ID: $userId\n";
            }
            
            // Create a test document
            $testDoc = [
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'type' => 'ID_DOCUMENT',
                'subType' => 'passport',
                'fileName' => 'test_document_' . time() . '.jpg',
                'filePath' => '/uploads/documents/test_document_' . time() . '.jpg',
                'url' => '/uploads/documents/test_document_' . time() . '.jpg',
                'fileType' => 'image/jpeg',
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                'meta' => [
                    'documentType' => 'passport',
                    'documentNumber' => 'TEST-' . rand(10000, 99999),
                    'documentExpiry' => new MongoDB\BSON\UTCDateTime(strtotime('+5 years')),
                    'originalName' => 'test_passport.jpg',
                    'mimeType' => 'image/jpeg',
                    'size' => 12345,
                    'uploadedBy' => $userId,
                    'uploadedAt' => new MongoDB\BSON\UTCDateTime()
                ]
            ];
            
            echo "Attempting to create document using direct MongoDB access...\n";
            $result = $db->db->documents->insertOne($testDoc);
            
            if ($result && $result->getInsertedId()) {
                $docId = (string)$result->getInsertedId();
                echo "✓ Document created successfully with ID: $docId\n";
                
                // Now try to fetch the document to verify it exists
                echo "Verifying document was stored correctly...\n";
                $stored = $db->db->documents->findOne(['_id' => new MongoDB\BSON\ObjectId($docId)]);
                
                if ($stored) {
                    echo "✓ Document successfully retrieved from database\n";
                    echo "Document fields: " . implode(', ', array_keys((array)$stored)) . "\n";
                } else {
                    echo "✗ Failed to retrieve document from database\n";
                }
            } else {
                echo "✗ Failed to create document\n";
            }
            
        } catch (Exception $e) {
            echo "Error creating document: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
        break;
        
    default:
        echo "Unknown command: $command\n";
        echo "Use ?cmd=help to see available commands\n";
}