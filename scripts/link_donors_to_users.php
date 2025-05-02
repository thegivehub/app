<?php
/**
 * Link Donors to Users Script
 * 
 * This script:
 * 1. Iterates through all donors in the donors collection
 * 2. For each donor, checks if there's a corresponding user account by email
 * 3. If a user exists, links the donor to that user
 * 4. If no user exists, creates a new user account and links it
 * 5. Updates both collections with cross-references
 * 
 * Run this script from the command line:
 * php scripts/link_donors_to_users.php [--dry-run] [--quiet]
 * 
 * Options:
 * --dry-run: Shows what would be done without making changes
 * --quiet: Reduces output verbosity
 */

// Load required files
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/Collection.php';

// Parse command line options
$dryRun = in_array('--dry-run', $argv);
$quiet = in_array('--quiet', $argv);

class DonorUserLinker {
    private $db;
    private $donorsCollection;
    private $usersCollection;
    private $dryRun;
    private $quiet;
    
    private $stats = [
        'total_donors' => 0,
        'already_linked' => 0,
        'linked_to_existing' => 0,
        'new_users_created' => 0,
        'failed' => 0,
        'skipped_no_email' => 0
    ];
    
    public function __construct($dryRun = false, $quiet = false) {
        $this->dryRun = $dryRun;
        $this->quiet = $quiet;
        
        // Initialize database connection
        $this->db = new Database();
        $this->donorsCollection = $this->db->getCollection('donors');
        $this->usersCollection = $this->db->getCollection('users');
        
        $this->log("Database connection initialized.");
        $this->log("Dry run mode: " . ($dryRun ? "ON" : "OFF"));
    }
    
    /**
     * Process all donors and link them to users
     */
    public function processAllDonors() {
        $this->log("Starting to process donors...");
        
        // Get all donors
        $donors = $this->donorsCollection->find([]);
        $this->stats['total_donors'] = $this->donorsCollection->countDocuments([]);
        
        $this->log("Found {$this->stats['total_donors']} donors to process.");
        
        // Process each donor
        foreach ($donors as $donor) {
            $this->processDonor($donor);
        }
        
        // Display final stats
        $this->displayStats();
    }
    
    /**
     * Process a single donor
     * @param array $donor The donor document
     */
    private function processDonor($donor) {
        $donorId = (string)$donor['_id'];
        
        // Check if donor is already linked to a user
        if (isset($donor['userId'])) {
            $userIdStr = (string)$donor['userId'];
            $this->log("Donor {$donorId} already linked to user {$userIdStr}.", true);
            $this->stats['already_linked']++;
            
            // Ensure the user has a donorId reference as well
            if (!$this->dryRun) {
                $this->ensureUserHasDonorReference($userIdStr, $donorId);
            }
            return;
        }
        
        // Get donor email - search in multiple possible locations
        $email = null;
        if (isset($donor['email'])) {
            $email = $donor['email'];
        } elseif (isset($donor['donor']) && isset($donor['donor']['email'])) {
            $email = $donor['donor']['email'];
        } elseif (isset($donor['donorInfo']) && isset($donor['donorInfo']['email'])) {
            $email = $donor['donorInfo']['email'];
        }
        
        if (!$email) {
            $this->log("Donor {$donorId} has no email address, skipping.", true);
            $this->stats['skipped_no_email']++;
            return;
        }
        
        // Clean and validate email
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->log("Donor {$donorId} has invalid email: {$email}, skipping.", true);
            $this->stats['skipped_no_email']++;
            return;
        }
        
        // Get donor name - search in multiple possible locations
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
        
        $this->log("Processing donor {$donorId}: {$firstName} {$lastName} <{$email}>");
        
        // Check if a user with this email already exists
        $existingUser = null;
        try {
            $existingUser = $this->usersCollection->findOne(['email' => $email]);
        } catch (Exception $e) {
            $this->log("Error finding existing user by email: " . $e->getMessage(), false, true);
        }
        
        if ($existingUser) {
            // Link donor to existing user
            $userId = (string)$existingUser['_id'];
            $this->log("Donor {$donorId} will be linked to existing user {$userId} ({$existingUser['username']})");
            
            if (!$this->dryRun) {
                $this->linkDonorToUser($donorId, $userId);
            }
            
            $this->stats['linked_to_existing']++;
        } else {
            // Try to create a new user account
            $this->log("No existing user found for email {$email}, creating new user...");
            
            $userId = $this->createUserForDonor($email, $firstName, $lastName, $donorId);
            
            if ($userId) {
                $this->log("Created new user {$userId} for donor {$donorId}");
                $this->stats['new_users_created']++;
            } else {
                $this->log("Failed to create user for donor {$donorId}", false, true);
                $this->stats['failed']++;
                
                // Try one more time with a modified email (add +donor suffix)
                $emailParts = explode('@', $email);
                if (count($emailParts) === 2) {
                    $modifiedEmail = $emailParts[0] . '+donor@' . $emailParts[1];
                    $this->log("Retrying with modified email: {$modifiedEmail}");
                    
                    $userId = $this->createUserForDonor($modifiedEmail, $firstName, $lastName, $donorId);
                    
                    if ($userId) {
                        $this->log("Created new user {$userId} with modified email for donor {$donorId}");
                        $this->stats['new_users_created']++;
                        $this->stats['failed']--; // Decrement the failed count since we succeeded
                    }
                }
            }
        }
    }
    
    /**
     * Create a new user account for a donor
     * @param string $email
     * @param string $firstName
     * @param string $lastName
     * @param string $donorId
     * @return string|null User ID if successful, null otherwise
     */
    private function createUserForDonor($email, $firstName, $lastName, $donorId) {
        if ($this->dryRun) {
            return "dry-run-user-id";
        }
        
        try {
            // Create a random password (they can reset it later)
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
            
            // Insert the new user and get the ID
            $this->log("Creating new user with email {$email} and username {$username}");
            
            // Use a direct insertion approach with error details
            try {
                $rawCollection = $this->db->db->selectCollection('users');
                $insertResult = $rawCollection->insertOne($newUser);
                $userId = (string)$insertResult->getInsertedId();
                
                if ($userId) {
                    $this->log("Successfully created user with ID: {$userId}");
                    
                    // Link the donor to the new user
                    $this->linkDonorToUser($donorId, $userId);
                    return $userId;
                }
            } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
                // MongoDB bulk write exception
                $this->log("MongoDB BulkWriteException: " . $e->getMessage(), false, true);
                
                // Check for duplicate key error
                if (strpos($e->getMessage(), 'duplicate key error') !== false) {
                    if (strpos($e->getMessage(), 'email') !== false) {
                        $this->log("A user with email {$email} already exists", false, true);
                    } else if (strpos($e->getMessage(), 'username') !== false) {
                        $this->log("A user with username {$username} already exists", false, true);
                    }
                }
            } catch (\Exception $e) {
                $this->log("General exception while creating user: " . $e->getMessage(), false, true);
            }
            
            return null;
        } catch (Exception $e) {
            $this->log("Error preparing user data: " . $e->getMessage(), false, true);
            return null;
        }
    }
    
    /**
     * Link a donor to a user by updating the donor document
     * @param string $donorId
     * @param string $userId
     * @return bool
     */
    private function linkDonorToUser($donorId, $userId) {
        if ($this->dryRun) {
            return true;
        }
        
        try {
            // Use direct MongoDB driver to update donor with userId
            $rawDonorCollection = $this->db->db->selectCollection('donors');
            $result = $rawDonorCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($donorId)],
                ['$set' => [
                    'userId' => new MongoDB\BSON\ObjectId($userId),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]]
            );
            
            // Also ensure the user has a reference to the donor
            $this->ensureUserHasDonorReference($userId, $donorId);
            
            // Check if the update was successful
            return $result->getModifiedCount() > 0;
        } catch (Exception $e) {
            $this->log("Error linking donor to user: " . $e->getMessage(), false, true);
            return false;
        }
    }
    
    /**
     * Ensure the user document has a reference to the donor
     * @param string $userId
     * @param string $donorId
     * @return bool
     */
    private function ensureUserHasDonorReference($userId, $donorId) {
        if ($this->dryRun) {
            return true;
        }
        
        try {
            // First check if the user already has a donorId reference
            $rawUserCollection = $this->db->db->selectCollection('users');
            $user = $rawUserCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
            
            if ($user && isset($user['donorId'])) {
                // User already has a donorId, nothing to do
                return true;
            }
            
            // Get existing user roles if any
            $roles = ['donor']; // Default to donor role
            if ($user && isset($user['roles']) && is_array($user['roles'])) {
                if (!in_array('donor', $user['roles'])) {
                    $roles = array_merge($user['roles'], ['donor']);
                } else {
                    $roles = $user['roles'];
                }
            }
            
            // Update user with donorId using direct MongoDB driver
            $result = $rawUserCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                ['$set' => [
                    'donorId' => new MongoDB\BSON\ObjectId($donorId),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                    'roles' => $roles
                ]]
            );
            
            return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;
        } catch (Exception $e) {
            $this->log("Error updating user with donor reference: " . $e->getMessage(), false, true);
            return false;
        }
    }
    
    /**
     * Display final statistics
     */
    private function displayStats() {
        echo "\n";
        echo "============ SUMMARY ============\n";
        echo "Total donors processed: {$this->stats['total_donors']}\n";
        echo "Already linked: {$this->stats['already_linked']}\n";
        echo "Linked to existing users: {$this->stats['linked_to_existing']}\n";
        echo "New users created: {$this->stats['new_users_created']}\n";
        echo "Failed: {$this->stats['failed']}\n";
        echo "Skipped (no email): {$this->stats['skipped_no_email']}\n";
        echo "=================================\n";
        
        if ($this->dryRun) {
            echo "\nThis was a DRY RUN. No changes were made to the database.\n";
            echo "Run without --dry-run to apply the changes.\n";
        } else {
            echo "\nAll changes have been applied to the database.\n";
        }
    }
    
    /**
     * Log a message with optional verbosity control
     * @param string $message The message to log
     * @param bool $isVerbose Whether this is a verbose message
     * @param bool $isError Whether this is an error message
     */
    private function log($message, $isVerbose = false, $isError = false) {
        if ($this->quiet && $isVerbose) {
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
}

// Run the script
$linker = new DonorUserLinker($dryRun, $quiet);
$linker->processAllDonors();