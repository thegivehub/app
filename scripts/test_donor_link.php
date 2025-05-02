<?php
/**
 * Test Donor Link Script
 * 
 * This script tests linking a single donor to a user account.
 * It helps diagnose issues with the linking process in isolation.
 * 
 * Usage: php scripts/test_donor_link.php <donor_id>
 */

// Load required files
require_once __DIR__ . '/../lib/db.php';

// Check for donor ID argument
if ($argc < 2) {
    echo "Usage: php test_donor_link.php <donor_id>\n";
    exit(1);
}

$donorId = $argv[1];

// Initialize database connection
$db = new Database();

// Main test function
function testDonorLink($db, $donorId) {
    echo "Testing donor link process for donor ID: $donorId\n";
    echo "----------------------------------------\n";
    
    // Step 1: Fetch the donor
    $donorCollection = $db->db->selectCollection('donors');
    $donor = $donorCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($donorId)]);
    
    if (!$donor) {
        echo "ERROR: Donor not found with ID: $donorId\n";
        return;
    }
    
    echo "Found donor:\n";
    echo " - ID: " . $donorId . "\n";
    
    // Extract donor email
    $email = null;
    if (isset($donor['email'])) {
        $email = $donor['email'];
    } elseif (isset($donor['donor']) && isset($donor['donor']['email'])) {
        $email = $donor['donor']['email'];
    } elseif (isset($donor['donorInfo']) && isset($donor['donorInfo']['email'])) {
        $email = $donor['donorInfo']['email'];
    }
    
    if (!$email) {
        echo "ERROR: Donor has no email address\n";
        return;
    }
    
    echo " - Email: $email\n";
    
    // Get donor name
    $name = null;
    if (isset($donor['name'])) {
        $name = $donor['name'];
    } elseif (isset($donor['donor']) && isset($donor['donor']['name'])) {
        $name = $donor['donor']['name'];
    } elseif (isset($donor['donorInfo']) && isset($donor['donorInfo']['name'])) {
        $name = $donor['donorInfo']['name'];
    }
    
    if (!$name) {
        $name = "Donor " . substr($donorId, -6);
    }
    
    echo " - Name: $name\n";
    
    // Check if donor is already linked
    if (isset($donor['userId'])) {
        echo "NOTE: Donor is already linked to user: " . $donor['userId'] . "\n";
    }
    
    // Clean and validate email
    $email = trim($email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "ERROR: Invalid email address: $email\n";
        return;
    }
    
    // Step 2: Check for existing user
    $userCollection = $db->db->selectCollection('users');
    $existingUser = $userCollection->findOne(['email' => $email]);
    
    if ($existingUser) {
        echo "\nFound existing user with matching email:\n";
        echo " - User ID: " . $existingUser['_id'] . "\n";
        echo " - Username: " . ($existingUser['username'] ?? 'No username') . "\n";
        
        // For testing, we'll perform the link even if already linked
        echo "\nLinking donor to existing user...\n";
        $userId = (string) $existingUser['_id'];
        linkDonorToUser($db, $donorId, $userId);
    } else {
        echo "\nNo existing user found with email: $email\n";
        
        // Parse name into first and last name
        $nameParts = explode(' ', $name, 2);
        $firstName = $nameParts[0];
        $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
        
        echo "Creating new user account with:\n";
        echo " - Email: $email\n";
        echo " - Name: $firstName $lastName\n";
        
        $userId = createUserForDonor($db, $email, $firstName, $lastName, $donorId);
        
        if ($userId) {
            echo "Successfully created user with ID: $userId\n";
            
            // Link the donor to the new user
            echo "\nLinking donor to new user...\n";
            linkDonorToUser($db, $donorId, $userId);
        } else {
            echo "Failed to create user account.\n";
            
            // Try with modified email
            $emailParts = explode('@', $email);
            if (count($emailParts) === 2) {
                $modifiedEmail = $emailParts[0] . '+donor@' . $emailParts[1];
                echo "\nRetrying with modified email: $modifiedEmail\n";
                
                $userId = createUserForDonor($db, $modifiedEmail, $firstName, $lastName, $donorId);
                
                if ($userId) {
                    echo "Successfully created user with modified email and ID: $userId\n";
                    
                    // Link the donor to the new user
                    echo "\nLinking donor to new user...\n";
                    linkDonorToUser($db, $donorId, $userId);
                } else {
                    echo "Failed to create user account with modified email.\n";
                }
            }
        }
    }
    
    // Final verification
    echo "\nVerifying final state:\n";
    $updatedDonor = $donorCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($donorId)]);
    
    if ($updatedDonor && isset($updatedDonor['userId'])) {
        echo "SUCCESS: Donor is now linked to user: " . $updatedDonor['userId'] . "\n";
        
        // Verify the user side of the relationship
        $linkedUser = $userCollection->findOne(['_id' => $updatedDonor['userId']]);
        if ($linkedUser && isset($linkedUser['donorId'])) {
            echo "SUCCESS: User is properly linked back to donor: " . $linkedUser['donorId'] . "\n";
        } else {
            echo "WARNING: User is not properly linked back to donor.\n";
        }
    } else {
        echo "ERROR: Donor is still not linked to any user.\n";
    }
}

/**
 * Create a new user account for a donor
 * @param Database $db Database connection
 * @param string $email User email
 * @param string $firstName User first name
 * @param string $lastName User last name
 * @param string $donorId Associated donor ID
 * @return string|null User ID if successful, null otherwise
 */
function createUserForDonor($db, $email, $firstName, $lastName, $donorId) {
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
        $userCollection = $db->db->selectCollection('users');
        
        while ($userCollection->countDocuments(['username' => $username]) > 0) {
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
        
        echo "Inserting new user with username: $username\n";
        
        // Insert the new user and get the ID
        $insertResult = $userCollection->insertOne($newUser);
        return (string) $insertResult->getInsertedId();
    } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
        echo "MongoDB BulkWriteException: " . $e->getMessage() . "\n";
        
        // Check for duplicate key errors
        if (strpos($e->getMessage(), 'duplicate key error') !== false) {
            if (strpos($e->getMessage(), 'email') !== false) {
                echo "A user with email $email already exists\n";
            } else if (strpos($e->getMessage(), 'username') !== false) {
                echo "A user with username $username already exists\n";
            }
        }
        
        return null;
    } catch (Exception $e) {
        echo "General exception: " . $e->getMessage() . "\n";
        return null;
    }
}

/**
 * Link a donor to a user by updating both documents
 * @param Database $db Database connection
 * @param string $donorId Donor ID
 * @param string $userId User ID
 * @return bool Success indicator
 */
function linkDonorToUser($db, $donorId, $userId) {
    try {
        // Update donor with userId
        $donorCollection = $db->db->selectCollection('donors');
        $result = $donorCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($donorId)],
            ['$set' => [
                'userId' => new MongoDB\BSON\ObjectId($userId),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
        
        echo "Donor update - matched: " . $result->getMatchedCount() . ", modified: " . $result->getModifiedCount() . "\n";
        
        // Get existing user info
        $userCollection = $db->db->selectCollection('users');
        $user = $userCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
        
        // Get existing user roles if any
        $roles = ['donor']; // Default to donor role
        if ($user && isset($user['roles']) && is_array($user['roles'])) {
            if (!in_array('donor', $user['roles'])) {
                $roles = array_merge($user['roles'], ['donor']);
            } else {
                $roles = $user['roles'];
            }
        }
        
        // Update user with donorId if it doesn't already have one
        if (!isset($user['donorId'])) {
            $userResult = $userCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                ['$set' => [
                    'donorId' => new MongoDB\BSON\ObjectId($donorId),
                    'updatedAt' => new MongoDB\BSON\UTCDateTime(),
                    'roles' => $roles
                ]]
            );
            
            echo "User update - matched: " . $userResult->getMatchedCount() . ", modified: " . $userResult->getModifiedCount() . "\n";
        } else {
            echo "User already has donorId: " . $user['donorId'] . "\n";
        }
        
        return $result->getModifiedCount() > 0 || $result->getMatchedCount() > 0;
    } catch (Exception $e) {
        echo "Error linking donor to user: " . $e->getMessage() . "\n";
        return false;
    }
}

// Run the test
testDonorLink($db, $donorId);
echo "\nTest completed.\n";