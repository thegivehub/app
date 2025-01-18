<?php
// lib/Donor.php
require_once 'Model.php';

class Donor extends Model {
    protected $collection;

    public function __construct() {
        parent::__construct();
        $this->collection = $this->db->getCollection('donors');
    }

    public function getStats() {
        $now = new MongoDB\BSON\UTCDateTime();
        $monthAgo = new MongoDB\BSON\UTCDateTime(strtotime('-1 month') * 1000);
        $twoMonthsAgo = new MongoDB\BSON\UTCDateTime(strtotime('-2 months') * 1000);

        // Get current period stats
        $currentStats = $this->collection->aggregate([
            [
                '$facet' => [
                    'totalDonors' => [
                        ['$match' => ['created' => ['$lte' => $now]]],
                        ['$count' => 'count']
                    ],
                    'activeDonors' => [
                        ['$match' => [
                            'status' => 'active',
                            'lastDonation' => ['$gte' => $monthAgo]
                        ]],
                        ['$count' => 'count']
                    ],
                    'totalDonations' => [
                        ['$group' => [
                            '_id' => null,
                            'total' => ['$sum' => '$totalDonated']
                        ]]
                    ],
                    'recurringDonors' => [
                        ['$match' => [
                            'donationType' => 'recurring',
                            'recurringDetails.status' => 'active'
                        ]],
                        ['$count' => 'count']
                    ]
                ]
            ]
        ])->toArray();

        // Get previous period stats for comparison
        $previousStats = $this->collection->aggregate([
            [
                '$facet' => [
                    'totalDonors' => [
                        ['$match' => ['created' => ['$lte' => $monthAgo]]],
                        ['$count' => 'count']
                    ],
                    'activeDonors' => [
                        ['$match' => [
                            'status' => 'active',
                            'lastDonation' => ['$gte' => $twoMonthsAgo, '$lt' => $monthAgo]
                        ]],
                        ['$count' => 'count']
                    ],
                    'totalDonations' => [
                        ['$match' => ['lastDonation' => ['$lt' => $monthAgo]]],
                        ['$group' => [
                            '_id' => null,
                            'total' => ['$sum' => '$totalDonated']
                        ]]
                    ]
                ]
            ]
        ])->toArray();

        // Calculate trends
        $current = $currentStats[0];
        $previous = $previousStats[0];

        $totalDonors = $current['totalDonors'][0]['count'] ?? 0;
        $prevTotalDonors = $previous['totalDonors'][0]['count'] ?? 0;
        $donorsTrend = $prevTotalDonors ? round(($totalDonors - $prevTotalDonors) / $prevTotalDonors * 100, 1) : 0;

        $totalDonations = $current['totalDonations'][0]['total'] ?? 0;
        $prevTotalDonations = $previous['totalDonations'][0]['total'] ?? 0;
        $donationsTrend = $prevTotalDonations ? round(($totalDonations - $prevTotalDonations) / $prevTotalDonations * 100, 1) : 0;

        $activeDonors = $current['activeDonors'][0]['count'] ?? 0;
        $prevActiveDonors = $previous['activeDonors'][0]['count'] ?? 0;
        $retentionRate = $prevActiveDonors ? round(($activeDonors / $prevActiveDonors) * 100, 1) : 0;

        $averageDonation = $totalDonors ? round($totalDonations / $totalDonors, 2) : 0;
        $prevAverageDonation = $prevTotalDonors ? round($prevTotalDonations / $prevTotalDonors, 2) : 0;
        $avgDonationTrend = $prevAverageDonation ? round(($averageDonation - $prevAverageDonation) / $prevAverageDonation * 100, 1) : 0;

        return [
            'totalDonors' => $totalDonors,
            'totalDonations' => $totalDonations,
            'averageDonation' => $averageDonation,
            'retentionRate' => $retentionRate,
            'donorsTrend' => $donorsTrend,
            'donationsTrend' => $donationsTrend,
            'avgDonationTrend' => $avgDonationTrend,
            'retentionTrend' => $retentionRate - ($prevActiveDonors ? 100 : 0),
            'recurringDonors' => $current['recurringDonors'][0]['count'] ?? 0
        ];
    }

    public function search($query, $filter = 'all', $page = 1, $limit = 10) {
        $match = [];

        // Apply search query
        if ($query) {
            $match['$or'] = [
                ['name' => ['$regex' => $query, '$options' => 'i']],
                ['email' => ['$regex' => $query, '$options' => 'i']]
            ];
        }

        // Apply filters
        switch ($filter) {
            case 'recurring':
                $match['donationType'] = 'recurring';
                $match['recurringDetails.status'] = 'active';
                break;
            case 'one-time':
                $match['donationType'] = 'one-time';
                break;
            case 'inactive':
                $match['status'] = 'inactive';
                break;
        }

        $pipeline = [
            ['$match' => $match],
            ['$sort' => ['lastDonation' => -1]],
            ['$skip' => ($page - 1) * $limit],
            ['$limit' => $limit]
        ];

        $donors = $this->collection->aggregate($pipeline)->toArray();
        $total = $this->collection->count($match);

        return [
            'donors' => $donors,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ];
    }

    public function getDonorDetails($id) {
        $donor = $this->collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
        if (!$donor) {
            throw new Exception('Donor not found');
        }

        // Enrich with additional data like campaign details
        foreach ($donor['donationHistory'] as &$donation) {
            $campaign = $this->db->campaigns->findOne(['_id' => $donation['campaignId']]);
            $donation['campaignName'] = $campaign ? $campaign['title'] : 'Unknown Campaign';
        }

        return $donor;
    }

    public function updateDonorPreferences($id, $preferences) {
        return $this->collection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($id)],
            ['$set' => ['preferences' => $preferences]]
        );
    }

    public function updateRecurringStatus($id, $status) {
        return $this->collection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($id)],
            ['$set' => [
                'recurringDetails.status' => $status,
                'lastActive' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
    }
}
