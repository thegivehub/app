<?php
/**
 * Script to update the wallets collection schema
 * This will modify the schema to accept ObjectId for userId
 */

require_once __DIR__ . '/../lib/db.php';

try {
    $db = Database::getInstance();
    
    // Get the raw MongoDB database instance
    $database = $db->db;
    
    // Update collection with new schema
    $database->command([
        'collMod' => 'wallets',
        'validator' => [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['userId', 'publicKey', 'secretKey', 'network', 'status', 'createdAt'],
                'properties' => [
                    'userId' => [
                        'bsonType' => 'objectId',
                        'description' => 'User ID associated with the wallet (required)'
                    ],
                    'publicKey' => [
                        'bsonType' => 'string',
                        'description' => 'Stellar public key (required)'
                    ],
                    'secretKey' => [
                        'bsonType' => 'string',
                        'description' => 'Encrypted Stellar secret key (required)'
                    ],
                    'network' => [
                        'enum' => ['testnet', 'public'],
                        'description' => 'Network type (required)'
                    ],
                    'status' => [
                        'enum' => ['active', 'inactive', 'locked'],
                        'description' => 'Wallet status (required)'
                    ],
                    'metadata' => [
                        'bsonType' => 'object',
                        'description' => 'Additional metadata about the wallet',
                        'properties' => [
                            'lastAccessed' => [
                                'bsonType' => 'date',
                                'description' => 'Last time the wallet was accessed'
                            ],
                            'deviceInfo' => [
                                'bsonType' => 'string',
                                'description' => 'Device information where wallet was created'
                            ]
                        ]
                    ],
                    'createdAt' => [
                        'bsonType' => 'date',
                        'description' => 'Timestamp when the wallet was created (required)'
                    ],
                    'updatedAt' => [
                        'bsonType' => 'date',
                        'description' => 'Timestamp when the wallet was last updated'
                    ]
                ]
            ]
        ],
        'validationLevel' => 'strict',
        'validationAction' => 'error'
    ]);
    
    echo "Successfully updated wallets collection schema\n";
} catch (Exception $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
    exit(1);
} 