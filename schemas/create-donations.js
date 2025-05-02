// create-donations.js
// run with: mongosh --file create-donations.js
//-----------------------------------
// 1. choose a database
//-----------------------------------
//use givehub;   // change this if you want a different db

//-----------------------------------
// 2. create the collection + validator
//-----------------------------------
db.createCollection("donations", {
  validator: {
    $jsonSchema: {
      bsonType: "object",
      required: ["userId","campaignId","amount","transaction","status","created"],
      properties: {
        userId:     { bsonType: "objectId" },
        campaignId: { bsonType: "objectId" },

        amount: {
          bsonType: "object",
          required: ["value","currency"],
          properties: {
            value:    { bsonType: "decimal" },
            currency: { bsonType: "string" },
            fiatEquivalent: {
              bsonType: "object",
              properties: {
                value:        { bsonType: "decimal" },
                currency:     { bsonType: "string" },
                exchangeRate: { bsonType: "decimal" }
              }
            }
          }
        },

        transaction: {
          bsonType: "object",
          required: ["txHash","stellarAddress","status"],
          properties: {
            txHash:        { bsonType: "string" },
            stellarAddress:{ bsonType: "string" },
            status:        { bsonType: "string", enum: ["pending","completed","failed","refunded"] },
            timestamp:     { bsonType: "date" },
            networkFee:    { bsonType: "decimal" },
            memo:          { bsonType: "string" }
          }
        },

        type:       { bsonType: "string", enum: ["one-time","recurring","milestone"] },
        status:     { bsonType: "string", enum: ["pending","processing","completed","failed","refunded"] },
        visibility: { bsonType: "string", enum: ["public","anonymous","private"] },

        recurringDetails: {
          bsonType: "object",
          properties: {
            frequency:     { bsonType: "string", enum: ["weekly","monthly","quarterly","annually"] },
            startDate:     { bsonType: "date" },
            endDate:       { bsonType: "date" },
            lastProcessed: { bsonType: "date" },
            nextProcessing:{ bsonType: "date" },
            totalProcessed:{ bsonType: "int"  },
            status:        { bsonType: "string", enum: ["active","paused","cancelled","completed"] }
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
                required: ["description","status"],
                properties: {
                  description:{ bsonType: "string" },
                  status:     { bsonType: "string", enum: ["pending","met","failed"] },
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
            category: { bsonType: "string", enum: ["water","education","healthcare","agriculture","infrastructure"] },
            metrics:  {
              bsonType: "array",
              items: {
                bsonType: "object",
                required: ["name","value","unit"],
                properties: {
                  name:       { bsonType: "string" },
                  value:      { bsonType: "decimal" },
                  unit:       { bsonType: "string" },
                  verifiedAt: { bsonType: "date" },
                  verifiedBy: { bsonType: "objectId" }
                }
              }
            },
            beneficiaries: {
              bsonType: "object",
              properties: {
                direct:      { bsonType: "int" },
                indirect:    { bsonType: "int" },
                communities: { bsonType: "array", items: { bsonType: "string" } }
              }
            },
            sdgGoals: {
              bsonType: "array",
              items: { bsonType: "int", minimum: 1, maximum: 17 }
            }
          }
        },

        taxBenefits: {
          bsonType: "object",
          properties: {
            country:   { bsonType: "string" },
            scheme:    { bsonType: "string" },
            reference: { bsonType: "string" },
            amount:    { bsonType: "decimal" },
            status:    { bsonType: "string", enum: ["pending","approved","rejected","processed"] },
            documents: {
              bsonType: "array",
              items: {
                bsonType: "object",
                properties: {
                  type:       { bsonType: "string" },
                  url:        { bsonType: "string" },
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
            location:  {
              bsonType: "object",
              properties: {
                country: { bsonType: "string" },
                region:  { bsonType: "string" }
              }
            }
          }
        },

        created: { bsonType: "date" },
        updated: { bsonType: "date" }
      }
    }
  },
  validationLevel: "strict",
  validationAction: "error"
});

//-----------------------------------
// 3. create indexes
//-----------------------------------
db.donations.createIndexes([
  { key: { userId: 1, campaignId: 1 } },
  { key: { "transaction.txHash": 1 }, unique: true },
  { key: { "transaction.stellarAddress": 1 } },
  { key: { status: 1 } },
  { key: { type: 1 } },
  { key: { created: -1 } },
  { key: { "amount.currency": 1 } },
  { key: { "impact.category": 1 } }
]);

print("donations collection and indexes created ✔");
