<?php
// scripts/generate_donor_data.php
require(__DIR__.'/../vendor/autoload.php');
require_once __DIR__ . '/../lib/db.php';

class DonorDataGenerator {
    private $db;
    private $campaignIds;
    private $firstNames = ['Sarah', 'John', 'Maria', 'David', 'Emma', 'James', 'Lisa', 'Michael', 'Sofia', 'William'];
    private $lastNames = ['Chen', 'Smith', 'Garcia', 'Johnson', 'Brown', 'Davis', 'Miller', 'Wilson', 'Moore', 'Taylor'];
    private $cities = [
        'US' => ['San Francisco', 'New York', 'Seattle', 'Austin', 'Boston'],
        'UK' => ['London', 'Manchester', 'Edinburgh', 'Bristol', 'Birmingham'],
        'CA' => ['Toronto', 'Vancouver', 'Montreal', 'Calgary', 'Ottawa']
    ];
    private $countries = ['United States' => 'US', 'United Kingdom' => 'UK', 'Canada' => 'CA'];
    private $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'icloud.com', 'hotmail.com'];

    public function __construct() {
        $this->db = new Database();
        $this->campaignIds = $this->getCampaignIds();
    }

    private function getCampaignIds() {
        // Get actual campaign IDs from the database using our MongoCollection wrapper
        $campaignCollection = $this->db->getCollection('campaigns');
        $campaigns = $campaignCollection->find([], ['projection' => ['_id' => 1]]);
        
        // If no campaigns exist, create some sample campaign IDs
        if (empty($campaigns)) {
            return [
                new MongoDB\BSON\ObjectId(),
                new MongoDB\BSON\ObjectId(),
                new MongoDB\BSON\ObjectId()
            ];
        }
        
        return array_map(function($campaign) {
            return $campaign['_id'];
        }, $campaigns);
    }

    public function generateDonors($count = 100) {
        $donors = [];
        $startDate = strtotime('-1 year');
        $now = time();

        for ($i = 0; $i < $count; $i++) {
            $firstName = $this->firstNames[array_rand($this->firstNames)];
            $lastName = $this->lastNames[array_rand($this->lastNames)];
            $email = strtolower($firstName . '.' . $lastName . '@' . $this->domains[array_rand($this->domains)]);

            $isRecurring = (rand(0, 100) < 30); // 30% chance of recurring donor
            $createdDate = rand($startDate, $now);
            $country = array_rand($this->countries);
            $countryCode = $this->countries[$country];
            
            $donationHistory = [];
            $totalDonated = 0;
            $lastDonationDate = null;
            
            // Generate donation history
            if ($isRecurring) {
                $monthlyAmount = rand(50, 500);
                $startMonth = date('Y-m', $createdDate);
                $currentMonth = date('Y-m');
                
                while (strtotime($startMonth) <= strtotime($currentMonth)) {
                    $donationDate = strtotime($startMonth . '-15');
                    $donationHistory[] = [
                        'amount' => $monthlyAmount,
                        'date' => new MongoDB\BSON\UTCDateTime($donationDate * 1000),
                        'campaignId' => $this->campaignIds[array_rand($this->campaignIds)],
                        'recurring' => true
                    ];
                    $totalDonated += $monthlyAmount;
                    $lastDonationDate = $donationDate;
                    $startMonth = date('Y-m', strtotime($startMonth . ' +1 month'));
                }
            } else {
                $numDonations = rand(1, 5);
                for ($j = 0; $j < $numDonations; $j++) {
                    $donationDate = rand($createdDate, $now);
                    $amount = rand(25, 1000);
                    $donationHistory[] = [
                        'amount' => $amount,
                        'date' => new MongoDB\BSON\UTCDateTime($donationDate * 1000),
                        'campaignId' => $this->campaignIds[array_rand($this->campaignIds)],
                        'recurring' => false
                    ];
                    $totalDonated += $amount;
                    $lastDonationDate = max($lastDonationDate, $donationDate);
                }
            }

            // Sort donation history by date
            usort($donationHistory, function($a, $b) {
                return $a['date']->toDateTime() <=> $b['date']->toDateTime();
            });

            $donor = [
                'email' => $email,
                'name' => $firstName . ' ' . $lastName,
                'status' => (time() - $lastDonationDate < 60*60*24*90) ? 'active' : 'inactive', // Inactive if no donation in 90 days
                'donationType' => $isRecurring ? 'recurring' : 'one-time',
                'totalDonated' => $totalDonated,
                'lastDonation' => new MongoDB\BSON\UTCDateTime($lastDonationDate * 1000),
                'donationHistory' => $donationHistory,
                'location' => [
                    'country' => $country,
                    'city' => $this->cities[$countryCode][array_rand($this->cities[$countryCode])]
                ],
                'preferences' => [
                    'newsletter' => (bool)rand(0, 1),
                    'notifications' => (bool)rand(0, 1),
                    'anonymousDonations' => (bool)rand(0, 1)
                ],
                'created' => new MongoDB\BSON\UTCDateTime($createdDate * 1000),
                'lastActive' => new MongoDB\BSON\UTCDateTime($lastDonationDate * 1000)
            ];

            if ($isRecurring) {
                $donor['recurringDetails'] = [
                    'amount' => $monthlyAmount,
                    'frequency' => 'monthly',
                    'startDate' => new MongoDB\BSON\UTCDateTime($createdDate * 1000),
                    'nextDonation' => new MongoDB\BSON\UTCDateTime(strtotime('+1 month', $lastDonationDate) * 1000),
                    'status' => 'active'
                ];
            }

            $donors[] = $donor;
        }

        return $donors;
    }

    public function insertDonors($donors) {
        try {
            $donorCollection = $this->db->getCollection('donors');
            foreach ($donors as $donor) {
                $donorCollection->insertOne($donor);
            }
            return count($donors);
        } catch (Exception $e) {
            echo "Error inserting donors: " . $e->getMessage() . "\n";
            return 0;
        }
    }
}

// Run the generator
try {
    echo "Starting donor data generation...\n";
    
    $generator = new DonorDataGenerator();
    $donors = $generator->generateDonors(100); // Generate 100 donors
    
    echo "Generated " . count($donors) . " donor records\n";
    
    $inserted = $generator->insertDonors($donors);
    
    echo "Successfully inserted " . $inserted . " donors into the database\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
