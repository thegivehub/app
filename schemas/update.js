const mongoose = require('mongoose');

const updateSchema = new mongoose.Schema({
  campaignId: ObjectId,
  type: String,  // milestone, general, emergency, success
  title: String,
  content: String,
  date: Date,
  author: {
    userId: ObjectId,
    role: String  // projectManager, localPartner, administrator
  },
  media: [{
    type: String,  // image, video, document
    url: String,
    caption: String,
    timestamp: Date
  }],
  metrics: [{
    name: String,
    value: Number,
    previousValue: Number,
    unit: String
  }],
  verification: {
    status: String,
    verifiedBy: ObjectId,
    verificationDate: Date,
    notes: String
  }
});

const Update = mongoose.model('Update', updateSchema);
module.exports = Update;



