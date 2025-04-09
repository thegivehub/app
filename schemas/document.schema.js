// Documents Collection Schema
const documentsSchema = {
  // MongoDB will create _id automatically
  bsonType: "object",
  required: [
    "userId",
    "firstName",
    "lastName",
    "dateOfBirth",
    "address",
    "city",
    "state",
    "postalCode",
    "country",
    "documentType",
    "status",
    "createdAt",
    "updatedAt"
  ],
  properties: {
    userId: {
      bsonType: "objectId",
      description: "Reference to the user collection"
    },
    firstName: {
      bsonType: "string",
      description: "User's legal first name"
    },
    lastName: {
      bsonType: "string",
      description: "User's legal last name"
    },
    dateOfBirth: {
      bsonType: "date",
      description: "User's date of birth"
    },
    address: {
      bsonType: "string",
      description: "Street address"
    },
    city: {
      bsonType: "string",
      description: "City name"
    },
    state: {
      bsonType: "string",
      description: "State or province"
    },
    postalCode: {
      bsonType: "string",
      description: "Postal/ZIP code"
    },
    country: {
      bsonType: "string",
      description: "Country code (ISO 3166-1 alpha-2)"
    },
    documentType: {
      enum: ["passport", "drivers_license", "national_id", "residence_permit"],
      description: "Type of government-issued ID"
    },
    documentNumber: {
      bsonType: "string",
      description: "ID document number",
      optional: true
    },
    documentExpiry: {
      bsonType: "date",
      description: "ID document expiration date",
      optional: true
    },
    documentImageUrl: {
      bsonType: ["string", "null"],
      description: "Secure URL to government ID image",
      optional: true
    },
    selfieImageUrl: {
      bsonType: ["string", "null"],
      description: "Secure URL to user's selfie image",
      optional: true
    },
    similarityScore: {
      bsonType: ["double", "null"],
      minimum: 0,
      maximum: 1,
      description: "Face similarity score between selfie and ID (0-1)",
      optional: true
    },
    status: {
      enum: ["pending", "approved", "rejected", "expired"],
      description: "Verification status"
    },
    rejectionReason: {
      bsonType: "string",
      description: "Reason for rejection if status is rejected",
      optional: true
    },
    verifiedBy: {
      bsonType: "objectId",
      description: "Reference to admin/verifier who processed the verification",
      optional: true
    },
    verifiedAt: {
      bsonType: "date",
      description: "Timestamp of verification decision",
      optional: true
    },
    createdAt: {
      bsonType: "date",
      description: "Timestamp of document creation"
    },
    updatedAt: {
      bsonType: "date",
      description: "Timestamp of last update"
    },
    ipAddress: {
      bsonType: "string",
      description: "IP address of submission",
      optional: true
    },
    userAgent: {
      bsonType: "string",
      description: "Browser/device info of submission",
      optional: true
    },
    verificationAttempts: {
      bsonType: "int",
      minimum: 0,
      description: "Number of verification attempts",
      optional: true
    },
    metadata: {
      bsonType: "object",
      description: "Additional metadata from verification process",
      optional: true,
      properties: {
        documentAuthenticityScore: {
          bsonType: ["double", "null"],
          description: "Score indicating likelihood document is authentic"
        },
        documentQualityScore: {
          bsonType: ["double", "null"],
          description: "Score indicating image quality of submitted document"
        },
        faceDetectionScore: {
          bsonType: ["double", "null"],
          description: "Confidence score of face detection"
        },
        livenessScore: {
          bsonType: ["double", "null"],
          description: "Score indicating likelihood selfie is of a live person"
        }
      }
    }
  }
};

// Create indexes
db.documents.createIndex({ "userId": 1 });
db.documents.createIndex({ "status": 1 });
db.documents.createIndex({ "createdAt": 1 });
db.documents.createIndex({ "documentType": 1 });
db.documents.createIndex({ "documentNumber": 1 });

// Sample document for testing
const sampleDocument = {
  userId: ObjectId("507f1f77bcf86cd799439011"),
  firstName: "John",
  lastName: "Doe",
  dateOfBirth: new Date("1990-01-15"),
  address: "123 Main Street",
  city: "Boston",
  state: "MA",
  postalCode: "02108",
  country: "US",
  documentType: "passport",
  documentNumber: "P123456789",
  documentExpiry: new Date("2028-01-15"),
  documentImageUrl: null,
  selfieImageUrl: null,
  similarityScore: null,
  status: "pending",
  verificationAttempts: 0,
  createdAt: new Date(),
  updatedAt: new Date(),
  ipAddress: "192.168.1.1",
  userAgent: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0.4472.124",
  metadata: {
    documentAuthenticityScore: null,
    documentQualityScore: null,
    faceDetectionScore: null,
    livenessScore: null
  }
};

// Insert sample document
db.documents.insertOne(sampleDocument);

// Migration script to update existing collection
print("Starting documents collection migration...");

// 1. Update schema validation
db.runCommand({
    collMod: "documents",
    validator: { $jsonSchema: documentsSchema },
    validationLevel: "moderate",
    validationAction: "error"
});

// 2. Update existing documents to ensure they match new schema
db.documents.updateMany(
    {}, // Match all documents
    [
        {
            $set: {
                // Ensure optional fields exist with null values if not present
                documentImageUrl: { $ifNull: ["$documentImageUrl", null] },
                selfieImageUrl: { $ifNull: ["$selfieImageUrl", null] },
                similarityScore: { $ifNull: ["$similarityScore", null] },
                documentNumber: { $ifNull: ["$documentNumber", null] },
                documentExpiry: { $ifNull: ["$documentExpiry", null] },
                verificationAttempts: { $ifNull: ["$verificationAttempts", 0] },
                ipAddress: { $ifNull: ["$ipAddress", null] },
                userAgent: { $ifNull: ["$userAgent", null] },
                metadata: {
                    $ifNull: [
                        "$metadata",
                        {
                            documentAuthenticityScore: null,
                            documentQualityScore: null,
                            faceDetectionScore: null,
                            livenessScore: null
                        }
                    ]
                }
            }
        }
    ]
);

print("Migration completed successfully!");

// Verify the migration
const totalDocs = db.documents.count();
const validDocs = db.documents.find({}).toArray().filter(doc => {
    try {
        db.runCommand({
            validate: "documents",
            documents: [doc]
        });
        return true;
    } catch (e) {
        print(`Document ${doc._id} failed validation: ${e.message}`);
        return false;
    }
}).length;

print(`Validation results: ${validDocs} out of ${totalDocs} documents are valid`); 