#!/usr/local/bin/php
<?php
require_once 'lib/Campaign.php';

array_shift($argv);

$campaign = new Campaign();

// $results = $campaign->create($newCampaign);

//print_r($results);

if (count($argv)) {
    $out = [];
    // Read
    while ($id = array_shift($argv)) {
        $out[] = $campaign->read($id);
    }
    //print_r($allCampaigns);
} else {
    $out = $campaign->read();
}
print json_encode($out);
// Update
//$campaignId = 'INSERT_CAMPAIGN_ID_HERE'; // replace with an actual campaign ID
//$campaign->update($campaignId, ['title' => 'Updated Campaign Title']);

// Delete
//$campaign->delete($campaignId);

