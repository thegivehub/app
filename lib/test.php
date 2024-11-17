<?php
require_once 'Campaign.php';

$campaign = new Campaign();

// Create
$newCampaign = [
    'title' => 'Clean Water Project',
    'description' => 'Providing clean water to rural areas',
    'location' => [
        'country' => 'Kenya',
        'region' => 'Nairobi',
        'coordinates' => [
            'latitude' => -1.286389,
            'longitude' => 36.817223
        ]
    ],
    'funding' => [
        'goalAmount' => 10000,
        'raisedAmount' => 0,
        'currency' => 'XLM'
    ]
];
$uid = $campaign->create($newCampaign);

// Read
$newitem = $campaign->read($uid);
print_r($newitem);

// Update
print "Updating title: ";
$campaign->update($uid, ['title' => 'Updated Campaign Title']);

// Verify update 
$updated = $campaign->read($uid);
if ($updated->title == 'Updated Campaign Title') {
    print "ok\n";
} else {
    print "ERROR\n";
    print "** Did not receive expected title. Update is broken.\n";
}
print_r($updated);

// Delete
print "Removing test campaign $uid\n";
$campaign->delete($uid);


