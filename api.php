<?php
require 'vendor/autoload.php';
require_once __DIR__ . '/lib/db.php';

/**
 * Sends a JSON response and exits
 * @param int $code HTTP response code
 * @param mixed $data Data to be JSON encoded
 * @param bool $exit Whether to exit after sending (default: true)
 */
function sendAPIJson($code, $data, $exit = true) {
    http_response_code($code);
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($exit) {
        exit;
    }
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
    
    // You can adjust the log path as needed
    $logFile = __DIR__ . "/logs/" . date('Y-m-d') . ".log";
    
    // Ensure logs directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    error_log($logEntry, 3, $logFile);
}

// Autoloader function to load the required class file based on the endpoint
spl_autoload_register(function ($className) {
    $filePath = __DIR__ . "/lib/$className.php";
    if (file_exists($filePath)) {
        require_once $filePath;
    } else if (preg_match('/^[A-Za-z0-9_]+$/', $className)) { // Only allow alphanumeric collection names
        // Check if this might be a dynamic collection
        $baseCollectionPath = __DIR__ . "/lib/Collection.php";
        if (file_exists($baseCollectionPath)) {
            require_once $baseCollectionPath;
            // Create the dynamic collection class that extends the base Collection
            $classDefinition = "
                class {$className} extends Collection {
                    protected \$collectionName = '{$className}';
                }
            ";
            eval($classDefinition); // This eval is much safer as it only contains the class name which we validated
        } else {
            logMessage("Base Collection class not found", ['path' => $baseCollectionPath], 'error');
            sendAPIJson(500, ["error" => "System configuration error"]);
        }
    } else {
        logMessage("Invalid collection name", ['className' => $className], 'error');
        sendAPIJson(400, ["error" => "Invalid collection name"]);
    }
});
session_start();

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$actions = [];

if (isset($_SERVER['PATH_INFO'])) {
    $actions = preg_split("/\//", $_SERVER['PATH_INFO']);
    array_shift($actions);
    $endpoint = ucfirst(array_shift($actions));
}

$id = $_GET['id'] ?? null;

$posted = json_decode(file_get_contents('php://input'), true);

// Set headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Instantiate the required class dynamically
$instance = new $endpoint();

// Verify that the endpoint class exists
if (!$endpoint || !class_exists($endpoint)) {
    logMessage("Invalid endpoint", ['endpoint' => $endpoint], 'error');
    sendAPIJson(400, ["error" => "Invalid endpoint"]);
}
$pathParts = preg_split("/\//", $_SERVER['PATH_INFO']);
array_shift($pathParts);

if ($endpoint === 'User' && isset($pathParts) && count($pathParts) > 0) {
        switch ($pathParts[1]) {
            case 'profile':
                if ($method === 'PUT') {
                    $result = $instance->updateProfile($instance->getUserIdFromToken(), $posted);
                    sendAPIJson(200, $result);
                }
                break;
            case 'me':
                if ($method === 'PUT') {
                    $result = $instance->updateProfile($instance->getUserIdFromToken(), $posted);
                    sendAPIJson(200, $result);
                }
                
                if ($method === 'GET') {
                    $result = $instance->me();
                    sendAPIJson(200, $result);
                }
                break;
        }
}
/*
if ($endpoint === 'Campaign') {
    if (isset($pathParts) && count($pathParts) > 0) {
        switch ($pathParts[1]) {
            case 'my':
                if ($method === 'GET') {
                    $result = $instance->getMyCampaigns();
                    sendAPIJson(200, $result);
                }
                break;
        }
    }
}
 */

if (count($actions)) {
    foreach ($actions as $action) {
        $action = preg_replace_callback("/\-(\w)/", function($m) {
            return strtoupper($m[1]);
        }, $action);
        
        switch ($action) {
            case 'register':
                $result = $instance->register($posted);
                echo json_encode($result);
                exit;
            case 'send-verification':
                $result = $instance->sendVerification($posted);
                echo json_encode($result);
                exit;
            case 'verify-code':
                $result = $instance->verifyCode($posted);
                echo json_encode($result);
                exit;
            default:
                if (method_exists($instance, $action)) {
                    $out = $instance->$action($posted);
                    print json_encode($out);
                    exit;
                }
        }
    }
}

// Handle CRUD operations based on HTTP method
switch ($method) {
    case 'POST':
        $data = $posted;
        if ($data) {
            $result = $instance->create($data);
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Invalid data"]);
        }
        break;

    case 'GET':
        // Read
        if ($id) {
            $result = $instance->read($id);
        } else {
            $result = $instance->read();
        }
        echo json_encode($result);
        break;

    case 'PUT':
        // Update
        if ($id) {
            $data = json_decode(file_get_contents('php://input'), true);
            if ($data) {
                $result = $instance->update($id, $data);
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode(["error" => "Invalid data"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["error" => "ID required"]);
        }
        break;

    case 'DELETE':
        // Delete
        if ($id) {
            $result = $instance->delete($id);
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(["error" => "ID required"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
        break;
}

