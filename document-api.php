<?php
// document-api.php
require_once __DIR__ . '/lib/autoload.php';
require_once __DIR__ . '/lib/Document.php';
require_once __DIR__ . '/lib/Documents.php';

// Parse the request
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : 'upload';

// Initialize document uploader
$uploader = new Document();
$documents = new Documents();

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Route the request based on action and method
switch ($action) {
    case 'create':
        if ($method === 'POST') {
            // Handle document creation without file upload
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                sendJson(400, ['error' => 'Invalid JSON data']);
            }
            
            // Use Documents class create method to ensure all required fields are set
            $result = $documents->create(null, $data);
            sendJson(
                $result['success'] ? 200 : 400,
                $result
            );
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'upload':
        if ($method === 'POST') {
            // Handle document upload
            if (!isset($_FILES['document'])) {
                sendJson(400, ['error' => 'No document file provided']);
            }
            
            $type = $_POST['type'] ?? '';
            if (empty($type)) {
                sendJson(400, ['error' => 'Document type is required']);
            }
            
            $description = $_POST['description'] ?? '';
            
            $result = $uploader->uploadDocument($_FILES['document'], $type, $description);
            sendJson(
                $result['success'] ? 200 : 400,
                $result
            );
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'list':
        if ($method === 'GET') {
            // Get user documents
            $type = $_GET['type'] ?? null;
            $userId = $_GET['userId'] ?? null;
            
            $result = $uploader->getUserDocuments($userId, $type);
            sendJson(
                $result['success'] ? 200 : ($userId ? 404 : 401),
                $result
            );
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'get':
        if ($method === 'GET') {
            // Get document details
            $documentId = $_GET['id'] ?? null;
            
            if (!$documentId) {
                sendJson(400, ['error' => 'Document ID is required']);
            }
            
            $result = $uploader->getDocumentDetails($documentId);
            sendJson(
                $result['success'] ? 200 : ($result['error'] === 'Document not found' ? 404 : 403),
                $result
            );
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'verify':
        if ($method === 'PUT') {
            // Verify a document
            $data = json_decode(file_get_contents('php://input'), true);
            $documentId = $_GET['id'] ?? $data['documentId'] ?? null;
            $status = $data['status'] ?? null;
            $notes = $data['notes'] ?? '';
            
            if (!$documentId) {
                sendJson(400, ['error' => 'Document ID is required']);
            }
            
            if (!$status || !in_array($status, ['approved', 'rejected'])) {
                sendJson(400, ['error' => 'Valid status (approved or rejected) is required']);
            }
            
            $result = $uploader->verifyDocument($documentId, $status, $notes);
            sendJson(
                $result['success'] ? 200 : 403,
                $result
            );
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'delete':
        if ($method === 'DELETE') {
            // Delete a document
            $documentId = $_GET['id'] ?? null;
            
            if (!$documentId) {
                sendJson(400, ['error' => 'Document ID is required']);
            }
            
            $result = $uploader->deleteDocument($documentId);
            sendJson(
                $result['success'] ? 200 : 403,
                $result
            );
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    default:
        sendJson(404, ['error' => 'Action not found']);
}

/**
 * Send a JSON response
 * @param int $statusCode HTTP status code
 * @param array $data Response data
 */
function sendJson($statusCode, $data) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
