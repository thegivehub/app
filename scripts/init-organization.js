// import-organizations.js
const mongoose = require('mongoose');
require('dotenv').config();

// Define subdocument schemas first
const documentSchema = new mongoose.Schema({
  type: String,
  url: String,
  expiryDate: Date,
  verified: Boolean
});

const contactSchema = new mongoose.Schema({
  userId: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User'
  },
  role: String,
  isPrimary: Boolean,
  department: String
});

const verificationDocumentSchema = new mongoose.Schema({
  type: String,
  url: String,
  status: String,
  notes: String
});

// Organization Schema
const organizationSchema = new mongoose.Schema({
  name: {
    type: String,
    required: true
  },
  type: {
    type: String,
    enum: ['NGO', 'Government', 'Community', 'Corporate'],
    required: true
  },
  status: {
    type: String,
    enum: ['active', 'pending', 'suspended'],
    default: 'active'
  },
  legalInfo: {
    registrationNumber: String,
    taxId: String,
    country: String,
    registrationDate: Date,
    documents: [documentSchema]
  },
  contacts: [contactSchema],
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
    verifiedBy: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'User'
    },
    verificationDate: Date,
    documents: [verificationDocumentSchema]
  },
  projects: [{
    campaignId: {
      type: mongoose.Schema.Types.ObjectId,
      ref: 'Campaign'
    },
    role: String,
    status: String
  }],
  ratings: {
    overallScore: Number,
    totalReviews: Number,
    categories: [{
      name: String,
      score: Number
    }]
  }
});

const Organization = mongoose.model('Organization', organizationSchema);

// Sample Organizations Data
const organizationsData = [
  {
    _id: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f9c"),
    name: "Samburu Water Authority",
    type: "Government",
    status: "active",
    legalInfo: {
      registrationNumber: "KE2010WA1234",
      taxId: "KE123456789",
      country: "Kenya",
      registrationDate: new Date("2010-03-15"),
      documents: [{
        type: "registration",
        url: "docs/swa_registration.pdf",
        expiryDate: new Date("2025-03-15"),
        verified: true
      }]
    },
    contacts: [{
      userId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f02"),
      role: "Regional Director",
      isPrimary: true,
      department: "Operations"
    }],
    location: {
      address: "123 Samburu Road",
      city: "Maralal",
      region: "Samburu County",
      country: "Kenya",
      coordinates: {
        latitude: 1.2833,
        longitude: 37.5333
      }
    },
    finance: {
      stellarAccount: "GBHJ4NDXN53PQKYP7VFXX7XM7S2YG4QZID7JKEKSM6RGPNEHH3YRKJA1",
      bankDetails: {
        bankName: "Kenya Commercial Bank",
        accountNumber: "1234567890",
        swiftCode: "KCBLKENX"
      },
      reportingCurrency: "KES"
    },
    verification: {
      status: "verified",
      verifiedBy: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f04"),
      verificationDate: new Date("2024-01-15"),
      documents: [{
        type: "government_approval",
        url: "docs/swa_approval.pdf",
        status: "verified",
        notes: "Annual compliance verified"
      }]
    },
    ratings: {
      overallScore: 4.8,
      totalReviews: 24,
      categories: [{
        name: "project_execution",
        score: 4.9
      }]
    }
  },
  {
    _id: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f9d"),
    name: "Chocó Health Initiative",
    type: "NGO",
    status: "active",
    legalInfo: {
      registrationNumber: "CO2015NG789",
      taxId: "CO987654321",
      country: "Colombia",
      registrationDate: new Date("2015-06-20"),
      documents: [{
        type: "ngo_registration",
        url: "docs/chi_registration.pdf",
        expiryDate: new Date("2025-06-20"),
        verified: true
      }]
    },
    contacts: [{
      userId: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f06"),
      role: "Executive Director",
      isPrimary: true,
      department: "Management"
    }],
    location: {
      address: "456 Calle Principal",
      city: "Quibdó",
      region: "Chocó",
      country: "Colombia",
      coordinates: {
        latitude: 5.6919,
        longitude: -76.6583
      }
    },
    finance: {
      stellarAccount: "GBHJ4NDXN53PQKYP7VFXX7XM7S2YG4QZID7JKEKSM6RGPNEHH3YRKJA2",
      bankDetails: {
        bankName: "Bancolombia",
        accountNumber: "0987654321",
        swiftCode: "COLOCOBB"
      },
      reportingCurrency: "COP"
    },
    verification: {
      status: "verified",
      verifiedBy: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f04"),
      verificationDate: new Date("2024-02-01"),
      documents: [{
        type: "ngo_verification",
        url: "docs/chi_verification.pdf",
        status: "verified",
        notes: "Documentation complete"
      }]
    }
  },
  {
    _id: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8fa1"),
    name: "Kilifi Agricultural Cooperative",
    type: "Community",
    status: "active",
    legalInfo: {
      registrationNumber: "KE2018AC567",
      taxId: "KE567891234",
      country: "Kenya",
      registrationDate: new Date("2018-09-10"),
      documents: [{
        type: "cooperative_registration",
        url: "docs/kac_registration.pdf",
        expiryDate: new Date("2025-09-10"),
        verified: true
      }]
    },
    location: {
      address: "789 Kilifi Road",
      city: "Kilifi",
      region: "Kilifi County",
      country: "Kenya",
      coordinates: {
        latitude: -3.6305,
        longitude: 39.8499
      }
    },
    finance: {
      stellarAccount: "GBHJ4NDXN53PQKYP7VFXX7XM7S2YG4QZID7JKEKSM6RGPNEHH3YRKJA3",
      reportingCurrency: "KES"
    },
    verification: {
      status: "verified",
      verifiedBy: new mongoose.Types.ObjectId("65ee1a1b2f3a4b5c6d7e8f04"),
      verificationDate: new Date("2024-01-20")
    }
  }
];

// Database connection and import function
async function importOrganizations() {
  try {
    await mongoose.connect('mongodb://localhost:27017/givehub', {
      useNewUrlParser: true,
      useUnifiedTopology: true
    });
    console.log('Connected to MongoDB');

    // Clear existing organizations
    await Organization.deleteMany({});
    console.log('Cleared existing organizations');

    // Insert new organizations
    const result = await Organization.insertMany(organizationsData);
    console.log(`Successfully imported ${result.length} organizations`);

    // Verification queries
    console.log('\nVerifying imported data:');

    // Organizations by type
    const orgsByType = await Organization.aggregate([
      { $group: { _id: "$type", count: { $sum: 1 } } }
    ]);
    console.log('\nOrganizations by type:', orgsByType);

    // Organizations by country
    const orgsByCountry = await Organization.aggregate([
      { $group: { _id: "$location.country", count: { $sum: 1 } } }
    ]);
    console.log('\nOrganizations by country:', orgsByCountry);

    // Verified organizations
    const verifiedOrgs = await Organization.countDocuments({
      "verification.status": "verified"
    });
    console.log('\nVerified organizations:', verifiedOrgs);

  } catch (error) {
    console.error('Error importing organizations:', error);
  } finally {
    await mongoose.disconnect();
    console.log('\nDisconnected from MongoDB');
  }
}

// Run the import
importOrganizations().then(() => {
  console.log('Organization import completed');
});

module.exports = {
  Organization,
  importOrganizations
};
