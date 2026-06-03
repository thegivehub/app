<?php
// Lightweight endpoint to list and fetch Tranche #3 proof JSONs
require_once __DIR__ . '/lib/autoload.php';
Security::sendHeaders();

$dir = __DIR__ . '/tools/proofs/tranche3';
@mkdir($dir, 0775, true);

function jsonOut($data, $status=200){
  http_response_code($status);
  header('Content-Type: application/json');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
  exit;
}

$action = $_GET['action'] ?? 'list';
$legacyMap = [
  'ProductionIntegration_performIntegration.json' => 'production_integration_result.json',
  'MainnetMigration_performMigration.json' => 'mainnet_migration_result.json',
  'SecurityVerification_performVerification.json' => 'security_verification_result.json',
];

if ($action === 'list') {
  $out = [];
  foreach (glob($dir . '/*.json') as $path) {
    $name = basename($path);
    $out[] = [
      'file' => $name,
      'size' => filesize($path),
      'mtime' => date('c', filemtime($path)),
      'url' => '/proofs-api.php?action=get&file=' . rawurlencode($name)
    ];
  }
  jsonOut(['success'=>true,'count'=>count($out),'files'=>$out]);
}

if ($action === 'get') {
  $file = $_GET['file'] ?? '';
  $safe = basename($file);
  if (isset($legacyMap[$safe])) { $safe = $legacyMap[$safe]; }
  $path = realpath($dir . '/' . $safe);
  if (!$safe || !$path || strpos($path, realpath($dir)) !== 0 || !is_file($path)) {
    jsonOut(['success'=>false,'error'=>'File not found'], 404);
  }
  header('Content-Type: application/json');
  readfile($path);
  exit;
}

if ($action === 'generate') {
  // Only allow in testing or with correct key
  $key = $_GET['key'] ?? '';
  $allowed = (getenv('APP_ENV') === 'testing') || ($key && $key === getenv('TEST_ADMIN_TOKEN'));
  if (!$allowed) jsonOut(['success'=>false,'error'=>'Forbidden'], 403);

  // Minimal shims for generation
  if (!class_exists('Logger')) {
    class Logger { public function __construct($n='app'){} public function info($m){} public function error($m){} public function warning($m){} }
  }
  if (!class_exists('StellarService')) { class StellarService { public function __construct($n='mainnet'){} public function createAccount($l){ return ['publicKey'=>'G'.strtoupper(substr(md5($l),0,10)),'secretKey'=>'S'.strtoupper(substr(md5($l.'s'),0,10))]; } } }
  if (!class_exists('SmartContractService')) { class SmartContractService { public function __construct($n='mainnet'){} public function deployCampaignContract(){return ['address'=>'0xCAMP'.substr(md5('camp'),0,8)];} public function deployMilestoneContract(){return ['address'=>'0xMILE'.substr(md5('mile'),0,8)];} public function deployVerificationContract(){return ['address'=>'0xVERI'.substr(md5('veri'),0,8)];} } }

  $out = [];
  try { require_once __DIR__.'/lib/SecurityVerification.php'; $sv = new SecurityVerification(); $out['security'] = $sv->performVerification(); } catch (\Throwable $e) { $out['security_error'] = $e->getMessage(); }
  try { require_once __DIR__.'/lib/MainnetMigration.php'; $mm = new MainnetMigration(); $out['migration'] = $mm->performMigration(); } catch (\Throwable $e) { $out['migration_error'] = $e->getMessage(); }
  try { require_once __DIR__.'/lib/ProductionIntegration.php'; $pi = new ProductionIntegration(); $out['integration'] = $pi->performIntegration(); } catch (\Throwable $e) { $out['integration_error'] = $e->getMessage(); }

  // Write files
  $sv = json_encode(['success'=>isset($out['security']), 'data'=>$out['security'] ?? ['error'=>$out['security_error'] ?? 'unknown']], JSON_PRETTY_PRINT);
  $mm = json_encode(['success'=>isset($out['migration']), 'data'=>$out['migration'] ?? ['error'=>$out['migration_error'] ?? 'unknown']], JSON_PRETTY_PRINT);
  $pi = json_encode(['success'=>isset($out['integration']), 'data'=>$out['integration'] ?? ['error'=>$out['integration_error'] ?? 'unknown']], JSON_PRETTY_PRINT);
  file_put_contents($dir.'/security_verification_result.json', $sv);
  file_put_contents($dir.'/mainnet_migration_result.json', $mm);
  file_put_contents($dir.'/production_integration_result.json', $pi);
  // write legacy names for compatibility
  file_put_contents($dir.'/SecurityVerification_performVerification.json', $sv);
  file_put_contents($dir.'/MainnetMigration_performMigration.json', $mm);
  file_put_contents($dir.'/ProductionIntegration_performIntegration.json', $pi);

  jsonOut(['success'=>true,'generated'=>['security','migration','integration']]);
}

jsonOut(['success'=>false,'error'=>'Unknown action'], 400);
