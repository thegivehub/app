db = db.getSiblingDB('givehub');

// Create collections with their validators
db.createCollection('users');
db.createCollection('campaigns');
db.createCollection('donations');
db.createCollection('donors');
db.createCollection('impactmetrics');
db.createCollection('updates');
db.createCollection('notifications');
db.createCollection('preferences');

// Create indexes
db.users.createIndex({ "email": 1 }, { unique: true });
db.users.createIndex({ "username": 1 }, { unique: true });
db.users.createIndex({ "status": 1 });

db.campaigns.createIndex({ "creator_id": 1 });
db.campaigns.createIndex({ "status": 1 });
db.campaigns.createIndex({ "created": -1 });
db.campaigns.createIndex({ "category": 1 });

db.donations.createIndex({ "campaign_id": 1 });
db.donations.createIndex({ "user_id": 1 });
db.donations.createIndex({ "transaction.txHash": 1 }, { unique: true });
db.donations.createIndex({ "created": -1 });

db.donors.createIndex({ "email": 1 }, { unique: true });
db.donors.createIndex({ "status": 1 });
db.donors.createIndex({ "donationType": 1 });
db.donors.createIndex({ "lastDonation": -1 });

// Create admin user if needed
// db.users.insertOne({
//   username: "admin",
//   email: "admin@example.com",
//   status: "active",
//   personalInfo: {
//     firstName: "Admin",
//     lastName: "User",
//     email: "admin@example.com"
//   },
//   auth: {
//     passwordHash: "$2y$10$YourHashedPasswordHere",
//     verified: true
//   },
//   roles: ["admin", "user"],
//   created: new Date(),
//   updated: new Date()
// });

print("MongoDB initialization completed");
