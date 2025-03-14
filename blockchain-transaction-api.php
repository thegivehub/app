<?php
/**
 * Blockchain Transaction API - Handles blockchain transaction status tracking
 */
require_once __DIR__ . '/lib/autoload.php';
require_once __DIR__ . '/lib/BlockchainTransactionController.php';

// Parse the request
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : 'status';

// Initialize blockchain transaction controller
$useTestnet = defined('STELLAR_TESTNET') && STELLAR_TESTNET === true;
$txController = new BlockchainTransactionController($useTestnet);

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// Check if user has admin privileges
function isAdmin() {
    // This is a placeholder - replace with your actual admin check logic
    // For testing purposes, allow admin flag in query string
    if (isset($_GET['admin']) && $_GET['admin'] === 'true') {
        return true;
    }
    
    return false;
}

// Route the request based on action and method
switch ($action) {
    case 'status':
        if ($method === 'GET') {
            // Get transaction hash from query
            $txHash = $_GET['hash'] ?? null;
            
            if (!$txHash) {
                sendJson(400, ['error' => 'Transaction hash is required']);
            }
            
            // Get transaction status
            $transaction = $txController->getTransaction($txHash);
            
            if (!$transaction) {
                sendJson(404, ['error' => 'Transaction not found']);
            }
            
            // Format response
            $response = [
                'success' => true,
                'transaction' => [
                    'hash' => $transaction['txHash'],
                    'status' => $transaction['status'],
                    'type' => $transaction['type'],
                    'createdAt' => $transaction['createdAt']->toDateTime()->format('Y-m-d H:i:s'),
                    'updatedAt' => $transaction['updatedAt']->toDateTime()->format('Y-m-d H:i:s')
                ]
            ];
            
            // Add additional details if available
            if (isset($transaction['amount'])) {
                $response['transaction']['amount'] = $transaction['amount'];
            }
            
            if (isset($transaction['stellarDetails'])) {
                $response['transaction']['stellarDetails'] = $transaction['stellarDetails'];
            }
            
            // Add status history if requested
            if (isset($_GET['history']) && $_GET['history'] === 'true') {
                $statusHistory = [];
                foreach ($transaction['statusHistory'] ?? [] as $history) {
                    $statusHistory[] = [
                        'status' => $history['status'],
                        'timestamp' => $history['timestamp']->toDateTime()->format('Y-m-d H:i:s'),
                        'details' => $history['details'] ?? ''
                    ];
                }
                $response['transaction']['statusHistory'] = $statusHistory;
            }
            
            sendJson(200, $response);
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'check':
        if ($method === 'POST') {
            // Require authentication for this action
            $userId = getUserId();
            if (!$userId) {
                sendJson(401, ['error' => 'Authentication required']);
            }
            
            // Get transaction hash from request body
            $data = getJsonBody();
            $txHash = $data['hash'] ?? $_POST['hash'] ?? null;
            
            if (!$txHash) {
                sendJson(400, ['error' => 'Transaction hash is required']);
            }
            
            // Check transaction status on blockchain
            $result = $txController->checkTransactionStatus($txHash);
            
            sendJson($result['success'] ? 200 : 400, $result);
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'create':
        if ($method === 'POST') {
            // Require authentication for this action
            $userId = getUserId();
            if (!$userId) {
                sendJson(401, ['error' => 'Authentication required']);
            }
            
            // Get transaction data from request body
            $data = getJsonBody();
            if (empty($data) && !empty($_POST)) {
                $data = $_POST;
            }
            
            // Validate required fields
            if (empty($data['txHash'])) {
                sendJson(400, ['error' => 'Transaction hash is required']);
            }
            
            if (empty($data['type'])) {
                sendJson(400, ['error' => 'Transaction type is required']);
            }
            
            // Add user ID to data if not provided
            if (empty($data['userId'])) {
                $data['userId'] = $userId;
            }
            
            // Create transaction record
            $result = $txController->createTransaction($data);
            
            sendJson($result['success'] ? 200 : 400, $result);
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'update':
        if ($method === 'POST') {
            // Require admin privileges for this action
            if (!isAdmin()) {
                sendJson(403, ['error' => 'Admin privileges required']);
            }
            
            // Get transaction data from request body
            $data = getJsonBody();
            if (empty($data) && !empty($_POST)) {
                $data = $_POST;
            }
            
            // Validate required fields
            if (empty($data['txHash'])) {
                sendJson(400, ['error' => 'Transaction hash is required']);
            }
            
            if (empty($data['status'])) {
                sendJson(400, ['error' => 'Status is required']);
            }
            
            // Update transaction status
            $result = $txController->updateTransactionStatus(
                $data['txHash'],
                $data['status'],
                $data['details'] ?? '',
                $data['additionalData'] ?? []
            );
            
            sendJson($result['success'] ? 200 : 400, $result);
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    case 'list':
        if ($method === 'GET') {
            // Require authentication for this action
            $userId = getUserId();
            if (!$userId) {
                sendJson(401, ['error' => 'Authentication required']);
            }
            
            // Set up pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            $options = [
                'page' => $page,
                'limit' => $limit
            ];
            
            // Determine which transactions to list
            $transactions = [];
            
            if (isset($_GET['status'])) {
                // List transactions by status
                $transactions = $txController->getTransactionsByStatus($_GET['status'], $options);
            } elseif (isset($_GET['campaign_id'])) {
                // List transactions for a campaign
                $transactions = $txController->getCampaignTransactions($_GET['campaign_id'], $options);
            } else {
                // List transactions for the user
                $transactions = $txController->getUserTransactions($userId, $options);
            }
            
            // Format response
            $formattedTransactions = [];
            foreach ($transactions as $tx) {
                $formattedTx = [
                    'id' => (string)$tx['_id'],
                    'hash' => $tx['txHash'],
                    'status' => $tx['status'],
                    'type' => $tx['type'],
                    'createdAt' => $tx['createdAt']->toDateTime()->format('Y-m-d H:i:s')
                ];
                
                if (isset($tx['amount'])) {
                    $formattedTx['amount'] = $tx['amount'];
                }
                
                if (isset($tx['stellarDetails'])) {
                    $formattedTx['stellarDetails'] = $tx['stellarDetails'];
                }
                
                $formattedTransactions[] = $formattedTx;
            }
            
            sendJson(200, [
                'success' => true,
                'transactions' => $formattedTransactions,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => count($transactions) // This is not accurate for total count, just a placeholder
                ]
            ]);
        } else {
            sendJson(405, ['error' => 'Method not allowed']);
        }
        break;
        
    default:
        sendJson(400, ['error' => 'Invalid action']);
        break;
} 