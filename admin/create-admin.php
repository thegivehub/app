<?php
require_once __DIR__ . '/../lib/db.php';

// Create an admin user
function createAdminUser($username, $password, $email, $firstName, $lastName) {
    $db = new Database("givehub");
    $users = $db->getCollection('users');
    
    // Check if the user already exists
    $existingUser = $users->findOne(['username' => $username]);
    if ($existingUser) {
        echo "Admin user already exists.\n";
        return;
    }
    
    // Create password hash
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Prepare user data
    $userData = [
        'username' => $username,
        'email' => $email,
        'status' => 'active',
        'personalInfo' => [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email
        ],
        'displayName' => "$firstName $lastName",
        'auth' => [
            'passwordHash' => $passwordHash,
            'verified' => true
        ],
        'roles' => ['admin', 'user'],
        'created' => date('Y-m-d H:i:s'),
        'updated' => date('Y-m-d H:i:s')
    ];
    
    // Insert the admin user
    $result = $users->insertOne($userData);
    
    if ($result['success']) {
        echo "Admin user created successfully.\n";
    } else {
        echo "Failed to create admin user.\n";
    }
}

// Create an admin user
createAdminUser('admin', 'Iaavsw1!', 'admin@thegivehub.com', 'Admin', 'User');
