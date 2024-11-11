require('mongoose');

const impactMetricsSchema = new mongoose.Schema({
  campaignId: ObjectId,
  category: String,  // education, water, health, etc.
  baselineDate: Date,
  metrics: [{
    name: String,
    baseline: Number,
    current: Number,
    target: Number,
    unit: String,
    verificationMethod: String,
    frequency: String,  // daily, weekly, monthly
    history: [{
      value: Number,
      date: Date,
      verifiedBy: ObjectId
    }]
  }],
  sdgAlignment: [{
    goalNumber: Number,
    targets: [String],
    contribution: String
  }],
  beneficiaries: {
    direct: Number,
    indirect: Number,
    demographics: [{
      category: String,
      count: Number
    }]
  },
  qualitativeData: [{
    type: String,  // testimonial, case study, observation
    date: Date,
    content: String,
    source: String,
    verification: {
      verifiedBy: ObjectId,
      date: Date,
      method: String
    }
  }]
});

const ImpactMetrics = mongoose.model('ImpactMetrics', impactMetricsSchema);
module.exports = ImpactMetrics;




