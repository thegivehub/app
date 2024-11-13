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
$campaign->create($newCampaign);

// Read
$allCampaigns = $campaign->read();
print_r($allCampaigns);

// Update
//$campaignId = 'INSERT_CAMPAIGN_ID_HERE'; // replace with an actual campaign ID
//$campaign->update($campaignId, ['title' => 'Updated Campaign Title']);

// Delete
//$campaign->delete($campaignId);

