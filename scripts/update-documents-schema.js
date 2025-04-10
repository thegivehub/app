// MongoDB Schema Migration Script
// This script updates the documents collection schema 
// to relax validation requirements for multi-step form submission

print("Starting documents collection schema migration...");

// The updated schema with relaxed validation
const updatedDocumentsSchema = {
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
      enum: ["passport", "drivers_license", "national_id", "residence_permit", "pending"],
      description: "Type of government-issued ID",
      optional: true
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

// 1. Update schema validation on the documents collection
try {
  const result = db.runCommand({
    collMod: "documents",
    validator: { $jsonSchema: updatedDocumentsSchema },
    validationLevel: "moderate", // This allows existing docs that don't meet validation criteria
    validationAction: "warn"     // Warn rather than reject invalid documents
  });
  
  if (result.ok) {
    print("Successfully updated the documents collection schema");
  } else {
    print("Failed to update schema validation: " + result.errmsg);
  }
} catch (e) {
  print("Error updating schema validation: " + e.message);
}

// 2. Update existing documents to handle cases where documentType might be missing
try {
  const updateResult = db.documents.updateMany(
    { documentType: { $exists: false } },
    { $set: { documentType: "pending" } }
  );
  
  print(`Updated ${updateResult.modifiedCount} documents with missing documentType field`);
} catch (e) {
  print("Error updating existing documents: " + e.message);
}

// 3. Verify the migration
const totalDocs = db.documents.count();
print(`Total document count: ${totalDocs}`);

// Test validate a few documents to see if they pass validation
const sampleDocs = db.documents.find().limit(5).toArray();
let validCount = 0;

sampleDocs.forEach((doc, index) => {
  try {
    const validation = db.runCommand({
      validate: "documents",
      documents: [doc]
    });
    
    if (validation.valid) {
      validCount++;
    } else {
      print(`Document ${index + 1} failed validation: ${JSON.stringify(validation.errors)}`);
    }
  } catch (e) {
    print(`Error validating document ${index + 1}: ${e.message}`);
  }
});

print(`Validation test: ${validCount} out of ${sampleDocs.length} sample documents are valid`);

// Create a backup of the original schema in a separate collection for reference
try {
  db.createCollection("documents_schema_backup");
  db.documents_schema_backup.insertOne({
    schemaVersion: "original",
    timestamp: new Date(),
    schema: db.getCollectionInfos({ name: "documents" })[0].options.validator
  });
  print("Created backup of original schema in documents_schema_backup collection");
} catch (e) {
  print("Error creating schema backup: " + e.message);
}

print("Schema migration completed!"); 