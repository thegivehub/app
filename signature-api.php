<?php
/**
 * Signature API - Handles signature collection and retrieval
 */
require_once __DIR__ . '/lib/autoload.php';
require_once __DIR__ . '/lib/SignatureController.php';

// Parse the request
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : 'save';

// Initialize signature controller
$signatureController = new SignatureController();

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Helper function to send JSON response
function sendJson($code, $data) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Helper function to get JSON request body
function getJsonBody() {
    $json = file_get_contents('php://input');
    return json_decode($json, true) ?: [];
}

// Verify authentication
function getUserId() {
    // This is a placeholder - replace with your actual authentication logic
    // For example, you might check a session or JWT token
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
        // Process the authorization header to get user ID
        // This is just an example - implement your actual auth logic
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            $token = $matches[1];
            // Validate token and get user ID
            // For now, we'll just return a dummy user ID
            return 'user_' . substr(md5($token), 0, 8);
        }
    }
    
    // For testing purposes, allow user_id in query string
    if (isset($_GET['user_id'])) {
        return $_GET['user_id'];
    }
    
    return null;
}

// Route the request based on action and method
switch ($action) {
    case 'save':
        if ($method === 'POST') {
            // Get user ID from authentication
            $userId = getUserId();
            if (!$userId) {
                sendJson(401, ['error' => 'Authentication required']);
            }
            
            // Get request data
            $data = getJsonBody();
            
            // For form data submissions
            if (empty($data) && isset($_POST['signatureData'])) {
                $data = $_POST;
            }
            
            // Validate required fields
            if (empty($data['signatureData'])) {
                sendJson(400, ['error' => 'Signature data is required']);
            }
            
            $type = $data['type'] ?? 'other';
            $documentId = $data['documentId'] ?? null;
            $description = $data['description'] ?? null;
            $metadata = $data['metadata'] ?? [];
            
            // Save the signature
            $result = $signatureController->saveSignature(
                $userId,
                $data['signatureData'],
                $type,
                $documentId,
                $description,
                $metadata
            );
            
            sendJson($result['success'] ? 200 : 400, $result);
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'get':
        if ($method === 'GET') {
            // Get user ID from authentication
            $userId = getUserId();
            if (!$userId) {
                sendJson(401, ['error' => 'Authentication required']);
            }
            
            // Get signature by ID
            if (isset($_GET['id'])) {
                $signature = $signatureController->getSignature($_GET['id']);
                
                if (!$signature) {
                    sendJson(404, ['error' => 'Signature not found']);
                }
                
                // Check if the signature belongs to the authenticated user
                if ($signature['userId'] !== $userId) {
                    sendJson(403, ['error' => 'Access denied']);
                }
                
                sendJson(200, ['success' => true, 'signature' => $signature]);
            }
            
            // Get all signatures for the user
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            $options = [
                'page' => $page,
                'limit' => $limit
            ];
            
            $signatures = $signatureController->getUserSignatures($userId, $options);
            
            sendJson(200, [
                'success' => true,
                'signatures' => $signatures,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => count($signatures) // This is not accurate for total count, just a placeholder
                ]
            ]);
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'document':
        if ($method === 'GET') {
            // Get user ID from authentication
            $userId = getUserId();
            if (!$userId) {
                sendJson(401, ['error' => 'Authentication required']);
            }
            
            // Get document ID from query
            $documentId = $_GET['documentId'] ?? null;
            if (!$documentId) {
                sendJson(400, ['error' => 'Document ID is required']);
            }
            
            // Get signatures for the document
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            $options = [
                'page' => $page,
                'limit' => $limit
            ];
            
            $signatures = $signatureController->getDocumentSignatures($documentId, $options);
            
            sendJson(200, [
                'success' => true,
                'signatures' => $signatures,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => count($signatures) // This is not accurate for total count, just a placeholder
                ]
            ]);
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'delete':
        if ($method === 'DELETE' || ($method === 'POST' && isset($_POST['_method']) && $_POST['_method'] === 'DELETE')) {
            // Get user ID from authentication
            $userId = getUserId();
            if (!$userId) {
                sendJson(401, ['error' => 'Authentication required']);
            }
            
            // Get signature ID from query or body
            $data = getJsonBody();
            $signatureId = $_GET['id'] ?? $data['id'] ?? null;
            
            if (!$signatureId) {
                sendJson(400, ['error' => 'Signature ID is required']);
            }
            
            // Check if the signature belongs to the authenticated user
            $signature = $signatureController->getSignature($signatureId);
            if (!$signature) {
                sendJson(404, ['error' => 'Signature not found']);
            }
            
            if ($signature['userId'] !== $userId) {
                sendJson(403, ['error' => 'Access denied']);
            }
            
            // Delete the signature
            $result = $signatureController->deleteSignature($signatureId);
            
            sendJson($result['success'] ? 200 : 400, $result);
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    default:
        sendJson(400, ['error' => 'Invalid action']);
        break;
} 