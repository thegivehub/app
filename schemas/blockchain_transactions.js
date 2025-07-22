/**
 * MongoDB schema for blockchain_transactions collection
 * This collection tracks the status of blockchain transactions
 */

db.createCollection("blockchain_transactions", {
  validator: {
    $jsonSchema: {
      bsonType: "object",
      required: ["txHash", "status", "createdAt", "type"],
      properties: {
        txHash: {
          bsonType: "string",
          description: "Blockchain transaction hash (required)"
        },
        sourceId: {
          bsonType: "string",
          description: "ID of the source record (donation, milestone payment, etc.)"
        },
        sourceType: {
          enum: ["donation", "milestone", "escrow", "withdrawal", "other"],
          description: "Type of source record (required)"
        },
        userId: {
          bsonType: "string",
          description: "User ID associated with the transaction"
        },
        campaignId: {
          bsonType: "string",
          description: "Campaign ID associated with the transaction"
        },
        amount: {
          bsonType: "object",
          required: ["value", "currency"],
          properties: {
            value: {
              bsonType: "double",
              description: "Transaction amount"
            },
            currency: {
              bsonType: "string",
              description: "Currency code (e.g., XLM)"
            }
          }
        },
        status: {
          enum: ["pending", "submitted", "confirming", "confirmed", "failed", "expired"],
          description: "Current status of the transaction (required)"
        },
        type: {
          enum: ["payment", "account_creation", "escrow_setup", "milestone_release", "other", "donation"],
          description: "Type of blockchain transaction (required)"
        },
        stellarDetails: {
          bsonType: "object",
          description: "Details from the Stellar blockchain",
          properties: {
            ledger: {
              bsonType: "int",
              description: "Ledger number where transaction was included"
            },
            sourceAccount: {
              bsonType: "string",
              description: "Source Stellar account"
            },
            destinationAccount: {
              bsonType: "string",
              description: "Destination Stellar account"
            },
            fee: {
              bsonType: "int",
              description: "Transaction fee in stroops"
            },
            memo: {
              bsonType: "string",
              description: "Transaction memo"
            },
            memoType: {
              bsonType: "string",
              description: "Type of memo"
            },
            successful: {
              bsonType: "bool",
              description: "Whether the transaction was successful on the blockchain"
            },
            operationCount: {
              bsonType: "int",
              description: "Number of operations in the transaction"
            }
          }
        },
        statusHistory: {
          bsonType: "array",
          description: "History of status changes",
          items: {
            bsonType: "object",
            required: ["status", "timestamp"],
            properties: {
              status: {
                enum: ["pending", "submitted", "confirming", "confirmed", "failed", "expired"],
                description: "Status at this point"
              },
              timestamp: {
                bsonType: "date",
                description: "When the status changed"
              },
              details: {
                bsonType: "string",
                description: "Additional details about the status change"
              }
            }
          }
        },
        lastChecked: {
          bsonType: "date",
          description: "When the transaction was last checked on the blockchain"
        },
        confirmations: {
          bsonType: "int",
          description: "Number of confirmations (for blockchains that use this concept)"
        },
        multisig: {
          bsonType: "bool",
          description: "Whether the transaction requires multiple signatures"
        },
        createdAt: {
          bsonType: "date",
          description: "When the transaction record was created"
        },
        updatedAt: {
          bsonType: "date",
          description: "When the transaction record was last updated"
        },
        expiresAt: {
          bsonType: "date",
          description: "When the transaction expires (if applicable)"
        },
        error: {
          bsonType: "object",
          description: "Error details if the transaction failed",
          properties: {
            code: {
              bsonType: "string",
              description: "Error code"
            },
            message: {
              bsonType: "string",
              description: "Error message"
            },
            details: {
              bsonType: "string",
              description: "Detailed error information"
            }
          }
        }
      }
    }
  },
  validationLevel: "strict",
  validationAction: "error"
});

// Create indexes
db.blockchain_transactions.createIndex({ txHash: 1 }, { unique: true });
db.blockchain_transactions.createIndex({ sourceId: 1 });
db.blockchain_transactions.createIndex({ userId: 1 });
db.blockchain_transactions.createIndex({ campaignId: 1 });
db.blockchain_transactions.createIndex({ status: 1 });
db.blockchain_transactions.createIndex({ createdAt: -1 });
db.blockchain_transactions.createIndex({ "amount.currency": 1 });
db.blockchain_transactions.createIndex({ lastChecked: 1 });

print("Blockchain transactions collection and indexes created successfully"); 