// import-campaigns.js
const mongoose = require('mongoose');
require('dotenv').config();

// Define subdocument schemas first
const mediaSchema = new mongoose.Schema({
  type: {
    type: String,
    enum: ['image', 'video', 'document']
  },
  url: String,
  caption: String,
  timestamp: Date
});

const milestoneSchema = new mongoose.Schema({
  title: String,
  description: String,
  targetDate: Date,
  completedDate: Date,
  status: {
    type: String,
    enum: ['pending', 'in-progress', 'completed', 'delayed']
  }
});

const metricSchema = new mongoose.Schema({
  name: String,
  baseline: Number,
  current: Number,
  target: Number,
  unit: String
});

const localPartnerSchema = new mongoose.Schema({
  organizationId: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Organization'
  },
  role: String,
  verificationDocuments: [String]
});

const updateSchema = new mongoose.Schema({
  date: Date,
  description: String,
  mediaUrls: [String],
  verifiedBy: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User'
  }
});

const transactionSchema = new mongoose.Schema({
  txHash: String,
  amount: Number,
  timestamp: Date,
  donorId: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User'
  }
});

// Campaign Schema
const campaignSchema = new mongoose.Schema({
  title: {
    type: String,
    required: true
  },
  description: String,
  location: {
    country: String,
    region: String,
    coordinates: {
      latitude: Number,
      longitude: Number
    }
  },
  funding: {
    goalAmount: Number,
    raisedAmount: {
      type: Number,
      default: 0
    },
    currency: {
      type: String,
      default: 'XLM'
    },
    donorCount: {
      type: Number,
      default: 0
    },
    transactions: [transactionSchema]
  },
  timeline: {
    created: {
      type: Date,
      default: Date.now
    },
    startDate: Date,
    endDate: Date,
    milestones: [milestoneSchema]
  },
  impact: {
    beneficiariesCount: Number,
    metrics: [metricSchema],
    sdgGoals: [Number]
  },
  verification: {
    status: {
      type: String,
      enum: ['pending', 'verified', 'rejected', 'completed']
    },
    localPartners: [localPartnerSchema],
    updates: [updateSchema]
  },
  media: [mediaSchema],
  category: String,
  tags: [String],
  status: {
    type: String,
    enum: ['draft', 'active', 'completed', 'cancelled'],
    default: 'draft'
  },
  visibility: {
    type: String,
    enum: ['public', 'private'],
    default: 'public'
  }
});

const Campaign = mongoose.model('Campaign', campaignSchema);

// Sample Campaigns Data
const campaignsData = [
  // First campaign - Water Pipeline (60% funded)
  {
    _id: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f9a"),
    title: "Clean Water Pipeline - Samburu County",
    description: "Construction of a 5km water pipeline connecting remote villages to the main water supply, serving 2,500 residents and dramatically reducing water collection time for local families.",
    location: {
      country: "Kenya",
      region: "Samburu County",
      coordinates: {
        latitude: 1.2833,
        longitude: 37.5333
      }
    },
    funding: {
      goalAmount: 125000,
      raisedAmount: 75000,
      currency: "XLM",
      donorCount: 184,
      transactions: [
        {
          txHash: "67f54d3ba1ff4d7",
          amount: 5000,
          timestamp: new Date("2024-03-10T14:22:31Z"),
          donorId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f01")
        }
      ]
    },
    timeline: {
      created: new Date("2024-02-15T08:00:00Z"),
      startDate: new Date("2024-03-01T00:00:00Z"),
      endDate: new Date("2024-06-01T00:00:00Z"),
      milestones: [
        {
          title: "Environmental Impact Assessment",
          description: "Complete required environmental studies and obtain permits",
          targetDate: new Date("2024-03-15T00:00:00Z"),
          completedDate: new Date("2024-03-14T16:30:00Z"),
          status: "completed"
        }
      ]
    },
    impact: {
      beneficiariesCount: 2500,
      metrics: [
        {
          name: "Water Collection Time",
          baseline: 240,
          current: 240,
          target: 15,
          unit: "minutes"
        }
      ],
      sdgGoals: [6, 3]
    },
    verification: {
      status: "verified",
      localPartners: [
        {
          organizationId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f9c"),
          role: "implementingPartner",
          verificationDocuments: ["water_authority_approval.pdf"]
        }
      ],
      updates: [
        {
          date: new Date("2024-03-14T16:30:00Z"),
          description: "Environmental impact assessment completed and approved",
          mediaUrls: ["site_survey_photo1.jpg"],
          verifiedBy: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f04")
        }
      ]
    },
    media: [
      {
        type: "image",
        url: "images/water_project/current_water_source.jpg",
        caption: "Current water source - 4 hour walk for local residents",
        timestamp: new Date("2024-02-15T08:00:00Z")
      },
      {
        type: "document",
        url: "docs/water_project/technical_plan.pdf",
        caption: "Technical implementation plan",
        timestamp: new Date("2024-02-15T08:00:00Z")
      }
    ],
    category: "water-access",
    tags: ["water", "infrastructure", "health", "community"],
    status: "active",
    visibility: "public"
  }
  // ... Add the rest of your campaigns here with the same structure
];

// Database connection and import function
async function importCampaigns() {
  try {
    await mongoose.connect('mongodb://localhost:27017/givehub', {
      useNewUrlParser: true,
      useUnifiedTopology: true
    });
    console.log('Connected to MongoDB');

    // Clear existing campaigns
    await Campaign.deleteMany({});
    console.log('Cleared existing campaigns');

    // Insert new campaigns
    const result = await Campaign.insertMany(campaignsData);
    console.log(`Successfully imported ${result.length} campaigns`);

    // Verification queries
    console.log('\nVerifying imported data:');

    // Campaigns by status
    const campaignsByStatus = await Campaign.aggregate([
      { $group: { _id: "$status", count: { $sum: 1 } } }
    ]);
    console.log('\nCampaigns by status:', campaignsByStatus);

    // Funding progress
    const fundingProgress = await Campaign.aggregate([
      { $group: {
        _id: null,
        totalGoal: { $sum: "$funding.goalAmount" },
        totalRaised: { $sum: "$funding.raisedAmount" },
        avgProgress: {
          $avg: {
            $multiply: [
              { $divide: ["$funding.raisedAmount", "$funding.goalAmount"] },
              100
            ]
          }
        }
      }}
    ]);
    console.log('\nFunding progress:', fundingProgress[0]);

  } catch (error) {
    console.error('Error importing campaigns:', error);
  } finally {
    await mongoose.disconnect();
    console.log('\nDisconnected from MongoDB');
  }
}

// Run the import
importCampaigns().then(() => {
  console.log('Campaign import completed');
});

module.exports = {
  Campaign,
  importCampaigns
};
