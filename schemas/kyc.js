// kyc_verifications MongoDB Collection Schema

db.createCollection("kyc_verifications", {
  validator: {
    $jsonSchema: {
      bsonType: "object",
      required: ["userId", "referenceId", "status", "created", "updated"],
      properties: {
        userId: {
          bsonType: "objectId",
          description: "User ID reference"
        },
        referenceId: {
          bsonType: "string",
          description: "Unique reference ID for Jumio"
        },
        accountId: {
          bsonType: "string",
          description: "Jumio account ID"
        },
        redirectUrl: {
          bsonType: "string",
          description: "URL to redirect user for verification"
        },
        status: {
          enum: ["PENDING", "APPROVED", "REJECTED", "ERROR", "FAILED"],
          description: "Current verification status"
        },
        verificationDetails: {
          bsonType: "object",
          description: "Details from Jumio verification response"
        },
        documentData: {
          bsonType: "object",
          description: "Extracted document information"
        },
        adminOverride: {
          bsonType: "object",
          properties: {
            adminId: {
              bsonType: "string",
              description: "Admin user ID who performed the override"
            },
            reason: {
              bsonType: "string",
              description: "Reason for override"
            },
            timestamp: {
              bsonType: "date",
              description: "When override was performed"
            },
            previousStatus: {
              bsonType: "string",
              description: "Status before override"
            }
          }
        },
        created: {
          bsonType: "date",
          description: "When verification was initiated"
        },
        updated: {
          bsonType: "date",
          description: "When verification was last updated"
        }
      }
    }
  }
});

// Create indexes
db.kyc_verifications.createIndex({ "userId": 1 });
db.kyc_verifications.createIndex({ "referenceId": 1 }, { unique: true });
db.kyc_verifications.createIndex({ "status": 1 });
db.kyc_verifications.createIndex({ "created": -1 });

// User schema updates for KYC
// This MongoDB update adds KYC verification fields to the existing users collection

db.users.updateMany({}, {
  $set: {
    kycVerification: {
      status: "unverified",
      provider: null,
      date: null
    }
  }
}, { upsert: false });

// Sample document
const exampleVerification = {
  userId: ObjectId("65ee1a1b2f3a4b5c6d7e8f9a"),
  referenceId: "GIVEHUB_65ee1a1b2f3a4b5c6d7e8f9a_1678901234_5678",
  accountId: "12345678-abcd-1234-efgh-1234567890ab",
  redirectUrl: "https://go.jumio.com/verify/12345678-abcd-1234-efgh-1234567890ab",
  status: "PENDING",
  verificationDetails: null,
  documentData: null,
  created: new Date(),
  updated: new Date()
};

// Sample approved verification document
const approvedVerification = {
  userId: ObjectId("65ee1a1b2f3a4b5c6d7e8f9b"),
  referenceId: "GIVEHUB_65ee1a1b2f3a4b5c6d7e8f9b_1678901235_5679",
  accountId: "12345678-abcd-1234-efgh-1234567890ac",
  redirectUrl: "https://go.jumio.com/verify/12345678-abcd-1234-efgh-1234567890ac",
  status: "APPROVED",
  verificationDetails: {
    transactionStatus: "DONE",
    verificationStatus: "APPROVED_VERIFIED",
    timestamp: new Date(),
    scanReference: "scan-ref-123456",
    jumioIdScanReference: "jumio-scan-ref-123456"
  },
  documentData: {
    type: "PASSPORT",
    issuingCountry: "USA",
    number: "123456789",
    firstName: "John",
    lastName: "Doe",
    dateOfBirth: "1990-01-01",
    expiryDate: "2030-01-01"
  },
  created: new Date(Date.now() - 86400000), // 1 day ago
  updated: new Date()
};

// Sample admin override verification
const adminOverrideVerification = {
  userId: ObjectId("65ee1a1b2f3a4b5c6d7e8f9c"),
  referenceId: "GIVEHUB_65ee1a1b2f3a4b5c6d7e8f9c_1678901236_5680",
  accountId: null,
  redirectUrl: null,
  status: "APPROVED",
  verificationDetails: null,
  documentData: null,
  adminOverride: {
    adminId: ObjectId("65ee1a1b2f3a4b5c6d7e8f9d"),
    reason: "User verified through alternative documentation",
    timestamp: new Date(),
    previousStatus: "PENDING"
  },
  created: new Date(Date.now() - 172800000), // 2 days ago
  updated: new Date()
};
