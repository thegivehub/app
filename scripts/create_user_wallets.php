<?php
/**
 * Script to create and fund Stellar wallets for all users
 * This script will:
 * 1. Find all users without wallets
 * 2. Create new Stellar keypairs for them
 * 3. Fund the wallets on testnet using Friendbot
 * 4. Store the wallet information in the database
 */

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/Collection.php';
require_once __DIR__ . '/../lib/Wallet.php';

use Soneso\StellarSDK\Crypto\KeyPair;

class WalletGenerator {
    private $db;
    private $usersCollection;
    private $walletsCollection;
    
    public function __construct() {
        // Initialize database connection
        $this->db = Database::getInstance();
        $this->usersCollection = $this->db->getCollection('users');
        $this->walletsCollection = $this->db->getCollection('wallets');
        
        // Enable MongoDB debug logging
        if (defined('MONGODB_DEBUG') && MONGODB_DEBUG) {
            MongoDB\Driver\Monitoring\addSubscriber(new class implements MongoDB\Driver\Monitoring\CommandSubscriber {
                public function commandStarted($event) {
                    if ($event instanceof MongoDB\Driver\Monitoring\CommandStartedEvent) {
                        echo "Command started: " . json_encode($event->getCommand(), JSON_PRETTY_PRINT) . "\n";
                    }
                }
                public function commandSucceeded($event) {
                    if ($event instanceof MongoDB\Driver\Monitoring\CommandSucceededEvent) {
                        echo "Command succeeded: " . json_encode($event->getReply(), JSON_PRETTY_PRINT) . "\n";
                    }
                }
                public function commandFailed($event) {
                    if ($event instanceof MongoDB\Driver\Monitoring\CommandFailedEvent) {
                        echo "Command failed: " . $event->getError()->getMessage() . "\n";
                    }
                }
            });
        }
    }
    
    /**
     * Main function to process all users
     */
    public function processUsers() {
        try {
            // Get all users without wallets
            $users = $this->getUsersWithoutWallets();
            
            if (empty($users)) {
                echo "No users found without wallets.\n";
                return;
            }
            
            echo "Found " . count($users) . " users without wallets.\n";
            
            // Process each user
            foreach ($users as $user) {
                try {
                    echo "\nProcessing user: " . $user['_id'] . "\n";
                    $this->createAndFundWallet($user);
                } catch (Exception $e) {
                    echo "Error processing user " . $user['_id'] . ": " . $e->getMessage() . "\n";
                    continue;
                }
            }
            
            echo "\nWallet creation process completed.\n";
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    /**
     * Get users who don't have wallets
     */
    private function getUsersWithoutWallets() {
        // Get all wallet user IDs
        $existingWallets = $this->walletsCollection->find(
            [],
            ['projection' => ['userId' => 1]]
        );
        
        $existingUserIds = [];
        foreach ($existingWallets as $wallet) {
            $existingUserIds[] = $wallet['userId'];
        }
        
        // Find users without wallets
        $users = [];
        $cursor = $this->usersCollection->find([
            '_id' => ['$nin' => $existingUserIds]
        ]);
        
        foreach ($cursor as $user) {
            $users[] = $user;
        }
        
        return $users;
    }
    
    /**
     * Create and fund a wallet for a user
     */
    private function createAndFundWallet($user) {
        // Generate new keypair
        $keypair = KeyPair::random();
        $publicKey = $keypair->getAccountId();
        $secretKey = $keypair->getSecretSeed();
        
        echo "Generated keypair for user " . $user['_id'] . "\n";
        echo "Public Key: " . $publicKey . "\n";

        sleep(2);

        // Fund account using Friendbot
        $result = $this->fundTestnetAccount($publicKey);
        if (!$result['success']) {
            throw new Exception("Failed to fund account: " . $result['error']);
        }
        
        echo "Account funded successfully\n";
        
        // Store wallet in database
        $wallet = [
            'userId' => $user['_id'],
            'publicKey' => $publicKey,
            'secretKey' => $secretKey,
            'network' => 'testnet',
            'status' => 'active',
            'createdAt' => new MongoDB\BSON\UTCDateTime(),
            'metadata' => [
                'lastAccessed' => new MongoDB\BSON\UTCDateTime(),
                'deviceInfo' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI Script'
            ]
        ];
        
        echo "\nAttempting to insert wallet document:\n";
        print_r($wallet);
        
        try {
            $result = $this->walletsCollection->insertOne($wallet);
            echo "\nInsert result:\n";
            print_r($result);
            
            if (!$result || !isset($result['id'])) {
                throw new Exception("Failed to store wallet in database");
            }
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            $error = $e->getMessage();
            echo "\nValidation Error Details:\n";
            print_r($e->getWriteResult()->getWriteErrors());
            throw new Exception("MongoDB Validation Error: " . $error);
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            echo "\nMongoDB Driver Error:\n";
            echo "Error Code: " . $e->getCode() . "\n";
            echo "Error Message: " . $e->getMessage() . "\n";
            throw new Exception("MongoDB Driver Error: " . $e->getMessage());
        } catch (\Exception $e) {
            throw new Exception("Database Error: " . $e->getMessage());
        }
        
        echo "Wallet stored in database\n";
    }
    
    /**
     * Fund an account on testnet using Friendbot
     */
    private function fundTestnetAccount($publicKey) {
        try {
            $response = file_get_contents(
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
                    'error' => 'Invalid response from Friendbot'
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

// Run the script
echo "Starting wallet generation process...\n";
$generator = new WalletGenerator();
$generator->processUsers(); 
