const mongoose = require('mongoose');

const organizationSchema = new mongoose.Schema( {
  name: String,
  type: String,  // NGO, Government, Community, Corporate
  status: String,  // active, pending, suspended
  legalInfo: {
    registrationNumber: String,
    taxId: String,
    country: String,
    registrationDate: Date,
    documents: [{
      type: String,
      url: String,
      expiryDate: Date,
      verified: Boolean
    }]
  },
  contacts: [{
    userId: ObjectId,
    role: String,
    isPrimary: Boolean,
    department: String
  }],
  location: {
    address: String,
    city: String,
    region: String,
    country: String,
    coordinates: {
      latitude: Number,
      longitude: Number
    }
  },
  finance: {
    stellarAccount: String,
    bankDetails: {
      bankName: String,
      accountNumber: String,
      swiftCode: String
    },
    reportingCurrency: String
  },
  verification: {
    status: String,
    verifiedBy: ObjectId,
    verificationDate: Date,
    documents: [{
      type: String,
      url: String,
      status: String,
      notes: String
    }]
  },
  projects: [{
    campaignId: ObjectId,
    role: String,  // lead, partner, supporter
    status: String
  }],
  ratings: {
    overallScore: Number,
    totalReviews: Number,
    categories: [{
      name: String,  // transparency, impact, communication
      score: Number
    }]
  }
});

const Organization = mongoose.model('Organization', organizationSchema);
module.exports = Organization;



