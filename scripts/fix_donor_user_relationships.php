<?php
/**
 * Fix Donor-User Relationships Script
 * 
 * This is a comprehensive script that diagnoses and fixes issues with donor-user relationships.
 * It performs the following tasks:
 * 
 * 1. Lists donor-user relationship statistics
 * 2. Identifies donors without user accounts
 * 3. Identifies users with donor relationships that need fixing
 * 4. Creates user accounts for donors who need them
 * 5. Links donor-user relationships bidirectionally
 * 6. Verifies wallet access for donors
 * 
 * Usage: php scripts/fix_donor_user_relationships.php [options]
 * Options:
 *   --dry-run          Show what would be done without making changes
 *   --fix-all          Automatically fix all identified issues
 *   --create-users     Create user accounts for donors without them
 *   --link-only        Only link existing users to donors (no new users created)
 *   --create-wallets   Create wallets for users without them
 *   --limit=N          Limit processing to N donors (default: all)
 *   --verbose          Show detailed output during processing
 */

// Load required files
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/Wallets.php';

// Parse command line options
$options = getopt('', ['dry-run', 'fix-all', 'create-users', 'link-only', 'create-wallets', 'limit::', 'verbose']);
$dryRun = isset($options['dry-run']);
$fixAll = isset($options['fix-all']);
$createUsers = isset($options['create-users']) || $fixAll;
$linkOnly = isset($options['link-only']);
$createWallets = isset($options['create-wallets']) || $fixAll;
$limit = isset($options['limit']) ? (int)$options['limit'] : 0;
$verbose = isset($options['verbose']);

class DonorUserRelationshipFixer {
    private $db;
    private $donorsCollection;
    private $usersCollection;
    private $walletsCollection;
    private $walletsService;
    private $dryRun;
    private $verbose;
    
    private $stats = [
        'total_donors' => 0,
        'donors_with_users' => 0,
        'donors_without_users' => 0,
        'users_with_donors' => 0,
        'users_without_donor_ref' => 0,
        'users_with_wallets' => 0,
        'users_without_wallets' => 0,
        'fixed_links' => 0,
        'created_users' => 0,
        'created_wallets' => 0,
        'errors' => 0
    ];
    
    public function __construct($dryRun = false, $verbose = false) {
        $this->dryRun = $dryRun;
        $this->verbose = $verbose;
        
        // Initialize database connection
        $this->db = new Database();
        $this->donorsCollection = $this->db->db->selectCollection('donors');
        $this->usersCollection = $this->db->db->selectCollection('users');
        $this->walletsCollection = $this->db->db->selectCollection('wallets');
        $this->walletsService = new Wallets();
        
        $this->log("Database connection initialized");
        $this->log("Dry run mode: " . ($dryRun ? "ON" : "OFF"));
    }
    
    /**
     * Run diagnostics and display statistics
     */
    public function runDiagnostics() {
        $this->log("\n=== RUNNING DIAGNOSTICS ===\n");
        
        // Count all donors
        $this->stats['total_donors'] = $this->donorsCollection->countDocuments([]);
        $this->log("Total donors: {$this->stats['total_donors']}");
        
        // Count donors with user accounts
        $this->stats['donors_with_users'] = $this->donorsCollection->countDocuments(['userId' => ['$exists' => true]]);
        $this->stats['donors_without_users'] = $this->stats['total_donors'] - $this->stats['donors_with_users'];
        $this->log("Donors with user accounts: {$this->stats['donors_with_users']}");
        $this->log("Donors without user accounts: {$this->stats['donors_without_users']}");
        
        // Count users with donor references
        $this->stats['users_with_donors'] = $this->usersCollection->countDocuments(['donorId' => ['$exists' => true]]);
        $this->log("Users with donor references: {$this->stats['users_with_donors']}");
        
        // Find users that have a donorId but the donor doesn't have userId
        $usersWithIncompleteLinks = $this->getUsersWithIncompleteLinks();
        $this->stats['users_without_donor_ref'] = count($usersWithIncompleteLinks);
        $this->log("Users with incomplete donor links: {$this->stats['users_without_donor_ref']}");
        
        // Count users with wallets
        $this->stats['users_with_wallets'] = $this->usersCollection->countDocuments([
            'donorId' => ['$exists' => true],
            '_id' => ['$in' => $this->getUserIdsWithWallets()]
        ]);
        $this->stats['users_without_wallets'] = $this->stats['users_with_donors'] - $this->stats['users_with_wallets'];
        $this->log("Users with wallets: {$this->stats['users_with_wallets']}");
        $this->log("Users without wallets: {$this->stats['users_without_wallets']}");
        
        // Display a sample of donor-user relationship issues
        $this->displaySampleIssues();
    }
    
    /**
     * Display a sample of donor-user relationship issues for manual inspection
     */
    private function displaySampleIssues() {
        $this->log("\n=== SAMPLE ISSUES ===\n");
        
        // Sample donors without users
        $donorsWithoutUsers = $this->donorsCollection->find(
            ['userId' => ['$exists' => false]],
            ['limit' => 5]
        )->toArray();
        
        if (count($donorsWithoutUsers) > 0) {
            $this->log("Sample donors without users:");
            foreach ($donorsWithoutUsers as $donor) {
                $donorId = (string)$donor['_id'];
                $name = $donor['name'] ?? ($donor['donor']['name'] ?? 'Unknown');
                $email = $donor['email'] ?? ($donor['donor']['email'] ?? 'Unknown');
                $this->log(" - Donor ID: $donorId, Name: $name, Email: $email");
            }
        }
        
        // Sample users with incomplete links
        $usersWithIncompleteLinks = $this->getUsersWithIncompleteLinks(5);
        
        if (count($usersWithIncompleteLinks) > 0) {
            $this->log("\nSample users with incomplete donor links:");
            foreach ($usersWithIncompleteLinks as $user) {
                $userId = (string)$user['_id'];
                $donorId = (string)$user['donorId'];
                $email = $user['email'] ?? 'Unknown';
                $this->log(" - User ID: $userId, Donor ID: $donorId, Email: $email");
            }
        }
        
        // Sample users without wallets
        $usersWithoutWallets = $this->getUsersWithoutWallets(5);
        
        if (count($usersWithoutWallets) > 0) {
            $this->log("\nSample users without wallets:");
            foreach ($usersWithoutWallets as $user) {
                $userId = (string)$user['_id'];
                $donorId = isset($user['donorId']) ? (string)$user['donorId'] : 'None';
                $email = $user['email'] ?? 'Unknown';
                $this->log(" - User ID: $userId, Donor ID: $donorId, Email: $email");
            }
        }
    }
    
    /**
     * Fix donor-user relationships
     * @param bool $createUsers Whether to create user accounts for donors without them
     * @param bool $linkOnly Only link existing users to donors (no new users created)
     * @param int $limit Limit processing to N donors (0 = all)
     */
    public function fixRelationships($createUsers = false, $linkOnly = false, $limit = 0) {
        $this->log("\n=== FIXING DONOR-USER RELATIONSHIPS ===\n");
        
        if ($linkOnly) {
            $this->log("Mode: Link only (no new users will be created)");
            $createUsers = false;
        } else if ($createUsers) {
            $this->log("Mode: Create users for donors without accounts");
        } else {
            $this->log("Mode: Diagnostic only (no changes will be made)");
            $this->dryRun = true;
        }
        
        // First fix existing incomplete links
        $this->fixIncompleteLinks();
        
        // Then create user accounts for donors without them if requested
        if ($createUsers) {
            $this->createMissingUserAccounts($limit);
        }
        
        // Display summary
        $this->log("\n=== RELATIONSHIP FIXING SUMMARY ===\n");
        $this->log("Fixed incomplete links: {$this->stats['fixed_links']}");
        $this->log("Created new user accounts: {$this->stats['created_users']}");
        $this->log("Errors encountered: {$this->stats['errors']}");
    }
    
    /**
     * Fix incomplete donor-user links (where one side has reference but other doesn't)
     */
    private function fixIncompleteLinks() {
        $this->log("Fixing incomplete donor-user links...");
        
        // Find users with donorId where the donor doesn't have userId
        $usersWithIncompleteLinks = $this->getUsersWithIncompleteLinks();
        
        if (empty($usersWithIncompleteLinks)) {
            $this->log("No incomplete links found.");
            return;
        }
        
        $this->log("Found " . count($usersWithIncompleteLinks) . " incomplete links to fix");
        
        foreach ($usersWithIncompleteLinks as $user) {
            $userId = (string)$user['_id'];
            $donorId = (string)$user['donorId'];
            $email = $user['email'] ?? 'Unknown';
            
            $this->log("Fixing link between User $userId and Donor $donorId ($email)");
            
            if (!$this->dryRun) {
                try {
                    // Update the donor to reference the user
                    $result = $this->donorsCollection->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($donorId)],
                        ['$set' => [
                            'userId' => new MongoDB\BSON\ObjectId($userId),
                            'updatedAt' => new MongoDB\BSON\UTCDateTime()
                        ]]
                    );
                    
                    if ($result->getModifiedCount() > 0) {
                        $this->stats['fixed_links']++;
                        $this->log(" - Successfully updated donor with user reference");
                    } else {
                        $this->log(" - No update needed for donor (already linked or donor not found)");
                    }
                } catch (Exception $e) {
                    $this->stats['errors']++;
                    $this->log(" - ERROR: " . $e->getMessage(), true);
                }
            }
        }
    }
    
    /**
     * Create user accounts for donors without them
     * @param int $limit Limit processing to N donors (0 = all)
     */
    private function createMissingUserAccounts($limit = 0) {
        // Find donors without users
        $query = ['userId' => ['$exists' => false]];
        $options = [];
        
        if ($limit > 0) {
            $options['limit'] = $limit;
        }
        
        $donorsWithoutUsers = $this->donorsCollection->find($query, $options)->toArray();
        
        if (empty($donorsWithoutUsers)) {
            $this->log("No donors without user accounts found.");
            return;
        }
        
        $this->log("Creating user accounts for " . count($donorsWithoutUsers) . " donors");
        
        foreach ($donorsWithoutUsers as $donor) {
            $donorId = (string)$donor['_id'];
            
            // Get donor email
            $email = null;
            if (isset($donor['email'])) {
                $email = $donor['email'];
            } elseif (isset($donor['donor']) && isset($donor['donor']['email'])) {
                $email = $donor['donor']['email'];
            } elseif (isset($donor['donorInfo']) && isset($donor['donorInfo']['email'])) {
                $email = $donor['donorInfo']['email'];
            }
            
            if (!$email) {
                $this->log("Donor $donorId has no email address, skipping", false, true);
                continue;
            }
            
            // Clean and validate email
            $email = trim($email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->log("Donor $donorId has invalid email: $email, skipping", false, true);
                continue;
            }
            
            // Get donor name
            $name = null;
            if (isset($donor['name'])) {
                $name = $donor['name'];
            } elseif (isset($donor['donor']) && isset($donor['donor']['name'])) {
                $name = $donor['donor']['name'];
            } elseif (isset($donor['donorInfo']) && isset($donor['donorInfo']['name'])) {
                $name = $donor['donorInfo']['name'];
            }
            
            // Default name if none found
            if (!$name || $name === 'Anonymous' || $name === 'Anonymous Donor') {
                $name = "Donor " . substr($donorId, -6);
            }
            
            // Parse name into first and last name
            $nameParts = explode(' ', $name, 2);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
            
            $this->log("Creating user for donor $donorId: $firstName $lastName <$email>");
            
            if ($this->dryRun) {
                continue;
            }
            
            // Check if a user with this email already exists
            $existingUser = $this->usersCollection->findOne(['email' => $email]);
            
            if ($existingUser) {
                $userId = (string)$existingUser['_id'];
                $this->log(" - User with email $email already exists (ID: $userId), linking to donor");
                
                // Link this donor to the existing user
                try {
                    $this->linkDonorToUser($donorId, $userId);
                    $this->stats['fixed_links']++;
                } catch (Exception $e) {
                    $this->stats['errors']++;
                    $this->log(" - ERROR linking to existing user: " . $e->getMessage(), true);
                }
                
                continue;
            }
            
            // Create new user
            try {
                $userId = $this->createUserForDonor($email, $firstName, $lastName, $donorId);
                
                if ($userId) {
                    $this->log(" - Created new user with ID: $userId");
                    $this->stats['created_users']++;
                    
                    // Link the donor to the new user
                    $this->linkDonorToUser($donorId, $userId);
                } else {
                    $this->stats['errors']++;
                    $this->log(" - ERROR: Failed to create user", true);
                    
                    // Try with modified email
                    $emailParts = explode('@', $email);
                    if (count($emailParts) === 2) {
                        $modifiedEmail = $emailParts[0] . '+donor@' . $emailParts[1];
                        $this->log(" - Retrying with modified email: $modifiedEmail");
                        
                        $userId = $this->createUserForDonor($modifiedEmail, $firstName, $lastName, $donorId);
                        
                        if ($userId) {
                            $this->log(" - Created new user with modified email, ID: $userId");
                            $this->stats['created_users']++;
                            
                            // Link the donor to the new user
                            $this->linkDonorToUser($donorId, $userId);
                        } else {
                            $this->log(" - ERROR: Failed to create user with modified email", true);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->stats['errors']++;
                $this->log(" - ERROR: " . $e->getMessage(), true);
            }
        }
    }
    
    /**
     * Create wallet for users without them
     * @param bool $createForAll Create wallets for all users with donor relationships
     * @param int $limit Limit processing to N users (0 = all)
     */
    public function createWallets($createForAll = false, $limit = 0) {
        $this->log("\n=== CREATING WALLETS ===\n");
        
        // Find users that need wallets
        $usersWithoutWallets = $this->getUsersWithoutWallets($limit);
        
        if (empty($usersWithoutWallets)) {
            $this->log("No users without wallets found.");
            return;
        }
        
        $this->log("Found " . count($usersWithoutWallets) . " users without wallets");
        
        foreach ($usersWithoutWallets as $user) {
            $userId = (string)$user['_id'];
            $email = $user['email'] ?? 'Unknown';
            
            $this->log("Creating wallet for user $userId ($email)");
            
            if ($this->dryRun) {
                continue;
            }
            
            try {
                // Use the wallet service to create a wallet
                $result = $this->walletsService->createWallet(['userId' => $userId]);
                
                if ($result['success']) {
                    $this->stats['created_wallets']++;
                    $this->log(" - Successfully created wallet: " . $result['wallet']['publicKey']);
                    
                    // Try to fund the wallet on testnet
                    $fundResult = $this->walletsService->fundTestnetAccount(['publicKey' => $result['wallet']['publicKey']]);
                    
                    if ($fundResult['success']) {
                        $this->log(" - Successfully funded wallet with testnet XLM");
                    } else {
                        $this->log(" - WARNING: Failed to fund wallet: " . ($fundResult['error'] ?? "Unknown error"), false, true);
                    }
                } else {
                    $this->stats['errors']++;
                    $this->log(" - ERROR: Failed to create wallet: " . ($result['error'] ?? "Unknown error"), true);
                }
            } catch (Exception $e) {
                $this->stats['errors']++;
                $this->log(" - ERROR: " . $e->getMessage(), true);
            }
        }
        
        // Display summary
        $this->log("\n=== WALLET CREATION SUMMARY ===\n");
        $this->log("Created wallets: {$this->stats['created_wallets']}");
        $this->log("Errors encountered: {$this->stats['errors']}");
    }
    
    /**
     * Get users with incomplete donor links
     * @param int $limit Maximum number of results to return (0 = all)
     * @return array Users with incomplete donor links
     */
    private function getUsersWithIncompleteLinks($limit = 0) {
        $pipeline = [
            ['$match' => ['donorId' => ['$exists' => true]]],
            ['$lookup' => [
                'from' => 'donors',
                'localField' => 'donorId',
                'foreignField' => '_id',
                'as' => 'donor'
            ]],
            ['$match' => [
                '$or' => [
                    ['donor' => ['$size' => 0]],
                    ['donor.userId' => ['$exists' => false]]
                ]
            ]]
        ];
        
        if ($limit > 0) {
            $pipeline[] = ['$limit' => $limit];
        }
        
        $results = $this->usersCollection->aggregate($pipeline)->toArray();
        return $results;
    }
    
    /**
     * Get user IDs that have wallets
     * @return array MongoDB\BSON\ObjectId array of user IDs
     */
    private function getUserIdsWithWallets() {
        $userIds = [];
        $results = $this->walletsCollection->distinct('userId');
        
        foreach ($results as $id) {
            $userIds[] = $id;
        }
        
        return $userIds;
    }
    
    /**
     * Get users without wallets
     * @param int $limit Maximum number of results to return (0 = all)
     * @return array Users without wallets
     */
    private function getUsersWithoutWallets($limit = 0) {
        $userIdsWithWallets = $this->getUserIdsWithWallets();
        $query = [
            'donorId' => ['$exists' => true]
        ];
        
        if (!empty($userIdsWithWallets)) {
            $query['_id'] = ['$nin' => $userIdsWithWallets];
        }
        
        $options = [];
        if ($limit > 0) {
            $options['limit'] = $limit;
        }
        
        return $this->usersCollection->find($query, $options)->toArray();
    }
    
    /**
     * Create a new user account for a donor
     * @param string $email User email
     * @param string $firstName User first name
     * @param string $lastName User last name
     * @param string $donorId Associated donor ID
     * @return string|null User ID if successful, null otherwise
     */
    private function createUserForDonor($email, $firstName, $lastName, $donorId) {
        if ($this->dryRun) {
            return "dry-run-user-id";
        }
        
        try {
            // Create a random password
            $password = bin2hex(random_bytes(8));
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate username from email
            $emailParts = explode('@', $email);
            $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $emailParts[0]));
            if (strlen($baseUsername) < 3) {
                $baseUsername .= substr(md5($email), 0, 5);
            }
            
            // Generate a unique username
            $username = $baseUsername;
            $counter = 1;
            
            while ($this->usersCollection->countDocuments(['username' => $username]) > 0) {
                $username = $baseUsername . $counter;
                $counter++;
            }
            
            // Prepare the new user document
            $now = new MongoDB\BSON\UTCDateTime();
            $newUser = [
                'email' => $email,
                'username' => $username,
                'password' => $hashedPassword,
                'personalInfo' => [
                    'firstName' => $firstName,
                    'lastName' => $lastName
                ],
                'status' => 'active',
                'roles' => ['donor'],
                'createdAt' => $now,
                'updatedAt' => $now,
                'donorId' => new MongoDB\BSON\ObjectId($donorId)
            ];
            
            // Insert the new user
            $insertResult = $this->usersCollection->insertOne($newUser);
            return (string)$insertResult->getInsertedId();
        } catch (Exception $e) {
            $this->log("ERROR creating user: " . $e->getMessage(), true);
            return null;
        }
    }
    
    /**
     * Link a donor to a user
     * @param string $donorId Donor ID
     * @param string $userId User ID
     * @return bool Success indicator
     */
    private function linkDonorToUser($donorId, $userId) {
        if ($this->dryRun) {
            return true;
        }
        
        try {
            // Update donor with userId
            $result = $this->donorsCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($donorId)],
                ['$set' => [
                    'userId' => new MongoDB\BSON\ObjectId($userId),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            
            // Ensure user has donorId
            $user = $this->usersCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
            
            // Get existing roles if any
            $roles = ['donor']; // Default to donor role
            if ($user && isset($user['roles']) && is_array($user['roles'])) {
                if (!in_array('donor', $user['roles'])) {
                    $roles = array_merge($user['roles'], ['donor']);
                } else {
                    $roles = $user['roles'];
                }
            }
            
            // Only update user if donorId doesn't exist or is different
            if (!isset($user['donorId']) || (string)$user['donorId'] !== $donorId) {
                $this->usersCollection->updateOne(
                    ['_id' => new MongoDB\BSON\ObjectId($userId)],
                    ['$set' => [
                        'donorId' => new MongoDB\BSON\ObjectId($donorId),
                        'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                        'roles' => $roles
                    ]]
                );
            }
            
            return true;
        } catch (Exception $e) {
            $this->log("ERROR linking donor to user: " . $e->getMessage(), true);
            return false;
        }
    }
    
    /**
     * Log a message
     * @param string $message The message to log
     * @param bool $verbose Whether this is a verbose message
     * @param bool $isError Whether this is an error message
     */
    private function log($message, $verbose = false, $isError = false) {
        if ($verbose && !$this->verbose) {
            return;
        }
        
        if ($isError) {
            echo "[ERROR] ";
        } elseif ($this->dryRun) {
            echo "[DRY RUN] ";
        } else {
            echo "[INFO] ";
        }
        
        echo $message . "\n";
    }
    
    /**
     * Display final statistics
     */
    public function displayStats() {
        echo "\n";
        echo "============ SUMMARY ============\n";
        echo "Total donors: {$this->stats['total_donors']}\n";
        echo "Donors with user accounts: {$this->stats['donors_with_users']}\n";
        echo "Donors without user accounts: {$this->stats['donors_without_users']}\n";
        echo "Users with donor references: {$this->stats['users_with_donors']}\n";
        echo "Users with incomplete donor links: {$this->stats['users_without_donor_ref']}\n";
        echo "Users with wallets: {$this->stats['users_with_wallets']}\n";
        echo "Users without wallets: {$this->stats['users_without_wallets']}\n";
        echo "\n";
        echo "Fixed links: {$this->stats['fixed_links']}\n";
        echo "Created users: {$this->stats['created_users']}\n";
        echo "Created wallets: {$this->stats['created_wallets']}\n";
        echo "Errors: {$this->stats['errors']}\n";
        echo "=================================\n";
        
        if ($this->dryRun) {
            echo "\nThis was a DRY RUN. No changes were made to the database.\n";
            echo "Run without --dry-run to apply the changes.\n";
        } else {
            echo "\nAll changes have been applied to the database.\n";
        }
        
        echo "\nRecommended next steps:\n";
        if ($this->stats['donors_without_users'] > 0) {
            echo "- Run with --create-users to create user accounts for donors without them\n";
        }
        if ($this->stats['users_without_wallets'] > 0) {
            echo "- Run with --create-wallets to create wallets for users without them\n";
        }
        if ($this->stats['users_without_donor_ref'] > 0) {
            echo "- Run without --dry-run to fix incomplete donor-user relationships\n";
        }
        if ($this->stats['donors_without_users'] == 0 && $this->stats['users_without_wallets'] == 0 && $this->stats['users_without_donor_ref'] == 0) {
            echo "- All relationships are properly set up. Test the transaction-demo.html page.\n";
        }
    }
}

// Main execution
echo "Donor-User Relationship Fixer\n";
echo "----------------------------\n";
echo "Analyzing and fixing donor-user relationships in the database\n\n";

$fixer = new DonorUserRelationshipFixer($dryRun, $verbose);

// First run diagnostics
$fixer->runDiagnostics();

// Then fix relationships if requested
if (!$dryRun || $fixAll || $createUsers || $linkOnly) {
    $fixer->fixRelationships($createUsers, $linkOnly, $limit);
}

// Create wallets if requested
if ($createWallets) {
    $fixer->createWallets(false, $limit);
}

// Display final statistics
$fixer->displayStats();

echo "\nScript completed.\n";