<?php
/**
 * demo-data-generator.php
 * 
 * This script generates realistic demo data for the GiveHub platform.
 * It creates sample users and campaigns with funding data to demonstrate
 * the admin dashboard functionality.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/db.php';

class DemoDataGenerator {
    private $db;
    private $usersCollection;
    private $campaignsCollection;
    
    // Sample campaign categories
    private $categories = [
        'Education', 'Healthcare', 'Environment', 'Arts', 
        'Community', 'Technology', 'Sports', 'Animals', 
        'Disaster Relief', 'Social Enterprise'
    ];
    
    // Sample campaign titles by category
    private $campaignTitleTemplates = [
        'Education' => [
            '{location} School Library Fund',
            'Technology for {location} Classrooms',
            'Scholarship Fund for {location} Students', 
            'STEM Program for Underprivileged Youth',
            'New Playground for {location} Elementary',
            'Educational Trip to {destination}',
            'After-School Program for {location}',
            'Build a School in {location}'
        ],
        'Healthcare' => [
            'Medical Relief for {location}',
            '{name}\'s Cancer Treatment Fund',
            'Mental Health Center in {location}',
            'Medical Equipment for {location} Clinic',
            'Healthcare Access for {location} Community',
            'Vaccination Drive in {location}',
            'Mobile Health Unit for Rural Areas',
            '{location} Children\'s Hospital Support'
        ],
        'Environment' => [
            '{location} Beach Cleanup',
            'Save the {location} Forest',
            'Renewable Energy for {location}',
            'Community Garden in {location}',
            'Plastic-Free {location} Initiative',
            'Tree Planting in {location}',
            '{location} River Conservation',
            'Sustainable Farming in {location}'
        ],
        'Arts' => [
            '{location} Community Theater',
            'Art Classes for {location} Youth',
            'Restore Historic {location} Museum',
            'Public Mural in {location}',
            '{location} Music Festival',
            'Documentary about {location}',
            'Artist Residency in {location}',
            '{location} Cultural Heritage Project'
        ],
        'Community' => [
            '{location} Community Center Renovation',
            'Food Bank for {location}',
            'Homeless Shelter in {location}',
            '{location} Neighborhood Watch',
            'Youth Center for {location}',
            'Senior Support in {location}',
            'Public Space Improvement in {location}',
            '{location} Community Events Fund'
        ],
        'Technology' => [
            'Coding Bootcamp for {location} Youth',
            'Tech Incubator in {location}',
            'Digital Literacy for {location} Seniors',
            'Tech Devices for {location} Schools',
            'Innovation Lab for {location}',
            '{location} Public Wifi Project',
            'Robotics Club for {location} Students',
            'Virtual Reality Education for {location}'
        ],
        'Sports' => [
            '{location} Youth Sports League',
            'Sports Equipment for {location} Schools',
            '{location} Community Pool Renovation',
            'Paralympics Training in {location}',
            'Sports Facility for {location}',
            '{location} Team Tournament Fund',
            'Athletic Scholarships for {location} Youth',
            'Coaching Program in {location}'
        ],
        'Animals' => [
            '{location} Animal Shelter Support',
            'Wildlife Conservation in {location}',
            'Rescue Center for {location} Strays',
            'Veterinary Care for {location} Pets',
            'Animal Rehabilitation in {location}',
            '{location} Zoo Improvements',
            'Endangered Species Protection in {location}',
            'Spay & Neuter Program for {location}'
        ],
        'Disaster Relief' => [
            '{location} Flood Relief',
            'Hurricane Recovery for {location}',
            'Fire Victims Support in {location}',
            'Earthquake Relief for {location}',
            'Disaster Preparedness for {location}',
            'Emergency Shelter in {location}',
            '{location} Storm Damage Fund',
            'Rebuild {location} After Disaster'
        ],
        'Social Enterprise' => [
            '{location} Women\'s Cooperative',
            'Microloans for {location} Entrepreneurs',
            'Fair Trade Market in {location}',
            'Job Training for {location} Youth',
            'Sustainable Business in {location}',
            '{location} Artisan Support',
            'Social Impact Startups in {location}',
            'Workforce Development for {location}'
        ]
    ];
    
    // Sample campaign descriptions
    private $campaignDescriptions = [
        'Education' => [
            "Help us bring quality education resources to students in {location}. Our goal is to create a vibrant learning environment where every child has the opportunity to succeed. Your contribution will directly fund new books, technology, and educational materials.",
            "The students of {location} need your support! We're raising funds to enhance their learning experience with better classrooms, modern technology, and expanded educational programs that will prepare them for future success.",
            "Education is the foundation of opportunity. Join our mission to improve educational facilities in {location} and give children the resources they need to thrive academically and personally."
        ],
        'Healthcare' => [
            "Your donation will help provide essential medical services to the underserved community in {location}. We aim to ensure everyone has access to quality healthcare regardless of their financial situation.",
            "Help us bring vital medical treatments and preventive care to {location}. Every contribution brings us closer to a healthier community where everyone can access the care they need when they need it most.",
            "The people of {location} face significant healthcare challenges. Your support will fund medical supplies, staff training, and facility improvements to deliver better care to those who need it most."
        ],
        'Environment' => [
            "Join our effort to protect the natural beauty of {location} for future generations. Your contribution will directly fund conservation efforts, cleanup initiatives, and environmental education programs.",
            "Climate change threatens our community in {location}. Help us implement sustainable solutions that will preserve our ecosystem, reduce pollution, and create a greener future for everyone.",
            "The biodiversity in {location} is at risk. Your donation will support critical conservation work to protect endangered species, restore natural habitats, and promote environmental stewardship."
        ],
        'Arts' => [
            "Art brings communities together. Help us cultivate creativity in {location} through accessible arts programming, exhibitions, and performances that celebrate our diverse cultural heritage.",
            "Support the vibrant artistic community in {location} by funding public art installations, creative workshops, and performance spaces that make art accessible to everyone.",
            "Culture and creativity are essential to community identity. Your contribution will help preserve and promote the unique artistic traditions of {location} while nurturing the next generation of artists."
        ],
        'Community' => [
            "Strong communities are built on connection and support. Help us strengthen {location} by funding community spaces, social programs, and neighborhood initiatives that bring people together.",
            "Every community deserves safe, welcoming spaces. Your donation will help transform {location} through infrastructure improvements, public facilities, and programs that foster community bonds.",
            "{location} has faced significant challenges. Support our community rebuilding efforts through funding social services, public spaces, and programs that address the unique needs of our residents."
        ]
    ];
    
    // Sample locations
    private $locations = [
        'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix',
        'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'Austin',
        'Seattle', 'Denver', 'Boston', 'Nashville', 'Portland',
        'Atlanta', 'Miami', 'Detroit', 'Minneapolis', 'San Francisco',
        'Rural America', 'Appalachia', 'Native American Reservations',
        'Coastal Communities', 'Small Town USA', 'Urban Centers',
        'Ghana', 'Kenya', 'India', 'Brazil', 'Mexico', 'Haiti',
        'Southeast Asia', 'Eastern Europe', 'Central America'
    ];
    
    // Sample first names
    private $firstNames = [
        'James', 'Robert', 'John', 'Michael', 'William', 'David', 'Richard', 'Joseph',
        'Thomas', 'Charles', 'Christopher', 'Daniel', 'Matthew', 'Anthony', 'Mark',
        'Mary', 'Patricia', 'Jennifer', 'Linda', 'Elizabeth', 'Barbara', 'Susan',
        'Jessica', 'Sarah', 'Karen', 'Emma', 'Olivia', 'Ava', 'Mia', 'Sofia',
        'Liam', 'Noah', 'Oliver', 'Elijah', 'Lucas', 'Mason', 'Logan', 'Ethan',
        'Miguel', 'Wei', 'Hiroshi', 'Ahmed', 'Rahul', 'Fatima', 'Zainab', 'Chen',
        'Juan', 'Carlos', 'Sophia', 'Isabella', 'Charlotte', 'Amelia', 'Harper',
        'Aiden', 'Jackson', 'Sebastian', 'Aria', 'Scarlett', 'Luna', 'Zoe'
    ];
    
    // Sample last names
    private $lastNames = [
        'Smith', 'Johnson', 'Williams', 'Jones', 'Brown', 'Davis', 'Miller', 'Wilson',
        'Moore', 'Taylor', 'Anderson', 'Thomas', 'Jackson', 'White', 'Harris', 'Martin',
        'Thompson', 'Garcia', 'Martinez', 'Robinson', 'Clark', 'Rodriguez', 'Lewis',
        'Lee', 'Walker', 'Hall', 'Allen', 'Young', 'Hernandez', 'King', 'Wright',
        'Lopez', 'Hill', 'Scott', 'Green', 'Adams', 'Baker', 'Gonzalez', 'Nelson',
        'Carter', 'Mitchell', 'Perez', 'Roberts', 'Turner', 'Phillips', 'Campbell',
        'Parker', 'Evans', 'Edwards', 'Collins', 'Stewart', 'Sanchez', 'Morris',
        'Rogers', 'Reed', 'Cook', 'Morgan', 'Bell', 'Murphy', 'Bailey', 'Rivera',
        'Cooper', 'Richardson', 'Cox', 'Howard', 'Ward', 'Torres', 'Peterson',
        'Gray', 'Ramirez', 'James', 'Watson', 'Brooks', 'Kelly', 'Sanders'
    ];
    
    // Sample destinations
    private $destinations = [
        'Washington D.C.', 'New York City', 'Boston', 'Chicago', 
        'Los Angeles', 'San Francisco', 'Seattle', 'London', 
        'Paris', 'Rome', 'Tokyo', 'Sydney', 'Beijing', 'Berlin', 
        'Cairo', 'Cape Town', 'Rio de Janeiro', 'Mexico City'
    ];
    
    // Constructor
    public function __construct() {
        $this->db = new Database("givehub");
        $this->usersCollection = $this->db->getCollection('users');
        $this->campaignsCollection = $this->db->getCollection('campaigns');
    }
    
    /**
     * Run the data generation process
     */
    public function generate() {
        echo "Starting demo data generation...\n";
        
        // Generate users if not enough exist
        $userCount = $this->usersCollection->count();
        if ($userCount < 50) {
            $this->generateUsers(50 - $userCount);
        } else {
            echo "Skipping user generation. Already have $userCount users.\n";
        }
        
        // Generate campaigns if not enough exist
        $campaignCount = $this->campaignsCollection->count();
        if ($campaignCount < 30) {
            $this->generateCampaigns(30 - $campaignCount);
        } else {
            echo "Skipping campaign generation. Already have $campaignCount campaigns.\n";
        }
        
        // Update funding data for campaigns
        $this->updateCampaignFunding();
        
        echo "Demo data generation completed!\n";
    }
    
    /**
     * Generate sample users
     */
    private function generateUsers($count) {
        echo "Generating $count sample users...\n";
        
        $users = [];
        
        for ($i = 0; $i < $count; $i++) {
            $firstName = $this->firstNames[array_rand($this->firstNames)];
            $lastName = $this->lastNames[array_rand($this->lastNames)];
            $username = strtolower($firstName . substr($lastName, 0, 1) . mt_rand(1, 999));
            $email = strtolower($firstName . '.' . $lastName . mt_rand(1, 999) . '@example.com');
            
            // Create user data
            $user = [
                'username' => $username,
                'email' => $email,
                'displayName' => $firstName . ' ' . $lastName,
                'status' => $this->getRandomStatus(),
                'personalInfo' => [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'email' => $email
                ],
                'created' => $this->getRandomDate(180, 0), // Between 6 months ago and today
                'roles' => ['user']
            ];
            
            // Small chance of being an admin
            if (mt_rand(1, 25) === 1) {
                $user['roles'][] = 'admin';
            }
            
            // Add to batch
            $users[] = $user;
            
            // Every 20 users, insert batch
            if (count($users) >= 20) {
                $this->usersCollection->insertMany($users);
                $users = [];
                echo "Inserted 20 users...\n";
            }
        }
        
        // Insert any remaining users
        if (count($users) > 0) {
            $this->usersCollection->insertMany($users);
            echo "Inserted " . count($users) . " users...\n";
        }
        
        echo "Completed user generation.\n";
    }
    
    /**
     * Generate sample campaigns
     */
    private function generateCampaigns($count) {
        echo "Generating $count sample campaigns...\n";
        
        // Get all users to assign as creators
        $users = $this->usersCollection->find([], ['limit' => 100]);
        
        if (count($users) === 0) {
            echo "No users found to assign as campaign creators.\n";
            return;
        }
        
        $campaigns = [];
        
        for ($i = 0; $i < $count; $i++) {
            // Select random user
            $creator = $users[array_rand($users)];
            $creatorId = $creator['_id'];
            $creatorName = $creator['displayName'];
            
            // Pick a random category
            $category = $this->categories[array_rand($this->categories)];
            
            // Pick a random title template for the category
            $titleTemplates = $this->campaignTitleTemplates[$category];
            $titleTemplate = $titleTemplates[array_rand($titleTemplates)];
            
            // Generate a random location
            $location = $this->locations[array_rand($this->locations)];
            $destination = $this->destinations[array_rand($this->destinations)];
            $name = $creator['personalInfo']['firstName'];
            
            // Replace placeholders in the title
            $title = str_replace(
                ['{location}', '{destination}', '{name}'],
                [$location, $destination, $name],
                $titleTemplate
            );
            
            // Pick a random description template for the category
            $descriptionTemplates = $this->campaignDescriptions[$category] ?? $this->campaignDescriptions['Community'];
            $descriptionTemplate = $descriptionTemplates[array_rand($descriptionTemplates)];
            
            // Replace placeholders in the description
            $description = str_replace(
                ['{location}', '{destination}', '{name}'],
                [$location, $destination, $name],
                $descriptionTemplate
            );
            
            // Generate funding goal (between $1,000 and $100,000)
            $fundingGoal = mt_rand(10, 1000) * 100;
            
            // Generate campaign date (between 1 year ago and today)
            $createdAt = $this->getRandomDate(365, 0);
            
            // Generate deadline (between 1 day and 90 days from creation)
            $deadlineDate = new DateTime($createdAt);
            $deadlineDate->modify('+' . mt_rand(30, 90) . ' days');
            $deadline = $deadlineDate->format('Y-m-d H:i:s');
            
            // Determine status based on created date
            $status = 'pending';
            $reviewedAt = null;
            $reviewedBy = null;
            
            // 70% chance of being reviewed if more than 5 days old
            $creationDate = new DateTime($createdAt);
            $interval = $creationDate->diff(new DateTime());
            
            if ($interval->days > 5 && mt_rand(1, 10) <= 7) {
                // 80% chance of approval
                if (mt_rand(1, 10) <= 8) {
                    $status = 'active';
                } else {
                    $status = 'rejected';
                }
                
                // Add review date (1-5 days after creation)
                $reviewDate = clone $creationDate;
                $reviewDate->modify('+' . mt_rand(1, 5) . ' days');
                $reviewedAt = $reviewDate->format('Y-m-d H:i:s');
                
                // Get random admin
                $adminCreator = $users[array_rand($users)];
                $reviewedBy = $adminCreator['displayName'];
            }
            
            // Create campaign data
            $campaign = [
                'title' => $title,
                'description' => $description . "\n\nYour support will make a difference in " . $location . " and beyond. Every donation, no matter the size, brings us closer to our goal. Thank you for your generosity!",
                'category' => $category,
                'creatorId' => $creatorId,
                'creatorName' => $creatorName,
                'fundingGoal' => $fundingGoal,
                'raised' => 0, // Will be updated later
                'status' => $status,
                'createdAt' => $createdAt,
                'deadline' => $deadline,
                'minContribution' => mt_rand(1, 5) * 5, // $5, $10, $15, $20, or $25
                'location' => [
                    'region' => $location,
                    'country' => 'United States'
                ],
                'images' => [
                    '/img/campaign-placeholder-' . mt_rand(1, 5) . '.jpg'
                ]
            ];
            
            // Add review info if reviewed
            if ($reviewedAt) {
                $campaign['reviewedAt'] = $reviewedAt;
                $campaign['reviewedBy'] = $reviewedBy;
                
                // Add rejection reason if rejected
                if ($status === 'rejected') {
                    $rejectionReasons = [
                        'Insufficient information provided about the project.',
                        'Project goals are unclear or unrealistic.',
                        'Budget details are incomplete or inconsistent.',
                        'Content violates our community guidelines.',
                        'Unable to verify creator identity or credentials.'
                    ];
                    
                    $campaign['feedback'] = $rejectionReasons[array_rand($rejectionReasons)];
                }
            }
            
            // Add to batch
            $campaigns[] = $campaign;
            
            // Every 10 campaigns, insert batch
            if (count($campaigns) >= 10) {
                $this->campaignsCollection->insertMany($campaigns);
                $campaigns = [];
                echo "Inserted 10 campaigns...\n";
            }
        }
        
        // Insert any remaining campaigns
        if (count($campaigns) > 0) {
            $this->campaignsCollection->insertMany($campaigns);
            echo "Inserted " . count($campaigns) . " campaigns...\n";
        }
        
        echo "Completed campaign generation.\n";
    }
    
    /**
     * Update campaigns with realistic funding data
     */
    private function updateCampaignFunding() {
        echo "Updating campaign funding data...\n";
        
        // Get all active campaigns
        $campaigns = $this->campaignsCollection->find(['status' => 'active']);
        
        foreach ($campaigns as $campaign) {
            $id = $campaign['_id'];
            $fundingGoal = isset($campaign['fundingGoal']) ? (float)$campaign['fundingGoal'] : 5000;
            
            // Generate random funding progress (between 10% and 110% of goal)
            $fundingPercentage = mt_rand(10, 110) / 100;
            $raised = round($fundingGoal * $fundingPercentage, 2);
            
            // Update campaign with funding info
            $this->campaignsCollection->updateOne(
                ['_id' => $id],
                ['$set' => ['raised' => $raised]]
            );
        }
        
        // Get rejected campaigns
        $rejectedCampaigns = $this->campaignsCollection->find(['status' => 'rejected']);
        
        foreach ($rejectedCampaigns as $campaign) {
            $id = $campaign['_id'];
            $fundingGoal = isset($campaign['fundingGoal']) ? (float)$campaign['fundingGoal'] : 5000;
            
            // Generate minimal funding for rejected campaigns (between 0% and 5% of goal)
            $fundingPercentage = mt_rand(0, 5) / 100;
            $raised = round($fundingGoal * $fundingPercentage, 2);
            
            // Update campaign with funding info
            $this->campaignsCollection->updateOne(
                ['_id' => $id],
                ['$set' => ['raised' => $raised]]
            );
        }
        
        // Get pending campaigns
        $pendingCampaigns = $this->campaignsCollection->find(['status' => 'pending']);
        
        foreach ($pendingCampaigns as $campaign) {
            $id = $campaign['_id'];
            
            // No funding for pending campaigns
            $this->campaignsCollection->updateOne(
                ['_id' => $id],
                ['$set' => ['raised' => 0]]
            );
        }
        
        echo "Completed funding data updates.\n";
    }
    
    /**
     * Get a random date between $minDaysAgo and $maxDaysAgo
     */
    private function getRandomDate($minDaysAgo, $maxDaysAgo) {
        $timestamp = time() - mt_rand($maxDaysAgo * 86400, $minDaysAgo * 86400);
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    /**
     * Get a random user status
     */
    private function getRandomStatus() {
        $statuses = ['active', 'active', 'active', 'active', 'pending', 'suspended'];
        return $statuses[array_rand($statuses)];
    }
}

// Execute data generation
$generator = new DemoDataGenerator();
$generator->generate();

echo "Script completed!\n";
