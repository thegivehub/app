<?php
require 'vendor/autoload.php';
require_once __DIR__ . '/lib/db.php';

/**
 * GiveHub API Router
 * 
 * Simple REST API router that maps URL paths directly to class methods:
 * /api/{class}/{method}/{id}
 * 
 * Classes are autoloaded from lib/ directory and can extend the base Collection
 * class for custom functionality beyond basic CRUD operations.
 */

/**
 * Sends a JSON response and exits
 * @param int $code HTTP response code
 * @param mixed $data Data to be JSON encoded
 */
function sendAPIJson($code, $data) {
    http_response_code($code);
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Logs message with timestamp and optional context
 * @param string $message Log message
 * @param array $context Additional context data
 * @param string $level Log level (debug, info, error, etc)
 */
function logMessage($message, array $context = [], $level = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    $contextJson = empty($context) ? '' : json_encode($context);
    $logEntry = "[{$timestamp}] [{$level}] {$message} {$contextJson}\n";
    
    $logFile = __DIR__ . "/logs/" . date('Y-m-d') . ".log";
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    error_log($logEntry, 3, $logFile);
}

// Initialize session
session_start();

// Set standard headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

// Register autoloader for API classes
spl_autoload_register(function ($className) {
    $filePath = __DIR__ . "/lib/$className.php";
    if (file_exists($filePath)) {
        require_once $filePath;
    } else if (preg_match('/^[A-Za-z0-9_]+$/', $className)) {
        // If no custom class exists, use base Collection class
        $baseCollectionPath = __DIR__ . "/lib/Collection.php";
        if (file_exists($baseCollectionPath)) {
            require_once $baseCollectionPath;
            // Create dynamic collection class
            eval("class {$className} extends Collection { protected \$collectionName = '{$className}'; }");
        } else {
            logMessage("Base Collection class not found", ['path' => $baseCollectionPath], 'error');
            sendAPIJson(500, ["error" => "System configuration error"]);
        }
    } else {
        logMessage("Invalid class name", ['className' => $className], 'error');
        sendAPIJson(400, ["error" => "Invalid resource name"]);
    }
});

// Get request details
$method = $_SERVER['REQUEST_METHOD'];
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$parts = array_values(array_filter(explode('/', $pathInfo)));

// Get request body for POST/PUT methods
$requestBody = null;
if (in_array($method, ['POST', 'PUT'])) {
    $requestBody = json_decode(file_get_contents('php://input'), true);
}

// Get ID from either path or query string, preferring path
function getRequestedId($parts) {
    // Check path parameter first (parts[1] if it exists and is numeric)
    if (isset($parts[1]) && is_numeric($parts[1])) {
        return $parts[1];
    }
    // Fall back to query string parameter if it exists
    return isset($_GET['id']) ? $_GET['id'] : null;
}

try {
    // No path info - return API info
    if (empty($parts)) {
        sendAPIJson(200, [
            'name' => 'GiveHub API',
            'version' => '1.0',
            'status' => 'online'
        ]);
    }

    // First part is always the class name
    $className = ucfirst($parts[0]);
    
    // Validate and instantiate class
    if (!preg_match('/^[A-Za-z0-9_]+$/', $className)) {
        sendAPIJson(400, ["error" => "Invalid resource name"]);
    }
    
    $instance = new $className();
    
    // Handle standard CRUD operations if no method specified
    if (count($parts) === 1 || (count($parts) === 2 && is_numeric($parts[1]))) {
        $id = getRequestedId($parts);
        
        switch ($method) {
            case 'GET':
                $result = $instance->read($id);
                sendAPIJson(200, $result);
                break;
                
            case 'POST':
                if (!$requestBody) {
                    sendAPIJson(400, ["error" => "Invalid or missing data"]);
                }
                $result = $instance->create($requestBody);
                sendAPIJson(201, $result);
                break;
                
            case 'PUT':
                if (!$id || !$requestBody) {
                    sendAPIJson(400, ["error" => "ID and data required for update"]);
                }
                $result = $instance->update($id, $requestBody);
                sendAPIJson(200, $result);
                break;
                
            case 'DELETE':
                if (!$id) {
                    sendAPIJson(400, ["error" => "ID required for delete"]);
                }
                $result = $instance->delete($id);
                sendAPIJson(200, $result);
                break;
        }
    }
    
    // Handle custom method call
    $method = $parts[1];
    $param = $parts[2] ?? null;
    
    if (!method_exists($instance, $method)) {
        sendAPIJson(404, ["error" => "Method not found"]);
    }
    
    // Call the method with parameter and/or request body
    $result = $instance->$method($param, $requestBody);
    sendAPIJson(200, $result);
    
} catch (Exception $e) {
    logMessage("Error processing request", [
        'error' => $e->getMessage(),
        'path' => $pathInfo,
        'method' => $method
    ], 'error');
    sendAPIJson(500, ["error" => "Server error: " . $e->getMessage()]);
}
