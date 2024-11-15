// users.js - MongoDB Schema

const userSchema = {
  validator: {
    $jsonSchema: {
      bsonType: "object",
      required: ["email", "username", "auth", "profile", "status", "roles", "created"],
      properties: {
        email: {
          bsonType: "string",
          pattern: "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$"
        },
        username: {
          bsonType: "string",
          minLength: 3,
          maxLength: 50
        },
        auth: {
          bsonType: "object",
          required: ["passwordHash", "lastLogin"],
          properties: {
            passwordHash: { bsonType: "string" },
            lastLogin: { bsonType: "date" },
            twoFactorEnabled: { bsonType: "bool" },
            twoFactorSecret: { bsonType: "string" },
            resetToken: { bsonType: "string" },
            resetTokenExpires: { bsonType: "date" }
          }
        },
        profile: {
          bsonType: "object",
          required: ["firstName", "lastName"],
          properties: {
            firstName: { bsonType: "string" },
            lastName: { bsonType: "string" },
            displayName: { bsonType: "string" },
            avatar: { bsonType: "string" },
            bio: { bsonType: "string" },
            location: {
              bsonType: "object",
              properties: {
                country: { bsonType: "string" },
                city: { bsonType: "string" },
                timezone: { bsonType: "string" }
              }
            },
            socialLinks: {
              bsonType: "object",
              properties: {
                website: { bsonType: "string" },
                twitter: { bsonType: "string" },
                linkedin: { bsonType: "string" }
              }
            }
          }
        },
        wallet: {
          bsonType: "object",
          required: ["stellarAddress"],
          properties: {
            stellarAddress: { bsonType: "string" },
            totalDonated: { bsonType: "decimal" },
            campaigns: {
              bsonType: "array",
              items: {
                bsonType: "object",
                required: ["campaignId", "amount"],
                properties: {
                  campaignId: { bsonType: "objectId" },
                  amount: { bsonType: "decimal" },
                  timestamp: { bsonType: "date" }
                }
              }
            }
          }
        },
        roles: {
          bsonType: "array",
          items: {
            enum: ["user", "admin", "moderator", "verified"]
          }
        },
        status: {
          enum: ["active", "inactive", "suspended", "pending"]
        },
        preferences: {
          bsonType: "object",
          properties: {
            emailNotifications: {
              bsonType: "object",
              properties: {
                campaigns: { bsonType: "bool" },
                updates: { bsonType: "bool" },
                newsletter: { bsonType: "bool" }
              }
            },
            language: { bsonType: "string" },
            currency: { bsonType: "string" }
          }
        },
        created: { bsonType: "date" },
        updated: { bsonType: "date" },
        lastActive: { bsonType: "date" }
      }
    }
  },
  indices: [
    { key: { email: 1 }, unique: true },
    { key: { username: 1 }, unique: true },
    { key: { "wallet.stellarAddress": 1 }, unique: true },
    { key: { status: 1 } },
    { key: { roles: 1 } },
    { key: { created: -1 } }
  ]
};

// Example document
const exampleUser = {
  email: "john.doe@example.com",
  username: "johndoe",
  auth: {
    passwordHash: "$2b$10$X7GNh.9LMB8YnZ2qZKC2O.1234567890abcdef",
    lastLogin: new Date("2024-03-14T10:00:00Z"),
    twoFactorEnabled: false,
    twoFactorSecret: null,
    resetToken: null,
    resetTokenExpires: null
  },
  profile: {
    firstName: "John",
    lastName: "Doe",
    displayName: "John D.",
    avatar: "https://storage.givehub.org/avatars/jd123.jpg",
    bio: "Passionate about making a difference through technology",
    location: {
      country: "United States",
      city: "San Francisco",
      timezone: "America/Los_Angeles"
    },
    socialLinks: {
      website: "https://johndoe.com",
      twitter: "@johndoe",
      linkedin: "in/johndoe"
    }
  },
  wallet: {
    stellarAddress: "GBXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX",
    totalDonated: 1500.00,
    campaigns: [
      {
        campaignId: ObjectId("65ee1a1b2f3a4b5c6d7e8f9a"),
        amount: 500.00,
        timestamp: new Date("2024-03-14T08:30:00Z")
      }
    ]
  },
  roles: ["user", "verified"],
  status: "active",
  preferences: {
    emailNotifications: {
      campaigns: true,
      updates: true,
      newsletter: false
    },
    language: "en",
    currency: "USD"
  },
  created: new Date("2024-01-01T00:00:00Z"),
  updated: new Date("2024-03-14T10:00:00Z"),
  lastActive: new Date("2024-03-14T10:00:00Z")
};

// Create collection with schema validation
db.createCollection("users", userSchema);

// Create indexes
db.users.createIndexes(userSchema.indices);
