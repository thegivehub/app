<?php
/**
 * Database Image Migration Script
 * 
 * This script moves base64 encoded images from MongoDB to the filesystem
 * and replaces the database entries with URL references to the files.
 * 
 * Usage:
 *   php migrate_images.php [--dry-run] [--debug]
 *   
 *   Options:
 *     --dry-run : Run in test mode without saving files or updating database
 *     --debug   : Enable additional debug output
 */

// Set maximum execution time to allow for large migrations
ini_set('max_execution_time', 600); // 10 minutes
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/DocumentUploader.php';
require_once __DIR__ . '/../lib/Auth.php';

// Parse command line arguments
$options = getopt('', ['dry-run', 'debug', 'admin']);
$dryRun = isset($options['dry-run']);
$debug = isset($options['debug']);
$adminMode = isset($options['admin']);

// Initialize
$db = new Database();
$auth = new Auth();

// Override authentication if in admin mode
if ($adminMode) {
    // Create a mock authentication method for the migration script
    class MigrationAuth extends Auth {
        public function getUserIdFromToken() {
            // Return a fixed admin ID for migration purposes
            return 'migration-admin';
        }
    }
    
    // Replace the auth instance with our migration-specific one
    $auth = new MigrationAuth();
    echo "Running in admin mode (authentication bypassed)\n";
}

// Create a DocumentUploader with auth
$documentUploader = new DocumentUploader($auth);

// Statistics counters
$stats = [
    'campaigns_processed' => 0,
    'campaign_images_migrated' => 0,
    'gallery_images_migrated' => 0,
    'users_processed' => 0,
    'profile_images_migrated' => 0,
    'errors' => []
];

echo "=== Image Migration Script ===\n";
if ($dryRun) {
    echo "*** DRY RUN MODE - No files will be saved and no database changes will be made ***\n";
}
echo "Moving base64 encoded images from database to filesystem\n\n";

/**
 * Custom function to process a base64 encoded image without requiring authentication
 * 
 * @param string $base64Image Base64 encoded image data
 * @param string $resourceType Type of resource (campaign, profile, document)
 * @param string $resourceId ID of the resource (campaign ID, user ID)
 * @return array Result with file path and URL
 */
function migrateBase64Image($base64Image, $resourceType, $resourceId = null) {
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
                'url' => '/uploads/dry-run/sample.' . $imageType,
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
            echo "    Processing base64 image for {$resourceType}\n";
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
        $resourceDir = $uploadDir . $resourceType . 's/';
        
        if (!is_dir($resourceDir)) {
            mkdir($resourceDir, 0755, true);
        }
        
        // Resource-specific directory handling
        if ($resourceType === 'campaign' && $resourceId) {
            $resourceDir .= $resourceId . '/';
            if (!is_dir($resourceDir)) {
                mkdir($resourceDir, 0755, true);
            }
        }
        
        // Generate a unique filename
        $uuid = bin2hex(random_bytes(8));
        $filename = "{$resourceType}_{$uuid}.{$imageType}";
        $filePath = $resourceDir . $filename;
        
        // Save the file
        if (!file_put_contents($filePath, $decodedImage)) {
            throw new Exception('Failed to save image file');
        }
        
        // Generate relative URL path
        $urlPath = '/uploads/' . $resourceType . 's/';
        if ($resourceType === 'campaign' && $resourceId) {
            $urlPath .= $resourceId . '/';
        }
        $urlPath .= $filename;
        
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

/**
 * Extract and save base64 image to filesystem
 * 
 * @param string $base64Image Base64 encoded image
 * @param string $resourceType Type of resource (campaign, profile, etc.)
 * @param string|null $resourceId ID of the resource
 * @return array Success status and URL to saved file
 */
function processBase64Image($base64Image, $resourceType, $resourceId = null) {
    global $documentUploader, $dryRun, $debug;
    
    // Use our custom migration function that doesn't require authentication
    return migrateBase64Image($base64Image, $resourceType, $resourceId);
}

// Helper function to process image arrays
function processImageArray($images, $campaignId) {
    global $stats;
    $processedImages = [];
    
    if (!is_array($images)) {
        return $processedImages;
    }
    
    foreach ($images as $item) {
        $processedItem = [];
        
        // Case 1: Simple string URL
        if (is_string($item)) {
            if (strpos($item, 'data:image/') === 0) {
                // Convert base64 to file
                $result = processBase64Image($item, 'campaign', $campaignId);
                if ($result['success']) {
                    $processedItem['url'] = $result['url'];
                    $stats['gallery_images_migrated']++;
                } else {
                    $stats['errors'][] = "Failed to migrate gallery image for campaign {$campaignId}";
                    continue;
                }
            } else {
                // Keep existing URL
                $processedItem['url'] = $item;
            }
        }
        // Case 2: Object with image/url and metadata
        else if (is_array($item)) {
            // Copy any existing metadata
            foreach ($item as $key => $value) {
                if ($key !== 'image' && $key !== 'url') {
                    $processedItem[$key] = $value;
                }
            }
            
            // Handle base64 image if present
            if (isset($item['image']) && is_string($item['image']) && 
                strpos($item['image'], 'data:image/') === 0) {
                $result = processBase64Image($item['image'], 'campaign', $campaignId);
                if ($result['success']) {
                    $processedItem['url'] = $result['url'];
                    $stats['gallery_images_migrated']++;
                } else {
                    $stats['errors'][] = "Failed to migrate gallery image for campaign {$campaignId}";
                    continue;
                }
            }
            // Keep existing URL if present
            else if (isset($item['url'])) {
                $processedItem['url'] = $item['url'];
            }
            // Keep existing image if it's not base64
            else if (isset($item['image']) && !strpos($item['image'], 'data:image/') === 0) {
                $processedItem['url'] = $item['image'];
            }
        }
        
        // Add timestamp if not present
        if (!isset($processedItem['uploadedAt'])) {
            $processedItem['uploadedAt'] = new MongoDB\BSON\UTCDateTime();
        }
        
        $processedImages[] = $processedItem;
    }
    
    return $processedImages;
}

echo "Starting campaign image migration...\n";

// Process campaign main images
$campaignCollection = $db->getCollection('campaigns');
$campaigns = $campaignCollection->find([
    '$or' => [
        ['image' => ['$regex' => '^data:image/']],
        ['coverImage' => ['$regex' => '^data:image/']],
        ['media' => ['$exists' => true]],
        ['images' => ['$exists' => true]],
        ['gallery' => ['$exists' => true]]
    ]
]);

foreach ($campaigns as $campaign) {
    $stats['campaigns_processed']++;
    $campaignId = (string)$campaign['_id'];
    $updated = false;
    $updateData = [];
    
    echo "Processing campaign: {$campaignId}\n";
    
    // Process main image if it's base64
    if (isset($campaign['image']) && is_string($campaign['image']) && 
        strpos($campaign['image'], 'data:image/') === 0) {
        
        $result = processBase64Image($campaign['image'], 'campaign', $campaignId);
        
        if ($result['success']) {
            $updateData['imageUrl'] = $result['url'];
            $updateData['image'] = null; // Set to null to save space
            $stats['campaign_images_migrated']++;
            $updated = true;
            echo "  Migrated main image to: {$result['url']}\n";
        } else {
            $stats['errors'][] = "Failed to migrate image for campaign {$campaignId}: " . $result['error'];
            echo "  Error migrating main image: {$result['error']}\n";
        }
    }
    
    // Process cover image if different from main and it's base64
    if (isset($campaign['coverImage']) && is_string($campaign['coverImage']) && 
        strpos($campaign['coverImage'], 'data:image/') === 0) {
        
        $result = processBase64Image($campaign['coverImage'], 'campaign', $campaignId);
        
        if ($result['success']) {
            $updateData['coverImageUrl'] = $result['url'];
            $updateData['coverImage'] = null; // Set to null to save space
            $stats['campaign_images_migrated']++;
            $updated = true;
            echo "  Migrated cover image to: {$result['url']}\n";
        } else {
            $stats['errors'][] = "Failed to migrate cover image for campaign {$campaignId}: " . $result['error'];
            echo "  Error migrating cover image: {$result['error']}\n";
        }
    }
    
    // Process all possible image arrays
    $imageArrayFields = ['gallery', 'media', 'images'];
    foreach ($imageArrayFields as $field) {
        if (isset($campaign[$field]) && is_array($campaign[$field])) {
            $processedImages = processImageArray($campaign[$field], $campaignId);
            if (!empty($processedImages)) {
                // Always store in images field for standardization
                if (isset($updateData['images']) && is_array($updateData['images'])) {
                    $updateData['images'] = array_merge($updateData['images'], $processedImages);
                } else {
                    $updateData['images'] = $processedImages;
                }
                // Null out the old field if it's different from 'images'
                if ($field !== 'images') {
                    $updateData[$field] = null;
                }
                $updated = true;
                echo "  Migrated {$field} array to images\n";
            }
        }
    }
    
    // Update the campaign if changes were made
    if ($updated) {
        try {
            // Prepare update operations
            $updateOps = ['$set' => $updateData];
            
            // Add $unset operation only if there are fields to unset
            $fieldsToUnset = array_diff(['gallery', 'media'], array_keys($updateData));
            if (!empty($fieldsToUnset)) {
                $unsetFields = array_fill_keys($fieldsToUnset, '');
                $updateOps['$unset'] = $unsetFields;
            }

            if ($debug) {
                echo "  Debug - Update operations for campaign {$campaignId}:\n";
                echo "  " . json_encode($updateOps, JSON_PRETTY_PRINT) . "\n";
            }

            $result = $campaignCollection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($campaignId)],
                $updateOps
            );
            
            // Check if update was successful using modifiedCount
            if ($result instanceof MongoDB\UpdateResult) {
                if ($result->getModifiedCount() > 0) {
                    echo "  Successfully updated campaign {$campaignId}\n";
                } else {
                    $stats['errors'][] = "Warning: No changes made to campaign {$campaignId} - document might not exist or no changes were necessary";
                    echo "  Warning: No changes made to campaign {$campaignId}\n";
                }
            } else {
                $stats['errors'][] = "Failed to update campaign {$campaignId} - unexpected result type: " . gettype($result);
                echo "  Error: Unexpected result type when updating campaign {$campaignId}\n";
            }
        } catch (MongoDB\Driver\Exception\Exception $e) {
            $stats['errors'][] = "MongoDB error updating campaign {$campaignId}: " . $e->getMessage();
            echo "  Error: MongoDB exception when updating campaign {$campaignId}: " . $e->getMessage() . "\n";
        } catch (Exception $e) {
            $stats['errors'][] = "Error updating campaign {$campaignId}: " . $e->getMessage();
            echo "  Error: General exception when updating campaign {$campaignId}: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nStarting user profile image migration...\n";

// Process user profile images
$userCollection = $db->getCollection('users');
$users = $userCollection->find([
    '$or' => [
        ['profile.avatar' => ['$regex' => '^data:image/']],
        ['avatar' => ['$regex' => '^data:image/']]
    ]
]);

foreach ($users as $user) {
    $stats['users_processed']++;
    $userId = (string)$user['_id'];
    $updated = false;
    $updateData = [];
    
    echo "Processing user: {$userId}\n";
    
    // Check profile.avatar path
    if (isset($user['profile']['avatar']) && is_string($user['profile']['avatar'])) {
        // Debug output to see the actual value format
        echo "  Debug - Avatar value: " . substr($user['profile']['avatar'], 0, 50) . "...\n";
        
        // Check if it looks like a base64 image (more lenient check)
        if (strpos($user['profile']['avatar'], 'data:image/') === 0) {
            // We'll let processBase64Image handle the specific validation
            $result = processBase64Image($user['profile']['avatar'], 'profile', $userId);
            
            if ($result['success']) {
                $updateData['profile.avatar'] = $result['url'];
                $stats['profile_images_migrated']++;
                $updated = true;
                echo "  Migrated profile image to: {$result['url']}\n";
            } else {
                $stats['errors'][] = "Failed to migrate profile image for user {$userId}: " . $result['error'];
                echo "  Error migrating profile image: {$result['error']}\n";
            }
        } else if ($debug) {
            echo "  Not a base64 image: " . substr($user['profile']['avatar'], 0, 30) . "...\n";
        }
    }
    
    // Check avatar path (older schemas)
    if (isset($user['avatar']) && is_string($user['avatar'])) {
        // Debug output to see the actual value format
        echo "  Debug - Avatar value: " . substr($user['avatar'], 0, 50) . "...\n";
        
        // Check if it looks like a base64 image (more lenient check)
        if (strpos($user['avatar'], 'data:image/') === 0) {
            // We'll let processBase64Image handle the specific validation
            $result = processBase64Image($user['avatar'], 'profile', $userId);
            
            if ($result['success']) {
                $updateData['avatar'] = $result['url'];
                $stats['profile_images_migrated']++;
                $updated = true;
                echo "  Migrated avatar image to: {$result['url']}\n";
            } else {
                $stats['errors'][] = "Failed to migrate avatar image for user {$userId}: " . $result['error'];
                echo "  Error migrating avatar image: {$result['error']}\n";
            }
        } else if ($debug) {
            echo "  Not a base64 image: " . substr($user['avatar'], 0, 30) . "...\n";
        }
    }
    
    // Update the user if changes were made
    if ($updated) {
        $result = $userCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($userId)],
            ['$set' => $updateData]
        );
        
        if (!$result['success']) {
            $stats['errors'][] = "Failed to update user {$userId} in database";
            echo "  Error updating user in database\n";
        }
    }
}

// Display migration statistics
echo "\n=== Migration Summary ===\n";
if ($dryRun) {
    echo "*** DRY RUN COMPLETED - No changes were made ***\n";
}
echo "Campaigns processed: {$stats['campaigns_processed']}\n";
echo "Campaign main images migrated: {$stats['campaign_images_migrated']}\n";
echo "Gallery images migrated: {$stats['gallery_images_migrated']}\n";
echo "Users processed: {$stats['users_processed']}\n";
echo "Profile images migrated: {$stats['profile_images_migrated']}\n";
echo "Total images migrated: " . ($stats['campaign_images_migrated'] + $stats['gallery_images_migrated'] + $stats['profile_images_migrated']) . "\n";
echo "Errors encountered: " . count($stats['errors']) . "\n";

// Output errors if any
if (count($stats['errors']) > 0) {
    echo "\n=== Errors ===\n";
    foreach ($stats['errors'] as $index => $error) {
        echo ($index + 1) . ". {$error}\n";
    }
}

echo "\nMigration completed!\n"; 