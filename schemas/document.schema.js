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
      description: "ID document number"
    },
    documentExpiry: {
      bsonType: "date",
      description: "ID document expiration date"
    },
    documentImageUrl: {
      bsonType: "string",
      description: "Secure URL to government ID image"
    },
    selfieImageUrl: {
      bsonType: "string",
      description: "Secure URL to user's selfie image"
    },
    similarityScore: {
      bsonType: "double",
      minimum: 0,
      maximum: 1,
      description: "Face similarity score between selfie and ID (0-1)"
    },
    status: {
      enum: ["pending", "approved", "rejected", "expired"],
      description: "Verification status"
    },
    rejectionReason: {
      bsonType: "string",
      description: "Reason for rejection if status is rejected"
    },
    verifiedBy: {
      bsonType: "objectId",
      description: "Reference to admin/verifier who processed the verification"
    },
    verifiedAt: {
      bsonType: "date",
      description: "Timestamp of verification decision"
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
      description: "IP address of submission"
    },
    userAgent: {
      bsonType: "string",
      description: "Browser/device info of submission"
    },
    verificationAttempts: {
      bsonType: "int",
      minimum: 0,
      description: "Number of verification attempts"
    },
    metadata: {
      bsonType: "object",
      description: "Additional metadata from verification process",
      properties: {
        documentAuthenticityScore: {
          bsonType: "double",
          description: "Score indicating likelihood document is authentic"
        },
        documentQualityScore: {
          bsonType: "double",
          description: "Score indicating image quality of submitted document"
        },
        faceDetectionScore: {
          bsonType: "double",
          description: "Confidence score of face detection"
        },
        livenessScore: {
          bsonType: "double",
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
  documentImageUrl: "https://storage.thegivehub.com/secure/docs/passport_12345.jpg",
  selfieImageUrl: "https://storage.thegivehub.com/secure/selfies/selfie_12345.jpg",
  similarityScore: 0.92,
  status: "pending",
  verificationAttempts: 1,
  createdAt: new Date(),
  updatedAt: new Date(),
  ipAddress: "192.168.1.1",
  userAgent: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0.4472.124",
  metadata: {
    documentAuthenticityScore: 0.95,
    documentQualityScore: 0.88,
    faceDetectionScore: 0.97,
    livenessScore: 0.94
  }
};

// Insert sample document
db.documents.insertOne(sampleDocument); 