#!/usr/local/bin/php
<?php
require 'vendor/autoload.php';
require_once __DIR__ . '/lib/db.php';
require(__DIR__."/lib/autoload.php");

array_shift($argv);

$collection = ucfirst(array_shift($argv));
print $collection."\n";

if (file_exists("lib/{$collection}.php")) {
    include __DIR__."/lib/{$collection}.php";
}
$obj = new $collection();
// $results = $campaign->create($newCampaign);

//print_r($results);

if (count($argv)) {
    $out = [];
    // Read
    while ($id = array_shift($argv)) {
        $p = preg_split("/=/", $id, 2);
        if (count($p)) {
            $id = $obj->findId([$p[0]=>$p[1]]);

            print $id."\n";

            $out = $obj->get($id);
        } else {
            $out  = [];
        } 
    }
    //print_r($allCampaigns);
} else {
    $out = $obj->read();
}

print_r($obj);
print json_encode($out, JSON_PRETTY_PRINT);
// Update
//$campaignId = 'INSERT_CAMPAIGN_ID_HERE'; // replace with an actual campaign ID
//$campaign->update($campaignId, ['title' => 'Updated Campaign Title']);

// Delete
//$campaign->delete($campaignId);

