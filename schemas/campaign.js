const mongoose = require('mongoose');

const campaignSchema = new mongoose.Schema({
  title: {
    type: String,
    required: true,
    trim: true
  },
  description: {
    type: String,
    required: true
  },
  location: {
    country: {
      type: String,
      required: true
    },
    region: {
      type: String,
      required: true
    },
    coordinates: {
      latitude: Number,
      longitude: Number
    }
  },
  funding: {
    goalAmount: {
      type: Number,
      required: true
    },
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
    transactions: [{
      txHash: String,
      amount: Number,
      timestamp: Date,
      donorId: {
        type: mongoose.Schema.Types.ObjectId,
        ref: 'User'
      }
    }]
  }
});


const Campaign = mongoose.model('Campaign', campaignSchema);
module.exports = Campaign;


