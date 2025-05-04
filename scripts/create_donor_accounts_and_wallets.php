<?php
/**
 * Script to create user accounts and wallets for test donors
 * This script will:
 * 1. Find all donors in the 'donors' collection
 * 2. Create a new user account for each donor if one doesn't exist
 * 3. Create a new Stellar wallet for each user
 * 4. Link the wallet to the user account
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/Donor.php';
require_once __DIR__ . '/../lib/User.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Soneso\StellarSDK\Crypto\KeyPair;

class DonorAccountsCreator {
    private $db;
    private $donorsCollection;
    private $usersCollection;
    private $walletsCollection;
    private $stats = [
        'totalDonors' => 0,
        'existingUsers' => 0,
        'createdUsers' => 0,
        'existingWallets' => 0,
        'createdWallets' => 0,
        'errors' => 0
    ];
    
    public function __construct() {
        // Initialize database connection
        $this->db = Database::getInstance();
        $this->donorsCollection = $this->db->getCollection('donors');
        $this->usersCollection = $this->db->getCollection('users');
        $this->walletsCollection = $this->db->getCollection('wallets');
        
        // Enable MongoDB debug logging
        if (defined('APP_DEBUG') && APP_DEBUG) {
            echo "Debug mode enabled\n";
        }
    }
    
    /**
     * Main function to process all donors
     */
    public function processDonors() {
        try {
            // Get all donors
            $donors = $this->getAllDonors();
            
            if (empty($donors)) {
                echo "No donors found in the database.\n";
                return;
            }
            
            $this->stats['totalDonors'] = count($donors);
            echo "Found " . count($donors) . " donors in the database.\n";
            
            // Process each donor
            foreach ($donors as $donor) {
                try {
                    echo "\nProcessing donor: " . $donor['name'] . " (" . $donor['email'] . ")\n";
                    
                    // Check if user already exists with this email
                    $existingUser = $this->findUserByEmail($donor['email']);
                    
                    if ($existingUser) {
                        echo "  User already exists with ID: " . $existingUser['_id'] . "\n";
                        $this->stats['existingUsers']++;
                        $userId = $existingUser['_id'];
                    } else {
                        // Create a new user account
                        $userId = $this->createUserFromDonor($donor);
                        if (!$userId) {
                            throw new Exception("Failed to create user account");
                        }
                        $this->stats['createdUsers']++;
                    }
                    
                    // Check if wallet already exists for this user
                    $existingWallet = $this->findWalletByUserId($userId);
                    
                    if ($existingWallet) {
                        echo "  Wallet already exists with public key: " . $existingWallet['publicKey'] . "\n";
                        $this->stats['existingWallets']++;
                    } else {
                        // Create a new wallet for the user
                        $walletId = $this->createAndFundWallet($userId);
                        if (!$walletId) {
                            throw new Exception("Failed to create wallet");
                        }
                        $this->stats['createdWallets']++;
                    }
                } catch (Exception $e) {
                    echo "  Error processing donor: " . $e->getMessage() . "\n";
                    $this->stats['errors']++;
                    continue;
                }
            }
            
            echo "\nProcessing completed. Summary:\n";
            echo "  Total donors processed: " . $this->stats['totalDonors'] . "\n";
            echo "  Existing users found: " . $this->stats['existingUsers'] . "\n";
            echo "  New users created: " . $this->stats['createdUsers'] . "\n";
            echo "  Existing wallets found: " . $this->stats['existingWallets'] . "\n";
            echo "  New wallets created: " . $this->stats['createdWallets'] . "\n";
            echo "  Errors encountered: " . $this->stats['errors'] . "\n";
            
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    /**
     * Get all donors from the database
     */
    private function getAllDonors() {
        $donors = [];
        $cursor = $this->donorsCollection->find([]);
        
        foreach ($cursor as $donor) {
            $donors[] = $donor;
        }
        
        return $donors;
    }
    
    /**
     * Find a user by email
     */
    private function findUserByEmail($email) {
        return $this->usersCollection->findOne(['email' => $email]);
    }
    
    /**
     * Find a wallet by user ID
     */
    private function findWalletByUserId($userId) {
        return $this->walletsCollection->findOne([
            'userId' => $userId instanceof MongoDB\BSON\ObjectId ? $userId : new MongoDB\BSON\ObjectId($userId)
        ]);
    }
    
    /**
     * Create a new user account from donor data
     * @param array $donor The donor data
     * @return string|null The created user ID or null on error
     */
    private function createUserFromDonor($donor) {
        // Generate a secure random password (donors will reset this)
        $password = bin2hex(random_bytes(8));
        
        // Extract name parts
        $nameParts = explode(' ', $donor['name'], 2);
        $firstName = $nameParts[0];
        $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
        
        // Get username from email (removing special chars)
        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $donor['email'])[0]));
        
        // Check if username already exists, append random digits if needed
        $existingUser = $this->usersCollection->findOne(['username' => $username]);
        if ($existingUser) {
            $username .= rand(100, 999);
        }
        
        // Create user data structure
        $userData = [
            'email' => $donor['email'],
            'username' => $username,
            'type' => 'donor',
            'status' => 'active',
            'auth' => [
                'passwordHash' => password_hash($password, PASSWORD_DEFAULT),
                'lastLogin' => new MongoDB\BSON\UTCDateTime(),
                'verified' => true,
                'twoFactorEnabled' => false
            ],
            'profile' => [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'displayName' => $donor['name'],
                'avatar' => null,
                'bio' => '',
                'location' => isset($donor['location']) ? $donor['location'] : null
            ],
            'preferences' => [
                'emailNotifications' => isset($donor['preferences']['newsletter']) 
                    ? ['newsletter' => $donor['preferences']['newsletter']] 
                    : ['newsletter' => true],
                'currency' => 'USD',
                'language' => 'en'
            ],
            'roles' => ['user', 'donor'],
            'created' => isset($donor['created']) 
                ? $donor['created'] 
                : new MongoDB\BSON\UTCDateTime(),
            'lastActive' => isset($donor['lastActive']) 
                ? $donor['lastActive'] 
                : new MongoDB\BSON\UTCDateTime()
        ];
        
        echo "  Creating new user account for " . $donor['name'] . "\n";
        
        try {
            $result = $this->usersCollection->insertOne($userData);
            
            if (!$result || !$result['success']) {
                throw new Exception(isset($result['error']) ? $result['error'] : "Database error while creating user");
            }
            
            $userId = $result['id'];
            echo "  User created with ID: " . $userId . "\n";
            echo "  Temporary password set: " . $password . " (donor will reset this)\n";
            
            return $userId;
        } catch (Exception $e) {
            echo "  Error creating user: " . $e->getMessage() . "\n";
            return null;
        }
    }
    
    /**
     * Create and fund a Stellar wallet for a user
     */
    private function createAndFundWallet($userId) {
        // Generate a new Stellar keypair
        $keypair = Keypair::random();
        $publicKey = $keypair->getPublicKey();
        $secretKey = $keypair->getSecretSeed();
        
        echo "  Generating new Stellar wallet for user " . $userId . "\n";
        echo "  Public Key: " . $publicKey . "\n";
        
        // Fund the account on testnet
        $fundResult = $this->fundTestnetAccount($publicKey);
        if (!$fundResult['success']) {
            echo "  Warning: Failed to fund account: " . $fundResult['error'] . "\n";
            echo "  Continuing with wallet creation anyway...\n";
        } else {
            echo "  Account funded successfully on testnet\n";
        }
        
        // Store wallet in database
        $wallet = [
            'userId' => $userId instanceof MongoDB\BSON\ObjectId ? $userId : new MongoDB\BSON\ObjectId($userId),
            'publicKey' => $publicKey,
            'secretKey' => $secretKey,
            'network' => 'testnet',
            'status' => 'active',
            'createdAt' => new MongoDB\BSON\UTCDateTime(),
            'lastAccessed' => new MongoDB\BSON\UTCDateTime()
        ];
        
        try {
            $result = $this->walletsCollection->insertOne($wallet);
            
            if (!$result || !$result['success']) {
                throw new Exception(isset($result['error']) ? $result['error'] : "Database error while creating wallet");
            }
            
            $walletId = $result['id'];
            echo "  Wallet created with ID: " . $walletId . "\n";
            
            return $walletId;
        } catch (Exception $e) {
            echo "  Error creating wallet: " . $e->getMessage() . "\n";
            return null;
        }
    }
    
    /**
     * Fund an account on testnet using Friendbot
     */
    private function fundTestnetAccount($publicKey) {
        try {
            $response = @file_get_contents(
                "https://friendbot.stellar.org?addr=" . urlencode($publicKey)
            );
            
            if (!$response) {
                return [
                    'success' => false,
                    'error' => 'No response from Friendbot'
                ];
            }
            
            $result = json_decode($response, true);
            
            if (isset($result['hash'])) {
                return [
                    'success' => true,
                    'hash' => $result['hash']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => json_encode($result)
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

// Execute if run from command line
if (php_sapi_name() === 'cli') {
    echo "Starting donor accounts and wallets creation...\n";
    $creator = new DonorAccountsCreator();
    $creator->processDonors();
}