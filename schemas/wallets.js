/**
 * MongoDB schema for wallets collection
 * This collection stores Stellar wallet information for users
 */

db.createCollection("wallets", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["userId", "publicKey", "secretKey", "network", "status", "createdAt"],
            properties: {
                userId: {
                    bsonType: "objectId",
                    description: "User ID associated with the wallet (required)"
                },
                publicKey: {
                    bsonType: "string",
                    description: "Stellar public key (required)"
                },
                secretKey: {
                    bsonType: "string",
                    description: "Encrypted Stellar secret key (required)"
                },
                network: {
                    enum: ["testnet", "public"],
                    description: "Network type (required)"
                },
                status: {
                    enum: ["active", "inactive", "locked"],
                    description: "Wallet status (required)"
                },
                metadata: {
                    bsonType: "object",
                    description: "Additional metadata about the wallet",
                    properties: {
                        lastAccessed: {
                            bsonType: "date",
                            description: "Last time the wallet was accessed"
                        },
                        deviceInfo: {
                            bsonType: "string",
                            description: "Device information where wallet was created"
                        }
                    }
                },
                createdAt: {
                    bsonType: "date",
                    description: "Timestamp when the wallet was created (required)"
                },
                updatedAt: {
                    bsonType: "date",
                    description: "Timestamp when the wallet was last updated"
                }
            }
        }
    },
    validationLevel: "strict",
    validationAction: "error"
});

// Create indexes
db.wallets.createIndex({ userId: 1 }, { unique: true, name: "idx_wallets_userId" });
db.wallets.createIndex({ publicKey: 1 }, { unique: true, name: "idx_wallets_publicKey" });
db.wallets.createIndex({ createdAt: -1 }, { name: "idx_wallets_createdAt" });
db.wallets.createIndex({ status: 1 }, { name: "idx_wallets_status" });

print("Wallets collection and indexes created successfully"); 