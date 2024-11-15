const mongoose = require('mongoose');

// Media Schema
const mediaSchema = new mongoose.Schema({
  type: {
    type: String,
    required: true,
    enum: ['image', 'video', 'document', 'audio']
  },
  url: {
    type: String,
    required: true
  },
  caption: String,
  timestamp: {
    type: Date,
    default: Date.now
  },
  size: Number,
  mimeType: String,
  thumbnail: String,
  metadata: {
    width: Number,
    height: Number,
    duration: Number,
    location: {
      latitude: Number,
      longitude: Number
    }
  },
  status: {
    type: String,
    enum: ['uploading', 'processed', 'failed'],
    default: 'processed'
  }
});

// Metric Update Schema
const metricUpdateSchema = new mongoose.Schema({
  name: {
    type: String,
    required: true
  },
  value: {
    type: Number,
    required: true
  },
  previousValue: {
    type: Number,
    required: true
  },
  unit: {
    type: String,
    required: true
  },
  changePercentage: {
    type: Number,
    default: function() {
      if (this.previousValue === 0) return 100;
      return ((this.value - this.previousValue) / this.previousValue) * 100;
    }
  },
  trend: {
    type: String,
    enum: ['increasing', 'decreasing', 'stable'],
    default: function() {
      if (this.value > this.previousValue) return 'increasing';
      if (this.value < this.previousValue) return 'decreasing';
      return 'stable';
    }
  },
  context: String
});

// Enhanced Update Schema
const updateSchema = new mongoose.Schema({
  campaignId: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Campaign',
    required: true,
    index: true
  },
  type: {
    type: String,
    required: true,
    enum: ['milestone', 'general', 'emergency', 'success', 'challenge', 'impact'],
    index: true
  },
  title: {
    type: String,
    required: true,
    trim: true
  },
  content: {
    type: String,
    required: true
  },
  summary: {
    type: String,
    maxlength: 280 // For social sharing
  },
  date: {
    type: Date,
    default: Date.now,
    index: true
  },
  author: {
    userId: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User',
      required: true
    },
    role: {
      type: String,
      required: true,
      enum: ['projectManager', 'localPartner', 'administrator', 'verifier']
    },
    organization: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'Organization'
    }
  },
  media: [mediaSchema],
  metrics: [metricUpdateSchema],
  verification: {
    status: {
      type: String,
      enum: ['pending', 'verified', 'rejected', 'flagged'],
      default: 'pending'
    },
    verifiedBy: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User'
    },
    verificationDate: Date,
    notes: String,
    method: String,
    evidenceUrls: [String]
  },
  visibility: {
    type: String,
    enum: ['public', 'private', 'donors_only'],
    default: 'public'
  },
  importance: {
    type: String,
    enum: ['low', 'medium', 'high', 'critical'],
    default: 'medium'
  },
  tags: [{
    type: String,
    index: true
  }],
  relatedUpdates: [{
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Update'
  }],
  notifications: {
    email: {
      sent: Boolean,
      sentAt: Date,
      recipients: Number
    },
    push: {
      sent: Boolean,
      sentAt: Date,
      recipients: Number
    }
  },
  engagement: {
    views: { type: Number, default: 0 },
    reactions: { type: Number, default: 0 },
    comments: { type: Number, default: 0 }
  }
}, {
  timestamps: true
});

// Indexes
updateSchema.index({ 'metrics.name': 1 });
updateSchema.index({ 'verification.status': 1 });
updateSchema.index({ campaignId: 1, date: -1 });

// Virtuals
updateSchema.virtual('isRecent').get(function() {
  const twentyFourHoursAgo = new Date(Date.now() - 24 * 60 * 60 * 1000);
  return this.date >= twentyFourHoursAgo;
});

updateSchema.virtual('verificationAge').get(function() {
  if (!this.verification.verificationDate) return null;
  return Date.now() - this.verification.verificationDate;
});

// Methods
updateSchema.methods.verify = async function(verifierId, notes = '') {
  this.verification.status = 'verified';
  this.verification.verifiedBy = verifierId;
  this.verification.verificationDate = new Date();
  this.verification.notes = notes;
  await this.save();
  
  // Update impact metrics if applicable
  if (this.metrics.length > 0) {
    await this.updateImpactMetrics();
  }
};

updateSchema.methods.updateImpactMetrics = async function() {
  const ImpactMetrics = mongoose.model('ImpactMetrics');
  const metrics = await ImpactMetrics.findOne({ campaignId: this.campaignId });
  
  if (metrics) {
    for (const metricUpdate of this.metrics) {
      const metric = metrics.metrics.find(m => m.name === metricUpdate.name);
      if (metric) {
        metric.current = metricUpdate.value;
        metric.history.push({
          value: metricUpdate.value,
          date: this.date,
          verifiedBy: this.verification.verifiedBy
        });
      }
    }
    await metrics.save();
  }
};

updateSchema.methods.sendNotifications = async function() {
  // Implement notification logic
};

// Statics
updateSchema.statics.getRecentUpdates = async function(campaignId, limit = 10) {
  return this.find({ 
    campaignId,
    visibility: 'public',
    verification.status: 'verified'
  })
  .sort({ date: -1 })
  .limit(limit)
  .populate('author.userId', 'profile.firstName profile.lastName')
  .populate('author.organization', 'name');
};

updateSchema.statics.getMetricHistory = async function(campaignId, metricName) {
  return this.aggregate([
    { $match: { 
      campaignId: mongoose.Types.ObjectId(campaignId),
      'metrics.name': metricName,
      'verification.status': 'verified'
    }},
    { $unwind: '$metrics' },
    { $match: { 'metrics.name': metricName }},
    { $sort: { date: 1 }},
    { $project: {
      date: 1,
      value: '$metrics.value',
      previousValue: '$metrics.previousValue',
      change: '$metrics.changePercentage'
    }}
  ]);
};

// Middleware
updateSchema.pre('save', async function(next) {
  if (this.isNew) {
    // Generate summary if not provided
    if (!this.summary && this.content) {
      this.summary = this.content.substring(0, 277) + '...';
    }
  }
  next();
});

const Update = mongoose.model('Update', updateSchema);
module.exports = Update;

/**
 * TODO:
 * 1. Add more query methods
 * 2. Create notification handlers
 * 3. Add analytic functions
 * 4. Include comment/reaction handling
 *
 **/

