<?php
require 'vendor/autoload.php';
require_once __DIR__ . '/lib/db.php';

// Autoloader function to load the required class file based on the endpoint
spl_autoload_register(function ($className) {
    $filePath = __DIR__ . "/lib/$className.php";
    if (file_exists($filePath)) {
        require_once $filePath;
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Class $className not found"]);
        exit;
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

// Verify that the endpoint class exists
if (!$endpoint || !class_exists($endpoint)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid endpoint"]);
    exit;
}

// Instantiate the required class dynamically
$instance = new $endpoint();

if (count($actions)) {
    foreach ($actions as $action) {
        $action = preg_replace_callback("/\-(\w)/", function($m) {
            return strtoupper($m[1]);
        }, $action);
        $out = $instance->$action($posted);
        print json_encode($out);
        exit;
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

