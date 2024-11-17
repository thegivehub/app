#!/usr/local/bin/php
<?php

// Autoloader function to load the required class file based on the endpoint
spl_autoload_register(function ($className) {
    $filePath = __DIR__ . "/lib/$className.php";
    if (file_exists($filePath)) {
        require_once $filePath;
    } else {
        try {
            $myclass = file_get_contents("lib/Collection.php");
            $myclass = preg_replace("/\%\%(.+?)\%\%/", $className, $myclass);
            
            eval($myclass);
            $test = new $className();

            if (!$test) {
                throw new Exception('Invalid collection');
            }
        } catch(e) {
            http_response_code(404);
            // Set headers
            header('Access-Control-Allow-Origin: *');
            header('Content-Type: application/json');
            echo json_encode(["error" => "Class $className not found"]);
            exit;
        }
    }
});

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

