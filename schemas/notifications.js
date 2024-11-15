const mongoose = require('mongoose');

// Delivery Attempt Schema
const deliveryAttemptSchema = new mongoose.Schema({
  method: {
    type: String,
    enum: ['email', 'push', 'in-app', 'sms'],
    required: true
  },
  timestamp: {
    type: Date,
    default: Date.now
  },
  status: {
    type: String,
    enum: ['pending', 'sent', 'delivered', 'failed', 'bounced'],
    default: 'pending'
  },
  provider: String,
  errorMessage: String,
  metadata: {
    messageId: String,
    deviceInfo: String,
    emailAddress: String,
    pushToken: String
  }
});

// Action Schema
const actionSchema = new mongoose.Schema({
  type: {
    type: String,
    enum: ['view', 'donate', 'verify', 'respond', 'acknowledge'],
    required: true
  },
  link: {
    type: String,
    required: true
  },
  completedAt: Date,
  response: mongoose.Schema.Types.Mixed
});

// Enhanced Notification Schema
const notificationSchema = new mongoose.Schema({
  type: {
    type: String,
    required: true,
    enum: [
      'campaign_update',
      'donation_received',
      'milestone_completed',
      'verification_required',
      'goal_reached',
      'alert',
      'thank_you',
      'impact_report'
    ],
    index: true
  },
  priority: {
    type: String,
    enum: ['high', 'medium', 'low'],
    default: 'medium',
    index: true
  },
  status: {
    type: String,
    enum: ['unread', 'read', 'archived', 'deleted'],
    default: 'unread',
    index: true
  },
  timestamp: {
    type: Date,
    default: Date.now,
    index: true
  },
  expiryDate: {
    type: Date,
    default: function() {
      const d = new Date();
      d.setDate(d.getDate() + 30); // 30 days default expiry
      return d;
    }
  },
  recipient: {
    userId: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User',
      required: true,
      index: true
    },
    deliveryMethods: [{
      type: String,
      enum: ['email', 'push', 'in-app', 'sms']
    }],
    preferences: {
      email: String,
      pushTokens: [String],
      language: String,
      timezone: String
    }
  },
  content: {
    title: {
      type: String,
      required: true
    },
    body: {
      type: String,
      required: true
    },
    action: actionSchema,
    template: String,
    variables: mongoose.Schema.Types.Mixed,
    localizations: {
      type: Map,
      of: {
        title: String,
        body: String
      }
    }
  },
  referenceData: {
    entityType: {
      type: String,
      enum: ['campaign', 'donation', 'update', 'verification', 'impact'],
      required: true
    },
    entityId: {
      type: mongoose.Schema.Types.ObjectId,
      required: true,
      refPath: 'referenceData.entityType'
    },
    context: mongoose.Schema.Types.Mixed
  },
  delivery: {
    attempts: [deliveryAttemptSchema],
    lastDelivered: Date,
    nextRetry: Date,
    maxRetries: {
      type: Number,
      default: 3
    },
    retryCount: {
      type: Number,
      default: 0
    },
    batchId: String
  },
  engagement: {
    openedAt: Date,
    clickedAt: Date,
    device: String,
    location: String
  },
  metadata: {
    campaign: String,
    segment: String,
    tags: [String]
  }
}, {
  timestamps: true
});

// Indexes
notificationSchema.index({ 'recipient.userId': 1, 'timestamp': -1 });
notificationSchema.index({ 'delivery.nextRetry': 1 }, { sparse: true });
notificationSchema.index({ 'expiryDate': 1 });
notificationSchema.index({ 
  'type': 1, 
  'status': 1, 
  'priority': 1 
});

// Virtuals
notificationSchema.virtual('isExpired').get(function() {
  return this.expiryDate && this.expiryDate < new Date();
});

notificationSchema.virtual('deliveryStatus').get(function() {
  if (!this.delivery.attempts.length) return 'pending';
  const lastAttempt = this.delivery.attempts[this.delivery.attempts.length - 1];
  return lastAttempt.status;
});

// Methods
notificationSchema.methods.markAsRead = async function() {
  this.status = 'read';
  this.engagement.openedAt = new Date();
  await this.save();
};

notificationSchema.methods.archive = async function() {
  this.status = 'archived';
  await this.save();
};

notificationSchema.methods.recordDeliveryAttempt = async function(method, status, metadata = {}) {
  this.delivery.attempts.push({
    method,
    status,
    timestamp: new Date(),
    metadata
  });

  if (status === 'delivered') {
    this.delivery.lastDelivered = new Date();
  } else if (status === 'failed' && this.delivery.retryCount < this.delivery.maxRetries) {
    this.delivery.retryCount += 1;
    this.delivery.nextRetry = new Date(Date.now() + (this.delivery.retryCount * 5 * 60 * 1000)); // exponential backoff
  }

  await this.save();
};

// Statics
notificationSchema.statics.getPendingDeliveries = async function() {
  const now = new Date();
  return this.find({
    'delivery.nextRetry': { $lte: now },
    'delivery.retryCount': { $lt: this.delivery.maxRetries },
    status: { $ne: 'deleted' }
  }).sort('delivery.nextRetry');
};

notificationSchema.statics.getUnreadByUser = async function(userId, limit = 20) {
  return this.find({
    'recipient.userId': userId,
    status: 'unread',
    expiryDate: { $gt: new Date() }
  })
  .sort('-priority -timestamp')
  .limit(limit);
};

notificationSchema.statics.createFromTemplate = async function(template, userData, entityData) {
  // Implementation for creating notifications from templates
};

// Middleware
notificationSchema.pre('save', function(next) {
  if (this.isNew) {
    // Set default delivery methods based on type and priority
    if (this.priority === 'high') {
      this.recipient.deliveryMethods = ['email', 'push', 'in-app'];
    } else {
      this.recipient.deliveryMethods = ['in-app'];
    }
  }
  next();
});

// Clean up expired notifications
notificationSchema.statics.cleanupExpired = async function() {
  const thirtyDaysAgo = new Date();
  thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
  
  return this.deleteMany({
    $or: [
      { expiryDate: { $lt: new Date() } },
      { 
        status: 'read',
        timestamp: { $lt: thirtyDaysAgo }
      }
    ]
  });
};

const Notification = mongoose.model('Notification', notificationSchema);
module.exports = Notification;

/** 
 * TODO:
 *  1. Add template handling
 *  2. Create delivery providers
 *  3. Add more analytics
 *  4. Include batch processing logic
 **/
