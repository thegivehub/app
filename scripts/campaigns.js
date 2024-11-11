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

// ... (previous schema definitions remain the same)

const campaignsData = [
  // 1. Water Pipeline - Active, 60% Funded
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
        },
        {
          txHash: "82g65e4cb2gg5e8",
          amount: 10000,
          timestamp: new Date("2024-03-11T09:15:22Z"),
          donorId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f03")
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
        },
        {
          title: "Pipeline Material Procurement",
          description: "Purchase and deliver all necessary materials",
          targetDate: new Date("2024-04-01T00:00:00Z"),
          completedDate: null,
          status: "pending"
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
        },
        {
          name: "Households Served",
          baseline: 0,
          current: 0,
          target: 500,
          unit: "households"
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
  },

  // 2. Medical Clinic - Just Started
  {
    _id: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f9e"),
    title: "Solar-Powered Medical Clinic - Chocó",
    description: "Construction of a permanent medical clinic serving 12 remote villages, powered by renewable energy and equipped with telemedicine capabilities.",
    location: {
      country: "Colombia",
      region: "Chocó",
      coordinates: {
        latitude: 5.6919,
        longitude: -76.6583
      }
    },
    funding: {
      goalAmount: 200000,
      raisedAmount: 30000,
      currency: "XLM",
      donorCount: 45,
      transactions: [
        {
          txHash: "89e32f1ca2ee5f8",
          amount: 10000,
          timestamp: new Date("2024-03-12T09:15:22Z"),
          donorId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f05")
        }
      ]
    },
    timeline: {
      created: new Date("2024-03-01T00:00:00Z"),
      startDate: new Date("2024-03-10T00:00:00Z"),
      endDate: new Date("2024-11-10T00:00:00Z"),
      milestones: [
        {
          title: "Land Acquisition",
          description: "Secure and verify land for clinic construction",
          targetDate: new Date("2024-04-01T00:00:00Z"),
          status: "pending"
        }
      ]
    },
    impact: {
      beneficiariesCount: 5000,
      metrics: [
        {
          name: "Healthcare Access Time",
          baseline: 180,
          current: 180,
          target: 30,
          unit: "minutes"
        }
      ],
      sdgGoals: [3]
    },
    verification: {
      status: "verified",
      localPartners: [
        {
          organizationId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f9d"),
          role: "implementingPartner",
          verificationDocuments: ["local_health_authority_approval.pdf"]
        }
      ]
    },
    media: [
      {
        type: "image",
        url: "images/clinic/proposed_site.jpg",
        caption: "Proposed clinic location",
        timestamp: new Date("2024-03-01T00:00:00Z")
      }
    ],
    category: "healthcare",
    tags: ["healthcare", "renewable-energy", "telemedicine"],
    status: "active",
    visibility: "public"
  },

  // 3. Agricultural Training - Completed
  {
    _id: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8fa0"),
    title: "Agricultural Training Center - Kilifi",
    description: "Establishment of a demonstration farm and training facility to teach drought-resistant farming techniques to local farmers.",
    location: {
      country: "Kenya",
      region: "Kilifi County",
      coordinates: {
        latitude: -3.6305,
        longitude: 39.8499
      }
    },
    funding: {
      goalAmount: 150000,
      raisedAmount: 150000,
      currency: "XLM",
      donorCount: 312,
      transactions: [
        {
          txHash: "92h76f5dc3hh6f9",
          amount: 15000,
          timestamp: new Date("2023-10-15T10:30:00Z"),
          donorId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f02")
        }
      ]
    },
    timeline: {
      created: new Date("2023-10-01T00:00:00Z"),
      startDate: new Date("2023-10-15T00:00:00Z"),
      endDate: new Date("2024-02-15T00:00:00Z"),
      milestones: [
        {
          title: "Facility Construction",
          description: "Complete main training facility building",
          targetDate: new Date("2023-12-15T00:00:00Z"),
          completedDate: new Date("2023-12-12T16:30:00Z"),
          status: "completed"
        },
        {
          title: "First Training Batch",
          description: "Complete training for first group of 50 farmers",
          targetDate: new Date("2024-02-01T00:00:00Z"),
          completedDate: new Date("2024-01-28T14:20:00Z"),
          status: "completed"
        }
      ]
    },
    impact: {
      beneficiariesCount: 1200,
      metrics: [
        {
          name: "Farmers Trained",
          baseline: 0,
          current: 200,
          target: 200,
          unit: "farmers"
        },
        {
          name: "Crop Yield Increase",
          baseline: 0,
          current: 40,
          target: 35,
          unit: "percentage"
        }
      ],
      sdgGoals: [1, 2, 8]
    },
    verification: {
      status: "completed",
      localPartners: [
        {
          organizationId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8fa1"),
          role: "implementingPartner",
          verificationDocuments: ["training_completion_report.pdf"]
        }
      ],
      updates: [
        {
          date: new Date("2024-02-15T10:00:00Z"),
          description: "Final project completion report submitted and verified",
          mediaUrls: ["completion_ceremony.jpg", "impact_report.pdf"],
          verifiedBy: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f04")
        }
      ]
    },
    media: [
      {
        type: "image",
        url: "images/agri/training_session.jpg",
        caption: "Farmers during practical training session",
        timestamp: new Date("2024-01-28T11:30:00Z")
      },
      {
        type: "document",
        url: "docs/agri/completion_report.pdf",
        caption: "Project Completion Report",
        timestamp: new Date("2024-02-15T10:00:00Z")
      }
    ],
    category: "agriculture",
    tags: ["agriculture", "education", "sustainability"],
    status: "completed",
    visibility: "public"
  },

  // 4. School Infrastructure - Struggling
  {
    _id: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8fa2"),
    title: "School Infrastructure Project - Putumayo",
    description: "Construction of 6 new classrooms, computer lab, and modern facilities to improve education access for 300+ children.",
    location: {
      country: "Colombia",
      region: "Putumayo",
      coordinates: {
        latitude: 1.1537,
        longitude: -76.6478
      }
    },
    funding: {
      goalAmount: 175000,
      raisedAmount: 52500,
      currency: "XLM",
      donorCount: 89,
      transactions: [
        {
          txHash: "73i87g6ed4ii7g0",
          amount: 5000,
          timestamp: new Date("2024-02-01T15:45:00Z"),
          donorId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f01")
        }
      ]
    },
    timeline: {
      created: new Date("2024-01-15T00:00:00Z"),
      startDate: new Date("2024-02-01T00:00:00Z"),
      endDate: new Date("2024-05-01T00:00:00Z"),
      milestones: [
        {
          title: "Initial Planning Phase",
          description: "Complete architectural plans and permits",
          targetDate: new Date("2024-03-01T00:00:00Z"),
          completedDate: new Date("2024-03-10T00:00:00Z"),
          status: "completed"
        }
      ]
    },
    impact: {
      beneficiariesCount: 300,
      metrics: [
        {
          name: "Student Capacity",
          baseline: 150,
          current: 150,
          target: 450,
          unit: "students"
        }
      ],
      sdgGoals: [4]
    },
    verification: {
      status: "verified",
      updates: [
        {
          date: new Date("2024-03-12T15:30:00Z"),
          description: "Campaign needs additional promotion to meet funding goal",
          mediaUrls: ["current_school_conditions.jpg"],
          verifiedBy: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f04")
        }
      ]
    },
    media: [
      {
        type: "image",
        url: "images/school/current_conditions.jpg",
        caption: "Current school facilities",
        timestamp: new Date("2024-01-15T00:00:00Z")
      }
    ],
    category: "education",
    tags: ["education", "infrastructure", "technology"],
    status: "active",
    visibility: "public"
  },

  // 5. Community Health & Sanitation - Starting Implementation
  {
    _id: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8fa4"),
    title: "Community Health & Sanitation - Siaya County",
    description: "Construction of 50 household sanitation facilities and implementation of community waste management system.",
    location: {
      country: "Kenya",
      region: "Siaya County",
      coordinates: {
        latitude: 0.0607,
        longitude: 34.2881
      }
    },
    funding: {
      goalAmount: 100000,
      raisedAmount: 100000,
      currency: "XLM",
      donorCount: 256,
      transactions: [
        {
          txHash: "45j98h7fg5kk8h1",
          amount: 20000,
          timestamp: new Date("2024-02-15T11:20:00Z"),
          donorId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f05")
        }
      ]
    },
    timeline: {
      created: new Date("2024-02-01T00:00:00Z"),
      startDate: new Date("2024-02-15T00:00:00Z"),
      endDate: new Date("2024-07-15T00:00:00Z"),
      milestones: [
        {
          title: "Community Assessment",
          description: "Complete community needs assessment and location planning",
          targetDate: new Date("2024-03-01T00:00:00Z"),
          completedDate: new Date("2024-02-28T00:00:00Z"),
          status: "completed"
        },
        {
          title: "Initial Construction Phase",
          description: "Complete first 10 household sanitation facilities",
          targetDate: new Date("2024-04-01T00:00:00Z"),
          status: "in-progress"
        }
      ]
    },
    impact: {
      beneficiariesCount: 2000,
      metrics: [
        {
          name: "Sanitation Facilities",
          baseline: 0,
          current: 0,
          target: 50,
          unit: "facilities"
        },
        {
          name: "Households with Access",
          baseline: 0,
          current: 0,
          target: 400,
          unit: "households"
        },
        {
          name: "Waste Management Coverage",
          baseline: 10,
          current: 10,
          target: 100,
          unit: "percentage"
        }
      ],
      sdgGoals: [3, 6]
    },
    verification: {
      status: "verified",
      localPartners: [
        {
          organizationId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8fa1"),
          role: "implementingPartner",
          verificationDocuments: ["community_approval.pdf", "site_assessment.pdf"]
        }
      ],
      updates: [
        {
          date: new Date("2024-02-28T16:00:00Z"),
          description: "Community needs assessment completed and implementation plan approved",
          mediaUrls: ["community_meeting.jpg", "assessment_report.pdf"],
          verifiedBy: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f04")
        }
      ]
    },
    media: [
      {
        type: "image",
        url: "images/sanitation/community_meeting.jpg",
        caption: "Community planning meeting",
        timestamp: new Date("2024-02-27T14:30:00Z")
      },
      {
        type: "document",
        url: "docs/sanitation/implementation_plan.pdf",
        caption: "Detailed Implementation Plan",
        timestamp: new Date("2024-02-28T16:00:00Z")
      },
      {
        type: "image",
        url: "images/sanitation/proposed_design.jpg",
        caption: "Proposed sanitation facility design",
        timestamp: new Date("2024-02-28T16:00:00Z")
      }
    ],
    category: "sanitation",
    tags: ["sanitation", "health", "community", "infrastructure"],
    status: "active",
    visibility: "public"
  }
];

// Enhanced database connection and import function with additional verification queries
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

    // Comprehensive verification queries
    console.log('\nVerifying imported data:');

    // Campaigns by status
    const campaignsByStatus = await Campaign.aggregate([
      { $group: { _id: "$status", count: { $sum: 1 } } }
    ]);
    console.log('\nCampaigns by status:', campaignsByStatus);

    // Campaigns by country
    const campaignsByCountry = await Campaign.aggregate([
      { $group: { 
        _id: "$location.country",
        campaignCount: { $sum: 1 },
        totalFunding: { $sum: "$funding.raisedAmount" }
      }}
    ]);
    console.log('\nCampaigns by country:', campaignsByCountry);

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
    console.log('\nOverall funding progress:', fundingProgress[0]);

    // Impact metrics
    const impactMetrics = await Campaign.aggregate([
      { $group: {
        _id: null,
        totalBeneficiaries: { $sum: "$impact.beneficiariesCount" },
        uniqueSDGs: { $addToSet: "$impact.sdgGoals" }
      }}
    ]);
    console.log('\nImpact metrics:', impactMetrics[0]);

    // Category analysis
    const categoryAnalysis = await Campaign.aggregate([
      { $group: {
        _id: "$category",
        campaignCount: { $sum: 1 },
        totalFunding: { $sum: "$funding.raisedAmount" },
        avgDonors: { $avg: "$funding.donorCount" }
      }}
    ]);
    console.log('\nCategory analysis:', categoryAnalysis);

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
