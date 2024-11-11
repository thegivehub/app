const mongoose = require('mongoose');

const userSchema = new mongoose.Schema({
  type: String,  // donor, projectManager, localPartner, administrator
  status: String,  // active, suspended, pending
  personalInfo: {
    firstName: String,
    lastName: String,
    email: String,
    phone: String,
    avatar: String,
    timezone: String,
    language: String
  },
  authentication: {
    passwordHash: String,
    twoFactorEnabled: Boolean,
    lastLogin: Date,
    loginHistory: [{
      date: Date,
      ip: String,
      device: String
    }]
  },
  wallet: {
    stellarPublicKey: String,
    preferredCurrency: String,
    transactions: [{
      type: String,  // donation, withdrawal, refund
      amount: Number,
      currency: String,
      timestamp: Date,
      txHash: String,
      status: String
    }]
  },
  roles: [{
    type: String,
    organizationId: ObjectId,
    permissions: [String]
  }],
  preferences: {
    notifications: {
      email: Boolean,
      push: Boolean,
      updateFrequency: String,
      subscribedTopics: [String]
    },
    privacy: {
      isAnonymous: Boolean,
      shareActivity: Boolean
    },
    interests: [String]  // categories of projects
  },
  activity: {
    donations: [{
      campaignId: ObjectId,
      amount: Number,
      date: Date,
      txHash: String,
      status: String
    }],
    comments: [{
      campaignId: ObjectId,
      content: String,
      date: Date,
      status: String  // active, deleted, flagged
    }],
    volunteering: [{
      campaignId: ObjectId,
      role: String,
      hours: Number,
      startDate: Date,
      endDate: Date
    }]
  }
});

const User = mongoose.model('User', userSchema);
module.exports = User;


