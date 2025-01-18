// donor-schema.js
const donorSchema = {
  validator: {
    $jsonSchema: {
      bsonType: "object",
      required: ["email", "name", "status", "donationType", "created"],
      properties: {
        email: {
          bsonType: "string",
          pattern: "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$"
        },
        name: {
          bsonType: "string",
          minLength: 2
        },
        status: {
          enum: ["active", "inactive"]
        },
        donationType: {
          enum: ["recurring", "one-time"]
        },
        totalDonated: {
          bsonType: "decimal",
          minimum: 0
        },
        lastDonation: {
          bsonType: "date"
        },
        donationHistory: {
          bsonType: "array",
          items: {
            bsonType: "object",
            required: ["amount", "date", "campaignId"],
            properties: {
              amount: { bsonType: "decimal" },
              date: { bsonType: "date" },
              campaignId: { bsonType: "objectId" },
              recurring: { bsonType: "bool" }
            }
          }
        },
        recurringDetails: {
          bsonType: "object",
          properties: {
            amount: { bsonType: "decimal" },
            frequency: { enum: ["monthly", "quarterly", "annually"] },
            startDate: { bsonType: "date" },
            nextDonation: { bsonType: "date" },
            status: { enum: ["active", "paused", "cancelled"] }
          }
        },
        location: {
          bsonType: "object",
          properties: {
            country: { bsonType: "string" },
            city: { bsonType: "string" }
          }
        },
        preferences: {
          bsonType: "object",
          properties: {
            newsletter: { bsonType: "bool" },
            notifications: { bsonType: "bool" },
            anonymousDonations: { bsonType: "bool" }
          }
        },
        created: { bsonType: "date" },
        lastActive: { bsonType: "date" }
      }
    }
  }
};

// Example donor document
const exampleDonor = {
  email: "sarah.chen@email.com",
  name: "Sarah Chen",
  status: "active",
  donationType: "recurring",
  totalDonated: 2500.00,
  lastDonation: new Date("2024-03-15"),
  donationHistory: [
    {
      amount: 500.00,
      date: new Date("2024-03-15"),
      campaignId: ObjectId("65ee1a1b2f3a4b5c6d7e8f9a"),
      recurring: true
    }
  ],
  recurringDetails: {
    amount: 500.00,
    frequency: "monthly",
    startDate: new Date("2023-10-15"),
    nextDonation: new Date("2024-04-15"),
    status: "active"
  },
  location: {
    country: "United States",
    city: "San Francisco"
  },
  preferences: {
    newsletter: true,
    notifications: true,
    anonymousDonations: false
  },
  created: new Date("2023-10-15"),
  lastActive: new Date("2024-03-15")
};

// Create collection with schema validation
db.createCollection("donors", donorSchema);

// Create indexes
db.donors.createIndex({ email: 1 }, { unique: true });
db.donors.createIndex({ status: 1 });
db.donors.createIndex({ donationType: 1 });
db.donors.createIndex({ "location.country": 1 });
db.donors.createIndex({ totalDonated: -1 });
db.donors.createIndex({ lastDonation: -1 });
