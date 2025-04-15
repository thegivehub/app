<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/tests/bootstrap.php';

$command = './vendor/bin/phpunit';
$arguments = [
    '--colors=always',
    '--testdox',
    '--stop-on-failure',
    'tests'
];

$command .= ' ' . implode(' ', array_map('escapeshellarg', $arguments));

passthru($command, $returnCode);
exit($returnCode); 