<?php
require 'vendor/autoload.php';

try {
    $client = new MongoDB\Client("mongodb://mongodb:27017");
    $dbs = $client->listDatabases();
    echo "Connected to MongoDB. Databases:\n";
    foreach ($dbs as $db) {
        echo "- " . $db->getName() . "\n";
    }
    exit(0);
} catch (Exception $e) {
    echo "MongoDB connection error: " . $e->getMessage() . "\n";
    exit(1);
}

