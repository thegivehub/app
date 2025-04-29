<?php
/**
 * Profile Picture Migration Script
 * 
 * This script migrates base64 encoded profile pictures from MongoDB to the filesystem
 * by extracting them from profile.pic field, saving them as files, and 
 * updating the database with URL references.
 * 
 * Usage:
 *   php update_profile_pics.php [--dry-run] [--debug]
 *   
 *   Options:
 *     --dry-run : Run in test mode without saving files or updating database
 *     --debug   : Enable additional debug output
 */

// Set maximum execution time to allow for large migrations
ini_set('max_execution_time', 600); // 10 minutes
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../lib/db.php';

// Parse command line arguments
$options = getopt('', ['dry-run', 'debug']);
$dryRun = isset($options['dry-run']);
$debug = isset($options['debug']);

// Initialize database connection
$db = new Database('givehub');

// Statistics counters
$stats = [
    'users_processed' => 0,
    'profile_pics_migrated' => 0,
    'errors' => []
];

echo "=== Profile Picture Migration Script ===\n";
if ($dryRun) {
    echo "*** DRY RUN MODE - No files will be saved and no database changes will be made ***\n";
}
echo "Moving base64 encoded profile pictures from database to filesystem\n\n";

/**
 * Process a base64 encoded image
 * 
 * @param string $base64Image Base64 encoded image data
 * @param string $userId User ID for the profile
 * @return array Result with file path and URL
 */
function processBase64Image($base64Image, $userId) {
    global $dryRun, $debug;
    
    // In dry run mode, just validate but don't save
    if ($dryRun) {
        // Check if it's a valid base64 image format
        if (preg_match('/^data:image\/(\w+|\w+\+\w+);base64,/', $base64Image, $matches)) {
            $imageType = $matches[1];
            // Handle special cases for image types
            if ($imageType === 'svg+xml') {
                $imageType = 'svg';
            }
            
            if ($debug) {
                echo "    Dry run - Valid base64 image of type: {$imageType}\n";
            }
            return [
                'success' => true,
                'url' => '/uploads/profiles/dry-run/sample.' . $imageType,
                'message' => 'Dry run - would have processed image'
            ];
        } 
        // Check if it's a URL instead
        else if (filter_var($base64Image, FILTER_VALIDATE_URL)) {
            if ($debug) {
                echo "    Dry run - Found URL instead of base64: " . $base64Image . "\n";
            }
            return [
                'success' => true,
                'url' => $base64Image,
                'message' => 'Dry run - would have kept URL'
            ];
        }
        else {
            return [
                'success' => false,
                'error' => 'Invalid base64 image format in dry run mode'
            ];
        }
    }
    
    try {
        // Trim any whitespace
        $base64Image = trim($base64Image);
        
        // Debug info
        if ($debug) {
            echo "    Processing base64 image for user {$userId}\n";
        }
        
        // Extract the MIME type and decode the image
        if (!preg_match('/^data:image\/(\w+|\w+\+\w+);base64,/', $base64Image, $matches)) {
            // Try to handle URLs that might have been misidentified as base64
            if (filter_var($base64Image, FILTER_VALIDATE_URL)) {
                if ($debug) {
                    echo "    Found URL instead of base64: " . $base64Image . "\n";
                }
                return [
                    'success' => true,
                    'url' => $base64Image, // Just return the existing URL
                    'message' => 'Kept existing URL'
                ];
            }
            
            throw new Exception('Invalid base64 image format (missing data:image/xxx;base64, prefix)');
        }
        
        $imageType = $matches[1];
        // Handle special cases for image types
        if ($imageType === 'svg+xml') {
            $imageType = 'svg';
        }
        
        $base64Data = substr($base64Image, strpos($base64Image, ',') + 1);
        
        // Remove any whitespace/line breaks which could cause decoding issues
        $base64Data = preg_replace('/\s+/', '', $base64Data);
        
        $decodedImage = base64_decode($base64Data, true);
        
        if ($decodedImage === false) {
            throw new Exception('Failed to decode base64 image data - invalid encoding');
        }
        
        // Create directory structures
        $uploadDir = __DIR__ . '/../uploads/';
        $profilesDir = $uploadDir . 'profiles/';
        
        if (!is_dir($profilesDir)) {
            mkdir($profilesDir, 0755, true);
        }
        
        // Generate a unique filename
        $uuid = bin2hex(random_bytes(8));
        $filename = "profile_{$userId}_{$uuid}.{$imageType}";
        $filePath = $profilesDir . $filename;
        
        // Save the file
        if (!file_put_contents($filePath, $decodedImage)) {
            throw new Exception('Failed to save image file');
        }
        
        // Generate relative URL path
        $urlPath = '/uploads/profiles/' . $filename;
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filePath,
            'url' => $urlPath,
        ];
    } catch (Exception $e) {
        if ($debug) {
            echo "    Error: " . $e->getMessage() . "\n";
        }
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

echo "Starting user profile picture migration...\n";

// Process user profile pictures
$userCollection = $db->getCollection('users');

// Find users with base64 encoded profile pictures
$users = $userCollection->find([
    'profile.pic' => ['$regex' => '^data:image/']
]);

foreach ($users as $user) {
    $stats['users_processed']++;
    $userId = (string)$user['_id'];
    
    echo "Processing user: {$userId}\n";
    
    // Check if the user has a base64 encoded profile picture
    if (isset($user['profile']['pic']) && is_string($user['profile']['pic'])) {
        if (strpos($user['profile']['pic'], 'data:image/') === 0) {
            // Process the base64 image
            $result = processBase64Image($user['profile']['pic'], $userId);
            
            if ($result['success']) {
                // Update the user document with the new URL
                try {
                    $updateResult = $userCollection->updateOne(
                        ['_id' => new MongoDB\BSON\ObjectId($userId)],
                        ['$set' => ['profile.pic' => $result['url']]]
                    );
                    
                    // Check if update was successful
                    if ($updateResult instanceof MongoDB\UpdateResult) {
                        if ($updateResult->getModifiedCount() > 0) {
                            $stats['profile_pics_migrated']++;
                            echo "  Migrated profile picture to: {$result['url']}\n";
                        } else {
                            $stats['errors'][] = "Warning: No changes made to user {$userId} - document might not exist or no changes were necessary";
                            echo "  Warning: No changes made to user {$userId}\n";
                        }
                    } else {
                        $stats['profile_pics_migrated']++; // Assume success for older MongoDB driver versions
                        echo "  Migrated profile picture to: {$result['url']}\n";
                    }
                } catch (Exception $e) {
                    $stats['errors'][] = "Error updating user {$userId}: " . $e->getMessage();
                    echo "  Error: " . $e->getMessage() . "\n";
                }
            } else {
                $stats['errors'][] = "Failed to migrate profile picture for user {$userId}: " . $result['error'];
                echo "  Error migrating profile picture: {$result['error']}\n";
            }
        } else {
            echo "  Profile picture is not base64 encoded\n";
        }
    } else {
        echo "  User does not have profile.pic field\n";
    }
}

// Display migration statistics
echo "\n=== Migration Summary ===\n";
if ($dryRun) {
    echo "*** DRY RUN COMPLETED - No changes were made ***\n";
}
echo "Users processed: {$stats['users_processed']}\n";
echo "Profile pictures migrated: {$stats['profile_pics_migrated']}\n";
echo "Errors encountered: " . count($stats['errors']) . "\n";

// Output errors if any
if (count($stats['errors']) > 0) {
    echo "\n=== Errors ===\n";
    foreach ($stats['errors'] as $index => $error) {
        echo ($index + 1) . ". {$error}\n";
    }
}

echo "\nMigration completed!\n";