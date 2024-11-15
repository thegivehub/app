#!/usr/local/bin/php
<?php

array_shift($argv);

$collection = ucfirst(array_shift($argv));

require_once "lib/{$collection}.php";
$obj = new $collection();
print_r($obj);
// $results = $campaign->create($newCampaign);

//print_r($results);

if (count($argv)) {
    $out = [];
    // Read
    while ($id = array_shift($argv)) {
        $out[] = $obj->read($id);
    }
    //print_r($allCampaigns);
} else {
    $out = $obj->read();
}
print json_encode($out, JSON_PRETTY_PRINT);
// Update
//$campaignId = 'INSERT_CAMPAIGN_ID_HERE'; // replace with an actual campaign ID
//$campaign->update($campaignId, ['title' => 'Updated Campaign Title']);

// Delete
//$campaign->delete($campaignId);

