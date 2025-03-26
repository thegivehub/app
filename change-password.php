<?php
/**
 * GiveHub User Password Change Script
 * 
 * This script allows admin users to change a user's password from the command line
 * by providing either a username or email address.
 * 
 * Usage:
 *   php change-password.php --username=johndoe --password=NewPassword123
 *   php change-password.php --email=john@example.com --password=NewPassword123
 *   php change-password.php --username=johndoe --generate-password
 * 
 * Options:
 *   --username=USERNAME    Specify user by username
 *   --email=EMAIL          Specify user by email address
 *   --password=PASSWORD    New password to set
 *   --generate-password    Generate a random secure password
 *   --help                 Display this help message
 */

require_once __DIR__ . '/lib/db.php';

// Function to display usage information
function showUsage() {
    echo "\nGiveHub User Password Change Script\n";
    echo "===================================\n\n";
    echo "Usage:\n";
    echo "  php change-password.php --username=johndoe --password=NewPassword123\n";
    echo "  php change-password.php --email=john@example.com --password=NewPassword123\n";
    echo "  php change-password.php --username=johndoe --generate-password\n\n";
    echo "Options:\n";
    echo "  --username=USERNAME    Specify user by username\n";
    echo "  --email=EMAIL          Specify user by email address\n";
    echo "  --password=PASSWORD    New password to set\n";
    echo "  --generate-password    Generate a random secure password\n";
    echo "  --help                 Display this help message\n";
    echo "\n";
    exit(0);
}

// Function to generate a secure random password
function generateSecurePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
    $password = '';
    
    // Ensure at least one of each: uppercase, lowercase, number, special char
    $password .= $chars[rand(26, 51)]; // Uppercase
    $password .= $chars[rand(0, 25)];  // Lowercase
    $password .= $chars[rand(52, 61)]; // Number
    $password .= $chars[rand(62, strlen($chars) - 1)]; // Special char
    
    // Fill up the rest of the password
    for ($i = 4; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    // Shuffle the password characters
    $password = str_shuffle($password);
    
    return $password;
}

// Function to change a user's password
function changeUserPassword($identifier, $identifierType, $newPassword) {
    try {
        $db = new Database("givehub");
        $users = $db->getCollection('users');
        
        // Find the user
        $filter = [];
        if ($identifierType === 'username') {
            $filter = ['username' => $identifier];
        } else {
            $filter = ['email' => $identifier];
        }
        
        $user = $users->findOne($filter);
        
        if (!$user) {
            echo "Error: User not found with {$identifierType} '{$identifier}'.\n";
            exit(1);
        }
        
        // Create password hash
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update the user's password
        $result = $users->updateOne(
            ['_id' => $user['_id']],
            ['$set' => [
                'auth.passwordHash' => $passwordHash,
                'updated' => date('Y-m-d H:i:s')
            ]]
        );
        
        if ($result['matched'] && $result['modified']) {
            echo "Password successfully updated for user: {$user['username']} ({$user['email']}).\n";
            echo "New password: {$newPassword}\n";
            return true;
        } else {
            echo "Error: Failed to update password. No database changes were made.\n";
            exit(1);
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Parse command line arguments
$options = getopt('', ['username:', 'email:', 'password:', 'generate-password', 'help']);

// Show help if requested
if (isset($options['help'])) {
    showUsage();
}

// Validate inputs
if (!isset($options['username']) && !isset($options['email'])) {
    echo "Error: You must specify either a username or email address.\n";
    showUsage();
}

if (!isset($options['password']) && !isset($options['generate-password'])) {
    echo "Error: You must either provide a password or use the generate-password option.\n";
    showUsage();
}

// Determine identifier type and value
$identifierType = isset($options['username']) ? 'username' : 'email';
$identifier = isset($options['username']) ? $options['username'] : $options['email'];

// Determine new password
$newPassword = isset($options['password']) 
    ? $options['password'] 
    : generateSecurePassword();

// Validate the new password if provided manually
if (isset($options['password'])) {
    if (strlen($newPassword) < 8) {
        echo "Error: Password must be at least 8 characters long.\n";
        exit(1);
    }
}

// Change the password
changeUserPassword($identifier, $identifierType, $newPassword);

// Exit cleanly
exit(0);
