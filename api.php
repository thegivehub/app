<?php
require 'vendor/autoload.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/KycController.php';
require_once __DIR__ . '/lib/AdminAuthController.php';
require_once __DIR__ . '/lib/AdminCampaignController.php';
require_once __DIR__ . '/lib/AdminUserController.php';
require_once __DIR__ . '/lib/AdminDashboardController.php';
require_once __DIR__ . '/lib/AdminReportsController.php';


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
if ($endpoint === 'kyc' || (isset($pathParts) && $pathParts[0] === 'kyc')) {
    $kycController = new KycController();
    
    // Extract the action from the URL path
    $action = $pathParts[1] ?? null;
    
    switch ($action) {
        case 'initiate':
            if ($method === 'POST') {
                $kycController->initiateVerification();
                exit;
            }
            break;
            
        case 'webhook':
            if ($method === 'POST') {
                $kycController->handleWebhook();
                exit;
            }
            break;
            
        case 'status':
            if ($method === 'GET') {
                $kycController->getVerificationStatus();
                exit;
            }
            break;
            
        case 'admin-override':
            if ($method === 'POST') {
                $kycController->adminOverride();
                exit;
            }
            break;
            
        case 'report':
            if ($method === 'GET') {
                $kycController->generateReport();
                exit;
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'KYC endpoint not found']);
            exit;
    }
    
    // If we get here, method not allowed
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed for this KYC endpoint']);
    exit;
}

// Admin-specific endpoints
if (isset($pathParts) && $pathParts[0] === 'admin') {
    $adminAuthController = new AdminAuthController();
    
    // Handle admin authentication
    if (isset($pathParts[1])) {
        if ($pathParts[1] === 'login') {
            $adminAuthController->handleLogin();
            exit;
        } else if ($pathParts[1] === 'verify') {
            $adminAuthController->handleVerify();
            exit;
        } else if ($pathParts[1] === 'campaigns') {
            $adminCampaignController = new AdminCampaignController();
            $adminCampaignController->handleRequest();
            exit;
        } else if ($pathParts[1] === 'users') {
            $adminUserController = new AdminUserController();
            $adminUserController->handleRequest();
            exit;
        } else if ($pathParts[1] === 'dashboard') {
            $adminDashboardController = new AdminDashboardController();
            $adminDashboardController->handleRequest();
            exit;
        } else if ($pathParts[1] === 'reports') {
            // New endpoint for reports
            $adminReportsController = new AdminReportsController();
            $adminReportsController->handleRequest();
            exit;
        }
    }
    
    // If we reach here, it's an unknown admin endpoint
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['error' => 'Unknown admin endpoint']);
    exit;
}

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

if ($endpoint === 'Campaign') {
    logMessage("Campaign endpoint requested", [
        'action' => $pathParts[1] ?? 'default',
        'method' => $method,
        'userId' => isset($instance) ? $instance->getUserIdFromToken() : null
    ]);
    if (isset($pathParts) && count($pathParts) > 1) {
        switch ($pathParts[1]) {
            case 'my':
                if ($method === 'GET') {
                    $result = $instance->getMyCampaigns();
                    sendAPIJson(200, $result);
                }
                break;

            case 'featured':
                if ($method === 'GET') {
                    $result = $instance->getFeaturedCampaigns();
                    sendAPIJson(200, $result);
                }
                break;

            case 'category':
                if ($method === 'GET' && isset($pathParts[2])) {
                    $category = $pathParts[2];
                    $result = $instance->getCampaignsByCategory($category);
                    sendAPIJson(200, $result);
                }
                break;

            case 'search':
                if ($method === 'GET' && isset($_GET['q'])) {
                    $query = $_GET['q'];
                    $result = $instance->searchCampaigns($query);
                    sendAPIJson(200, $result);
                }
                break;
        }
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
logMessage("API Request", [
    'endpoint' => $endpoint,
    'method' => $method,
    'pathParts' => $pathParts ?? null,
    'id' => $id ?? null,
    'headers' => getallheaders(),
]);

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

