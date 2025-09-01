<?php

require("lib/db.php");

$db = new Database();
$db->users = $db->getCollection('users');
print_r($db);

$results = $db->users->find();
print_r($results);
