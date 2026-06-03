<?php
require_once __DIR__ . '/lib/autoload.php';
require_once __DIR__ . '/lib/Security.php';
Security::sendHeaders();

// Only accept POST/JSON; write minimal analytics log
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) { $data = [ 'raw' => substr($input,0,1024) ]; }

$event = $data['event'] ?? 'unknown';
$payload = $data['payload'] ?? [];
$line = json_encode([
    'ts' => date('c'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    'event' => $event,
    'payload' => $payload,
]);

// Append to logs/analytics.log
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
@file_put_contents($logDir . '/analytics.log', $line . PHP_EOL, FILE_APPEND);

header('Content-Type: application/json');
echo json_encode(['success'=> true]);

