// import-impact-metrics.js
const mongoose = require('mongoose');
require('dotenv').config();

// Define subdocument schemas
const metricHistorySchema = new mongoose.Schema({
  value: Number,
  date: Date,
  verifiedBy: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User'
  }
});

const metricSchema = new mongoose.Schema({
  name: String,
  baseline: Number,
  current: Number,
  target: Number,
  unit: String,
  verificationMethod: String,
  frequency: String,
  history: [metricHistorySchema]
});

const sdgAlignmentSchema = new mongoose.Schema({
  goalNumber: Number,
  targets: [String],
  contribution: String
});

const demographicSchema = new mongoose.Schema({
  category: String,
  count: Number
});

const verificationSchema = new mongoose.Schema({
  verifiedBy: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User'
  },
  date: Date,
  method: String
});

const qualitativeDataSchema = new mongoose.Schema({
  type: String,
  date: Date,
  content: String,
  source: String,
  verification: verificationSchema
});

// Impact Metrics Schema
const impactMetricsSchema = new mongoose.Schema({
  campaignId: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Campaign',
    required: true
  },
  category: {
    type: String,
    required: true
  },
  baselineDate: Date,
  metrics: [metricSchema],
  sdgAlignment: [sdgAlignmentSchema],
  beneficiaries: {
    direct: Number,
    indirect: Number,
    demographics: [demographicSchema]
  },
  qualitativeData: [qualitativeDataSchema]
});

const ImpactMetric = mongoose.model('ImpactMetric', impactMetricsSchema);

// Sample Impact Metrics Data
const impactMetricsData = [
  {
    _id: new mongoose.Types.ObjectId(),
    campaignId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f9a"),
    category: "water-access",
    baselineDate: new Date("2024-03-01"),
    metrics: [
      {
        name: "Daily Water Access Hours",
        baseline: 2,
        current: 2,
        target: 24,
        unit: "hours",
        verificationMethod: "Community water point monitoring system",
        frequency: "daily",
        history: [
          {
            value: 2,
            date: new Date("2024-03-01"),
            verifiedBy: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f02")
          }
        ]
      },
      {
        name: "Average Water Collection Time",
        baseline: 240,
        current: 240,
        target: 15,
        unit: "minutes",
        verificationMethod: "Community surveys",
        frequency: "weekly",
        history: [
          {
            value: 240,
            date: new Date("2024-03-01"),
            verifiedBy: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f02")
          }
        ]
      }
    ],
    sdgAlignment: [
      {
        goalNumber: 6,
        targets: ["6.1", "6.4"],
        contribution: "Providing clean water access to underserved communities"
      },
      {
        goalNumber: 3,
        targets: ["3.3", "3.9"],
        contribution: "Reducing water-borne diseases through clean water access"
      }
    ],
    beneficiaries: {
      direct: 2500,
      indirect: 5000,
      demographics: [
        {
          category: "women",
          count: 1300
        },
        {
          category: "children",
          count: 800
        },
        {
          category: "elderly",
          count: 400
        }
      ]
    },
    qualitativeData: [
      {
        type: "baseline_survey",
        date: new Date("2024-03-01"),
        content: "Community currently relies on distant water sources, affecting productivity and health",
        source: "Community needs assessment",
        verification: {
          verifiedBy: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f04"),
          date: new Date("2024-03-02"),
          method: "Field verification"
        }
      }
    ]
  },
  {
    _id: new mongoose.Types.ObjectId(),
    campaignId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f9e"),
    category: "healthcare",
    baselineDate: new Date("2024-03-10"),
    metrics: [
      {
        name: "Healthcare Access Time",
        baseline: 180,
        current: 180,
        target: 30,
        unit: "minutes",
        verificationMethod: "Community surveys",
        frequency: "monthly",
        history: [
          {
            value: 180,
            date: new Date("2024-03-10"),
            verifiedBy: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f06")
          }
        ]
      }
    ],
    sdgAlignment: [
      {
        goalNumber: 3,
        targets: ["3.8"],
        contribution: "Improving healthcare accessibility in remote regions"
      }
    ],
    beneficiaries: {
      direct: 5000,
      indirect: 12000,
      demographics: [
        {
          category: "women",
          count: 2600
        },
        {
          category: "children",
          count: 1500
        }
      ]
    }
  }
];

// Database connection and import function
async function importImpactMetrics() {
  try {
    await mongoose.connect('mongodb://localhost:27017/givehub', {
      useNewUrlParser: true,
      useUnifiedTopology: true
    });
    console.log('Connected to MongoDB');

    // Clear existing impact metrics
    await ImpactMetric.deleteMany({});
    console.log('Cleared existing impact metrics');

    // Insert new impact metrics
    const result = await ImpactMetric.insertMany(impactMetricsData);
    console.log(`Successfully imported ${result.length} impact metric records`);

    // Verification queries
    console.log('\nVerifying imported data:');

    // Metrics by category
    const metricsByCategory = await ImpactMetric.aggregate([
      { $group: { _id: "$category", count: { $sum: 1 } } }
    ]);
    console.log('\nMetrics by category:', metricsByCategory);

    // Total beneficiaries
    const totalBeneficiaries = await ImpactMetric.aggregate([
      { $group: {
        _id: null,
        directBeneficiaries: { $sum: "$beneficiaries.direct" },
        indirectBeneficiaries: { $sum: "$beneficiaries.indirect" }
      }}
    ]);
    console.log('\nTotal beneficiaries:', totalBeneficiaries[0]);

    // SDG coverage
    const sdgCoverage = await ImpactMetric.aggregate([
      { $unwind: "$sdgAlignment" },
      { $group: {
        _id: "$sdgAlignment.goalNumber",
        campaigns: { $addToSet: "$campaignId" }
      }}
    ]);
    console.log('\nSDG coverage:', sdgCoverage);

  } catch (error) {
    console.error('Error importing impact metrics:', error);
  } finally {
    await mongoose.disconnect();
    console.log('\nDisconnected from MongoDB');
  }
}

// Run the import
importImpactMetrics().then(() => {
  console.log('Impact metrics import completed');
});

module.exports = {
  ImpactMetric,
  importImpactMetrics
};
