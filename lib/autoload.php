<?php

/**
 * Sends a JSON response and exits
 * @param int $code HTTP response code
 * @param mixed $data Data to be JSON encoded
 * @param bool $exit Whether to exit after sending (default: true)
 */
function sendJson($code, $data, $exit = true) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($exit) {
        exit;
    }
}

if (!function_exists("logMessage")) {
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
        $logFile = "logs/" . date('Y-m-d') . ".log";
        
        // Ensure logs directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        error_log($logEntry, 3, $logFile);
    }
}

// Require Composer's autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Handle Stellar SDK namespaces if needed
if (!class_exists('Soneso\StellarSDK\Crypto\KeyPair')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Define the core classes that should be loaded explicitly
$coreClasses = [
    'Auth', 'Campaign', 'Collection', 'Donation', 'DonationProcessor', 'Document', 
    'DocumentUploader', 'Donor', 'Impactmetrics', 'Mailer', 'Notification', 
    'Organization', 'Transaction', 'TransactionProcessor', 'Update', 'User', 
    'Verify', 'Wallet', 'Wallets'
];

// Preload core classes
foreach ($coreClasses as $class) {
    $filePath = __DIR__ . "/$class.php";
    if (file_exists($filePath)) {
        require_once $filePath;
    }
}

// Autoloader function to load the required class file based on the endpoint
spl_autoload_register(function ($className) {
    if (class_exists($className)) return;
    
    // Handle namespaces
    $parts = explode('\\', $className);
    $className = end($parts);
    
    $filePath = __DIR__."/$className.php";
    if (file_exists($filePath)) {
        require_once $filePath;
    } else if (preg_match('/^[A-Za-z0-9]+$/', $className)) { // Only allow alphanumeric collection names
        // Check if this might be a dynamic collection
        $baseCollectionPath = __DIR__ ."/Collection.php";
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
            sendJson(500, ["error" => "System configuration error"]);
        }
    } else {
        logMessage("Invalid collection name", ['className' => $className], 'error');
        sendJson(400, ["error" => "Invalid collection name:" . $className]);
    }
});


