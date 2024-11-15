const mongoose = require('mongoose');

// History Entry Schema
const historyEntrySchema = new mongoose.Schema({
  value: {
    type: Number,
    required: true
  },
  date: {
    type: Date,
    default: Date.now
  },
  verifiedBy: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: true
  },
  notes: String,
  evidence: [String],  // URLs to evidence documents/photos
  method: String
});

// Metric Schema
const metricSchema = new mongoose.Schema({
  name: {
    type: String,
    required: true
  },
  baseline: {
    type: Number,
    required: true
  },
  current: {
    type: Number,
    required: true
  },
  target: {
    type: Number,
    required: true
  },
  unit: {
    type: String,
    required: true
  },
  verificationMethod: {
    type: String,
    required: true
  },
  frequency: {
    type: String,
    enum: ['daily', 'weekly', 'monthly', 'quarterly', 'annually'],
    required: true
  },
  history: [historyEntrySchema],
  status: {
    type: String,
    enum: ['on_track', 'at_risk', 'behind', 'completed'],
    default: 'on_track'
  },
  lastUpdated: Date,
  nextUpdateDue: Date
});

// SDG Target Schema
const sdgTargetSchema = new mongoose.Schema({
  goalNumber: {
    type: Number,
    required: true,
    min: 1,
    max: 17
  },
  targets: [{
    type: String,
    validate: {
      validator: function(v) {
        return /^\d+\.\d+$/.test(v);  // Format: "1.1", "1.2", etc.
      },
      message: props => `${props.value} is not a valid SDG target format!`
    }
  }],
  contribution: {
    type: String,
    required: true
  },
  alignmentStrength: {
    type: String,
    enum: ['direct', 'indirect', 'enabling'],
    default: 'direct'
  }
});

// Demographics Schema
const demographicsSchema = new mongoose.Schema({
  category: {
    type: String,
    required: true,
    enum: [
      'children', 'youth', 'adults', 'elderly',
      'women', 'men', 'families', 'students',
      'farmers', 'entrepreneurs', 'healthcare_workers'
    ]
  },
  count: {
    type: Number,
    required: true,
    min: 0
  },
  verifiedCount: {
    type: Boolean,
    default: false
  },
  methodology: String
});

// Qualitative Data Schema
const qualitativeDataSchema = new mongoose.Schema({
  type: {
    type: String,
    required: true,
    enum: ['testimonial', 'case_study', 'observation', 'interview', 'survey']
  },
  date: {
    type: Date,
    required: true
  },
  content: {
    type: String,
    required: true
  },
  source: {
    type: String,
    required: true
  },
  mediaUrls: [String],
  verification: {
    verifiedBy: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User',
      required: true
    },
    date: {
      type: Date,
      required: true
    },
    method: {
      type: String,
      required: true
    },
    status: {
      type: String,
      enum: ['pending', 'verified', 'rejected'],
      default: 'pending'
    }
  },
  tags: [String]
});

// Enhanced Impact Metrics Schema
const impactMetricsSchema = new mongoose.Schema({
  campaignId: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Campaign',
    required: true,
    index: true
  },
  category: {
    type: String,
    required: true,
    enum: ['education', 'water', 'health', 'agriculture', 'infrastructure', 'energy', 'economic'],
    index: true
  },
  baselineDate: {
    type: Date,
    required: true
  },
  status: {
    type: String,
    enum: ['collecting_baseline', 'monitoring', 'completed', 'suspended'],
    default: 'collecting_baseline'
  },
  metrics: [metricSchema],
  sdgAlignment: [sdgTargetSchema],
  beneficiaries: {
    direct: {
      type: Number,
      default: 0,
      min: 0
    },
    indirect: {
      type: Number,
      default: 0,
      min: 0
    },
    demographics: [demographicsSchema],
    verificationMethod: String,
    lastVerified: Date
  },
  qualitativeData: [qualitativeDataSchema],
  riskAssessment: {
    level: {
      type: String,
      enum: ['low', 'medium', 'high'],
      default: 'low'
    },
    factors: [String],
    mitigationStrategies: [String]
  },
  verificationSchedule: {
    frequency: String,
    lastVerification: Date,
    nextVerification: Date,
    responsibleParty: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'Organization'
    }
  }
}, {
  timestamps: true
});

// Indexes
impactMetricsSchema.index({ 'metrics.current': 1 });
impactMetricsSchema.index({ 'sdgAlignment.goalNumber': 1 });
impactMetricsSchema.index({ 'beneficiaries.direct': 1, 'beneficiaries.indirect': 1 });

// Virtuals
impactMetricsSchema.virtual('totalBeneficiaries').get(function() {
  return this.beneficiaries.direct + this.beneficiaries.indirect;
});

impactMetricsSchema.virtual('verificationStatus').get(function() {
  if (!this.verificationSchedule.nextVerification) return 'not_scheduled';
  return new Date() > this.verificationSchedule.nextVerification ? 'overdue' : 'current';
});

// Methods
impactMetricsSchema.methods.updateMetric = async function(metricId, newValue, verifierId) {
  const metric = this.metrics.id(metricId);
  if (!metric) throw new Error('Metric not found');
  
  metric.history.push({
    value: newValue,
    date: new Date(),
    verifiedBy: verifierId
  });
  
  metric.current = newValue;
  metric.lastUpdated = new Date();
  metric.status = this.calculateMetricStatus(metric);
  
  await this.save();
  return metric;
};

impactMetricsSchema.methods.calculateMetricStatus = function(metric) {
  const progress = (metric.current - metric.baseline) / (metric.target - metric.baseline) * 100;
  if (progress >= 100) return 'completed';
  if (progress >= 80) return 'on_track';
  if (progress >= 50) return 'at_risk';
  return 'behind';
};

// Statics
impactMetricsSchema.statics.getSDGImpact = async function() {
  return this.aggregate([
    { $unwind: '$sdgAlignment' },
    { $group: {
      _id: '$sdgAlignment.goalNumber',
      totalProjects: { $sum: 1 },
      totalBeneficiaries: { $sum: '$beneficiaries.direct' },
      contributions: { $push: '$sdgAlignment.contribution' }
    }},
    { $sort: { _id: 1 } }
  ]);
};

impactMetricsSchema.statics.getCategoryImpact = async function() {
  return this.aggregate([
    { $group: {
      _id: '$category',
      metrics: { 
        $push: {
          name: '$metrics.name',
          current: '$metrics.current',
          target: '$metrics.target'
        }
      },
      beneficiaries: {
        $sum: { $add: ['$beneficiaries.direct', '$beneficiaries.indirect'] }
      }
    }}
  ]);
};

// Middleware
impactMetricsSchema.pre('save', function(next) {
  // Update metric statuses
  this.metrics.forEach(metric => {
    metric.status = this.calculateMetricStatus(metric);
  });
  next();
});

const ImpactMetrics = mongoose.model('ImpactMetrics', impactMetricsSchema);
module.exports = ImpactMetrics;

