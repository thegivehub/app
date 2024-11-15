// donations.js - MongoDB Schema

const donationSchema = {
  validator: {
    $jsonSchema: {
      bsonType: "object",
      required: ["userId", "campaignId", "amount", "transaction", "status", "created"],
      properties: {
        userId: {
          bsonType: "objectId",
          description: "Reference to user making donation"
        },
        campaignId: {
          bsonType: "objectId",
          description: "Reference to campaign receiving donation"
        },
        amount: {
          bsonType: "object",
          required: ["value", "currency"],
          properties: {
            value: { bsonType: "decimal" },
            currency: { bsonType: "string" },
            fiatEquivalent: {
              bsonType: "object",
              properties: {
                value: { bsonType: "decimal" },
                currency: { bsonType: "string" },
                exchangeRate: { bsonType: "decimal" }
              }
            }
          }
        },
        transaction: {
          bsonType: "object",
          required: ["txHash", "stellarAddress", "status"],
          properties: {
            txHash: { bsonType: "string" },
            stellarAddress: { bsonType: "string" },
            status: {
              enum: ["pending", "completed", "failed", "refunded"]
            },
            timestamp: { bsonType: "date" },
            networkFee: { bsonType: "decimal" },
            memo: { bsonType: "string" }
          }
        },
        type: {
          enum: ["one-time", "recurring", "milestone"],
          default: "one-time"
        },
        status: {
          enum: ["pending", "processing", "completed", "failed", "refunded"]
        },
        visibility: {
          enum: ["public", "anonymous", "private"],
          default: "public"
        },
        recurringDetails: {
          bsonType: "object",
          properties: {
            frequency: {
              enum: ["weekly", "monthly", "quarterly", "annually"]
            },
            startDate: { bsonType: "date" },
            endDate: { bsonType: "date" },
            lastProcessed: { bsonType: "date" },
            nextProcessing: { bsonType: "date" },
            totalProcessed: { bsonType: "int" },
            status: {
              enum: ["active", "paused", "cancelled", "completed"]
            }
          }
        },
        milestoneDetails: {
          bsonType: "object",
          properties: {
            milestoneId: { bsonType: "objectId" },
            conditions: {
              bsonType: "array",
              items: {
                bsonType: "object",
                required: ["description", "status"],
                properties: {
                  description: { bsonType: "string" },
                  status: {
                    enum: ["pending", "met", "failed"]
                  },
                  verifiedBy: { bsonType: "objectId" },
                  verifiedAt: { bsonType: "date" }
                }
              }
            }
          }
        },
        impact: {
          bsonType: "object",
          properties: {
            category: {
              enum: ["water", "education", "healthcare", "agriculture", "infrastructure"]
            },
            metrics: {
              bsonType: "array",
              items: {
                bsonType: "object",
                required: ["name", "value", "unit"],
                properties: {
                  name: { bsonType: "string" },
                  value: { bsonType: "decimal" },
                  unit: { bsonType: "string" },
                  verifiedAt: { bsonType: "date" },
                  verifiedBy: { bsonType: "objectId" }
                }
              }
            },
            beneficiaries: {
              bsonType: "object",
              properties: {
                direct: { bsonType: "int" },
                indirect: { bsonType: "int" },
                communities: {
                  bsonType: "array",
                  items: { bsonType: "string" }
                }
              }
            },
            sdgGoals: {
              bsonType: "array",
              items: {
                bsonType: "int",
                minimum: 1,
                maximum: 17
              }
            }
          }
        },
        taxBenefits: {
          bsonType: "object",
          properties: {
            country: { bsonType: "string" },
            scheme: { bsonType: "string" },
            reference: { bsonType: "string" },
            amount: { bsonType: "decimal" },
            status: {
              enum: ["pending", "approved", "rejected", "processed"]
            },
            documents: {
              bsonType: "array",
              items: {
                bsonType: "object",
                properties: {
                  type: { bsonType: "string" },
                  url: { bsonType: "string" },
                  verifiedAt: { bsonType: "date" }
                }
              }
            }
          }
        },
        metadata: {
          bsonType: "object",
          properties: {
            userAgent: { bsonType: "string" },
            ipAddress: { bsonType: "string" },
            location: {
              bsonType: "object",
              properties: {
                country: { bsonType: "string" },
                region: { bsonType: "string" }
              }
            }
          }
        },
        created: { bsonType: "date" },
        updated: { bsonType: "date" }
      }
    }
  },
  indices: [
    { key: { userId: 1, campaignId: 1 } },
    { key: { "transaction.txHash": 1 }, unique: true },
    { key: { "transaction.stellarAddress": 1 } },
    { key: { status: 1 } },
    { key: { type: 1 } },
    { key: { created: -1 } },
    { key: { "amount.currency": 1 } },
    { key: { "impact.category": 1 } }
  ]
};

// Example document
const exampleDonation = {
  userId: ObjectId("user_object_id"),
  campaignId: ObjectId("65ee1a1b2f3a4b5c6d7e8f9a"),
  amount: {
    value: 500.00,
    currency: "XLM",
    fiatEquivalent: {
      value: 475.25,
      currency: "USD",
      exchangeRate: 0.9505
    }
  },
  transaction: {
    txHash: "68ee1a1b2f3a4b5c6d7e8f9b",
    stellarAddress: "GBXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX",
    status: "completed",
    timestamp: new Date("2024-03-15T08:30:00Z"),
    networkFee: 0.00001,
    memo: "Clean Water Project Donation"
  },
  type: "one-time",
  status: "completed",
  visibility: "public",
  impact: {
    category: "water",
    metrics: [
      {
        name: "Water Access Hours",
        value: 24.00,
        unit: "hours/day",
        verifiedAt: new Date("2024-03-15T10:00:00Z"),
        verifiedBy: ObjectId("verifier_object_id")
      }
    ],
    beneficiaries: {
      direct: 500,
      indirect: 2000,
      communities: ["Samburu County"]
    },
    sdgGoals: [6]
  },
  metadata: {
    userAgent: "Mozilla/5.0...",
    ipAddress: "192.168.1.1",
    location: {
      country: "United States",
      region: "California"
    }
  },
  created: new Date("2024-03-15T08:30:00Z"),
  updated: new Date("2024-03-15T08:30:00Z")
};

// Create collection with schema validation
db.createCollection("donations", donationSchema);

// Create indexes
db.donations.createIndexes(donationSchema.indices);
