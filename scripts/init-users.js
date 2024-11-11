// import-users.js
const mongoose = require('mongoose');
const bcrypt = require('bcrypt');
require('dotenv').config();

// User Schema
const userSchema = new mongoose.Schema({
  type: {
    type: String,
    enum: ['donor', 'projectManager', 'localPartner', 'administrator'],
    required: true
  },
  status: {
    type: String,
    enum: ['active', 'suspended', 'pending'],
    default: 'active'
  },
  personalInfo: {
    firstName: String,
    lastName: String,
    email: {
      type: String,
      required: true,
      unique: true
    },
    phone: String,
    avatar: String,
    timezone: String,
    language: {
      type: String,
      default: 'en'
    }
  },
  authentication: {
    passwordHash: String,
    twoFactorEnabled: {
      type: Boolean,
      default: false
    },
    lastLogin: Date,
    loginHistory: [{
      date: Date,
      ip: String,
      device: String
    }]
  },
  wallet: {
    stellarPublicKey: String,
    preferredCurrency: {
      type: String,
      default: 'XLM'
    },
    transactions: [{
      type: {
        type: String,
        enum: ['donation', 'withdrawal', 'refund']
      },
      amount: Number,
      currency: String,
      timestamp: Date,
      txHash: String,
      status: {
        type: String,
        enum: ['pending', 'completed', 'failed']
      }
    }]
  },
  roles: [{
    type: {
      type: String,
      required: true
    },
    organizationId: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'Organization'
    },
    permissions: [String]
  }],
  preferences: {
    notifications: {
      email: {
        type: Boolean,
        default: true
      },
      push: {
        type: Boolean,
        default: false
      },
      updateFrequency: {
        type: String,
        enum: ['daily', 'weekly', 'monthly'],
        default: 'weekly'
      },
      subscribedTopics: [String]
    },
    privacy: {
      isAnonymous: {
        type: Boolean,
        default: false
      },
      shareActivity: {
        type: Boolean,
        default: true
      }
    },
    interests: [String]
  },
  activity: {
    donations: [{
      campaignId: {
        type: mongoose.Schema.Types.ObjectId,
        ref: 'Campaign'
      },
      amount: Number,
      date: Date,
      txHash: String,
      status: String
    }],
    comments: [{
      campaignId: {
        type: mongoose.Schema.Types.ObjectId,
        ref: 'Campaign'
      },
      content: String,
      date: Date,
      status: {
        type: String,
        enum: ['active', 'deleted', 'flagged'],
        default: 'active'
      }
    }],
    volunteering: [{
      campaignId: {
        type: mongoose.Schema.Types.ObjectId,
        ref: 'Campaign'
      },
      role: String,
      hours: Number,
      startDate: Date,
      endDate: Date
    }]
  }
});

const User = mongoose.model('User', userSchema);

// The schema definition remains the same until generateSampleUsers...

async function generateSampleUsers() {
  const hashPassword = async (password) => {
    return await bcrypt.hash(password, 10);
  };

  return [
    {
      _id: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f01"),
      type: "administrator",
      status: "active",
      personalInfo: {
        firstName: "Alex",
        lastName: "Thompson",
        email: "alex.thompson@givehub.com",
        phone: "+1-415-555-0104",
        timezone: "America/New_York",
        language: "en"
      },
      authentication: {
        passwordHash: await hashPassword("adminSecure123!"),
        twoFactorEnabled: true,
        lastLogin: new Date("2024-03-15T14:30:00Z"),
        loginHistory: [{
          date: new Date("2024-03-15T14:30:00Z"),
          ip: "10.0.1.100",
          device: "Chrome/Windows"
        }]
      },
      roles: [{
        type: "administrator",
        permissions: ["manage_users", "manage_organizations", "manage_system", "verify_projects"]
      }]
    },
    {
      _id: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f02"),
      type: "donor",
      status: "active",
      personalInfo: {
        firstName: "Sarah",
        lastName: "Chen",
        email: "sarah.chen@email.com",
        phone: "+1-415-555-0101",
        timezone: "America/Los_Angeles",
        language: "en"
      },
      authentication: {
        passwordHash: await hashPassword("donorSecure456!"),
        twoFactorEnabled: true,
        lastLogin: new Date("2024-03-15T18:22:31Z")
      },
      wallet: {
        stellarPublicKey: "GBHJ4NDXN53PQKYP7VFXX7XM7S2YG4QZID7JKEKSM6RGPNEHH3YRKJAU",
        preferredCurrency: "USD",
        transactions: [{
          type: "donation",
          amount: 5000,
          currency: "XLM",
          timestamp: new Date("2024-03-15T18:25:00Z"),
          txHash: "67f54d3ba1ff4d7",
          status: "completed"
        }]
      },
      preferences: {
        notifications: {
          email: true,
          push: true,
          updateFrequency: "weekly",
          subscribedTopics: ["water", "education", "healthcare"]
        },
        privacy: {
          isAnonymous: false,
          shareActivity: true
        },
        interests: ["education", "water-access", "healthcare"]
      }
    },
    {
      _id: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f03"),
      type: "projectManager",
      status: "active",
      personalInfo: {
        firstName: "John",
        lastName: "Kamau",
        email: "john.kamau@givehub.com",
        phone: "+254-722-555-0102",
        timezone: "Africa/Nairobi",
        language: "en"
      },
      authentication: {
        passwordHash: await hashPassword("managerSecure789!"),
        twoFactorEnabled: true,
        lastLogin: new Date("2024-03-15T10:15:00Z")
      },
      roles: [{
        type: "projectManager",
        organizationId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f9c"),
        permissions: ["manage_projects", "verify_updates", "manage_funds"]
      }]
    },
    {
      _id: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f04"),
      type: "localPartner",
      status: "active",
      personalInfo: {
        firstName: "Isabella",
        lastName: "Morales",
        email: "isabella.morales@givehub.com",
        phone: "+57-1-555-0106",
        timezone: "America/Bogota",
        language: "es"
      },
      authentication: {
        passwordHash: await hashPassword("partnerSecure101!"),
        twoFactorEnabled: true,
        lastLogin: new Date("2024-03-15T16:45:00Z")
      },
      roles: [{
        type: "localPartner",
        organizationId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f9d"),
        permissions: ["manage_local_projects", "submit_updates"]
      }]
    },
    {
      _id: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f05"),
      type: "donor",
      status: "active",
      personalInfo: {
        firstName: "Maria",
        lastName: "Garcia",
        email: "maria.garcia@email.com",
        phone: "+1-650-555-0103",
        timezone: "America/Los_Angeles",
        language: "es"
      },
      authentication: {
        passwordHash: await hashPassword("donorSecure202!"),
        twoFactorEnabled: false,
        lastLogin: new Date("2024-03-15T19:20:00Z")
      },
      wallet: {
        stellarPublicKey: "GBHJ4NDXN53PQKYP7VFXR8XM7S2YG4QZID7JKEKSM6RGPNEHH3YRKJAV",
        preferredCurrency: "USD",
        transactions: [{
          type: "donation",
          amount: 10000,
          currency: "XLM",
          timestamp: new Date("2024-03-15T19:25:00Z"),
          txHash: "89e32f1ca2ee5f8",
          status: "completed"
        }]
      },
      preferences: {
        notifications: {
          email: true,
          push: false,
          updateFrequency: "monthly",
          subscribedTopics: ["education", "healthcare"]
        },
        privacy: {
          isAnonymous: true,
          shareActivity: false
        }
      }
    }
  ];
}

// Database connection and import function
async function importUsers() {
  try {
    await mongoose.connect('mongodb://localhost:27017/givehub', {
      useNewUrlParser: true,
      useUnifiedTopology: true
    });
    console.log('Connected to MongoDB');

    // Clear existing users
    await User.deleteMany({});
    console.log('Cleared existing users');

    // Generate and insert users
    const usersData = await generateSampleUsers();
    
    // Use insertMany instead of create for better performance with multiple documents
    const result = await User.insertMany(usersData);
    console.log(`Successfully imported ${result.length} users`);

    // Run verification queries
    console.log('\nVerifying imported data:');

    const usersByType = await User.aggregate([
      { $group: { _id: "$type", count: { $sum: 1 } } }
    ]);
    console.log('\nUsers by type:', usersByType);

  } catch (error) {
    console.error('Error importing users:', error);
  } finally {
    await mongoose.disconnect();
    console.log('\nDisconnected from MongoDB');
  }
}

// Run the import
importUsers().then(() => {
  console.log('User import completed');
});

module.exports = {
  User,
  importUsers
};

