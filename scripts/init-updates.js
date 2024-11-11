// import-updates.js
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

const metricSchema = new mongoose.Schema({
  name: String,
  value: Number,
  previousValue: Number,
  unit: String
});

// Update Schema
const updateSchema = new mongoose.Schema({
  campaignId: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'Campaign',
    required: true
  },
  type: {
    type: String,
    enum: ['milestone', 'progress', 'success', 'challenge', 'general'],
    required: true
  },
  title: {
    type: String,
    required: true
  },
  content: String,
  date: {
    type: Date,
    default: Date.now
  },
  author: {
    userId: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User'
    },
    role: String
  },
  media: [mediaSchema],
  metrics: [metricSchema],
  verification: {
    status: {
      type: String,
      enum: ['pending', 'verified', 'rejected'],
      default: 'pending'
    },
    verifiedBy: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User'
    },
    verificationDate: Date,
    notes: String
  }
});

const Update = mongoose.model('Update', updateSchema);

// Sample Updates Data
const updatesData = [
  {
    _id: new mongoose.Types.ObjectId(),
    campaignId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f9a"),
    type: "milestone",
    title: "Environmental Impact Assessment Complete",
    content: "Successfully completed the environmental impact assessment for the water pipeline project. All necessary permits have been obtained from local authorities.",
    date: new Date("2024-03-14T16:30:00Z"),
    author: {
      userId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f02"), // John Kamau
      role: "projectManager"
    },
    media: [{
      type: "document",
      url: "docs/environmental_assessment.pdf",
      caption: "Environmental Impact Assessment Report",
      timestamp: new Date("2024-03-14T16:30:00Z")
    },
    {
      type: "image",
      url: "images/site_survey.jpg",
      caption: "Site survey team conducting assessment",
      timestamp: new Date("2024-03-14T15:45:00Z")
    }],
    metrics: [{
      name: "Environmental Impact Score",
      value: 8.5,
      previousValue: 0,
      unit: "rating"
    }],
    verification: {
      status: "verified",
      verifiedBy: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f04"),
      verificationDate: new Date("2024-03-14T17:30:00Z"),
      notes: "All documentation complete and verified"
    }
  },
  {
    _id: new mongoose.Types.ObjectId(),
    campaignId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f9e"),
    type: "progress",
    title: "Land Acquisition Process Started",
    content: "Initiated discussions with local authorities for clinic location. Community leaders have identified three potential sites.",
    date: new Date("2024-03-12T09:15:22Z"),
    author: {
      userId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f06"),
      role: "localPartner"
    },
    media: [{
      type: "image",
      url: "images/potential_site_1.jpg",
      caption: "Primary proposed clinic location",
      timestamp: new Date("2024-03-12T09:00:00Z")
    }],
    verification: {
      status: "pending",
      notes: "Awaiting final site selection"
    }
  },
  {
    _id: new mongoose.Types.ObjectId(),
    campaignId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8fa0"),
    type: "success",
    title: "First Farmer Training Batch Complete",
    content: "Successfully completed training for our first group of 50 farmers.",
    date: new Date("2024-01-28T14:20:00Z"),
    author: {
      userId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f09"),
      role: "projectManager"
    },
    media: [{
      type: "image",
      url: "images/training_session.jpg",
      caption: "Farmers during practical training session",
      timestamp: new Date("2024-01-28T11:30:00Z")
    }],
    metrics: [{
      name: "Farmers Trained",
      value: 50,
      previousValue: 0,
      unit: "people"
    }],
    verification: {
      status: "verified",
      verifiedBy: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f04"),
      verificationDate: new Date("2024-01-29T10:00:00Z"),
      notes: "All training objectives met"
    }
  }
];

// Database connection and import function
async function importUpdates() {
  try {
    await mongoose.connect('mongodb://localhost:27017/givehub', {
      useNewUrlParser: true,
      useUnifiedTopology: true
    });
    console.log('Connected to MongoDB');

    // Clear existing updates
    await Update.deleteMany({});
    console.log('Cleared existing updates');

    // Insert new updates
    const result = await Update.insertMany(updatesData);
    console.log(`Successfully imported ${result.length} updates`);

    // Verification queries
    console.log('\nVerifying imported data:');

    // Updates by type
    const updatesByType = await Update.aggregate([
      { $group: { _id: "$type", count: { $sum: 1 } } }
    ]);
    console.log('\nUpdates by type:', updatesByType);

    // Updates by verification status
    const updatesByVerification = await Update.aggregate([
      { $group: { _id: "$verification.status", count: { $sum: 1 } } }
    ]);
    console.log('\nUpdates by verification status:', updatesByVerification);

    // Updates with media
    const updatesWithMedia = await Update.aggregate([
      { $project: { 
        title: 1, 
        mediaCount: { $size: "$media" } 
      }}
    ]);
    console.log('\nUpdates with media:', updatesWithMedia);

  } catch (error) {
    console.error('Error importing updates:', error);
  } finally {
    await mongoose.disconnect();
    console.log('\nDisconnected from MongoDB');
  }
}

// Run the import
importUpdates().then(() => {
  console.log('Update import completed');
});

module.exports = {
  Update,
  importUpdates
};
