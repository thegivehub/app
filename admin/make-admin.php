<?php
require_once __DIR__ . '/lib/db.php';

// Make an existing user an admin
function makeUserAdmin($username) {
    $db = new Database("givehub");
    $users = $db->getCollection('users');
    
    // Find the user
    $user = $users->findOne(['username' => $username]);
    
    if (!$user) {
        echo "User not found.\n";
        return;
    }
    
    // Add admin role if not already present
    $roles = isset($user['roles']) ? $user['roles'] : ['user'];
    if (!in_array('admin', $roles)) {
        $roles[] = 'admin';
    }
    
    // Update user
    $result = $users->updateOne(
        ['_id' => $user['_id']],
        ['$set' => ['roles' => $roles]]
    );
    
    if ($result['matched'] > 0) {
        echo "User {$username} is now an admin.\n";
    } else {
        echo "Failed to update user roles.\n";
    }
}

// Usage
// Make a user an admin
makeUserAdmin('yourusername');
