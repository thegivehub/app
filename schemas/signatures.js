/**
 * MongoDB schema for signatures collection
 * This collection stores digital signatures captured from users
 */

db.createCollection("signatures", {
  validator: {
    $jsonSchema: {
      bsonType: "object",
      required: ["userId", "signatureData", "createdAt", "type"],
      properties: {
        userId: {
          bsonType: "string",
          description: "User ID associated with the signature (required)"
        },
        documentId: {
          bsonType: ["string", "null"],
          description: "Optional ID of the document being signed"
        },
        signatureData: {
          bsonType: "string",
          description: "Base64 encoded signature data (required)"
        },
        type: {
          enum: ["consent", "agreement", "document", "verification", "other"],
          description: "Type of signature (required)"
        },
        metadata: {
          bsonType: "object",
          description: "Additional metadata about the signature",
          properties: {
            ipAddress: {
              bsonType: "string",
              description: "IP address of the signer"
            },
            userAgent: {
              bsonType: "string",
              description: "User agent of the signer's browser"
            },
            location: {
              bsonType: "object",
              description: "Geographic location data if available",
              properties: {
                latitude: { bsonType: "double" },
                longitude: { bsonType: "double" }
              }
            }
          }
        },
        description: {
          bsonType: "string",
          description: "Description of what was signed"
        },
        createdAt: {
          bsonType: "date",
          description: "Timestamp when the signature was created (required)"
        },
        updatedAt: {
          bsonType: "date",
          description: "Timestamp when the signature was last updated"
        }
      }
    }
  },
  validationLevel: "strict",
  validationAction: "error"
});

// Create indexes
db.signatures.createIndex({ userId: 1 }, { name: "idx_signatures_userId" });
db.signatures.createIndex({ documentId: 1 }, { name: "idx_signatures_documentId" });
db.signatures.createIndex({ createdAt: -1 }, { name: "idx_signatures_createdAt" });
db.signatures.createIndex({ type: 1 }, { name: "idx_signatures_type" });

print("Signatures collection and indexes created successfully"); 