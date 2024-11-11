{
  _id: ObjectId("65ee1a1b2f3a4b5c6d7e8f9a"),
  title: "Clean Water Pipeline - Samburu County",
  description: "Construction of a 5km water pipeline connecting remote villages to the main water supply, serving 2,500 residents and dramatically reducing water collection time for local families.",
  location: {
    country: "Kenya",
    region: "Samburu County",
    coordinates: {
      latitude: 1.2833,
      longitude: 37.5333
    }
  },
  funding: {
    goalAmount: 125000,
    raisedAmount: 75000,
    currency: "XLM",
    donorCount: 184,
    transactions: [
      {
        txHash: "67f54d3ba1ff4d7...",
        amount: 5000,
        timestamp: ISODate("2024-03-10T14:22:31Z"),
        donorId: ObjectId("65ee1a1b2f3a4b5c6d7e8f9b")
      }
    ]
  },
  timeline: {
    created: ISODate("2024-02-15T08:00:00Z"),
    startDate: ISODate("2024-03-01T00:00:00Z"),
    endDate: ISODate("2024-06-01T00:00:00Z"),
    milestones: [
      {
        title: "Environmental Impact Assessment",
        description: "Complete required environmental studies and obtain permits",
        targetDate: ISODate("2024-03-15T00:00:00Z"),
        completedDate: ISODate("2024-03-14T16:30:00Z"),
        status: "completed"
      },
      {
        title: "Pipeline Material Procurement",
        description: "Purchase and deliver all necessary materials",
        targetDate: ISODate("2024-04-01T00:00:00Z"),
        completedDate: null,
        status: "pending"
      }
    ]
  },
  impact: {
    beneficiariesCount: 2500,
    metrics: [
      {
        name: "Water Collection Time",
        baseline: 240,
        current: 240,
        target: 15,
        unit: "minutes"
      },
      {
        name: "Households Served",
        baseline: 0,
        current: 0,
        target: 500,
        unit: "households"
      }
    ],
    sdgGoals: [6, 3]
  },
  verification: {
    status: "verified",
    localPartners: [
      {
        organizationId: ObjectId("65ee1a1b2f3a4b5c6d7e8f9c"),
        role: "implementingPartner",
        verificationDocuments: ["water_authority_approval.pdf", "environmental_impact_assessment.pdf"]
      }
    ],
    updates: [
      {
        date: ISODate("2024-03-14T16:30:00Z"),
        description: "Environmental impact assessment completed and approved",
        mediaUrls: ["site_survey_photo1.jpg", "approval_document.pdf"],
        verifiedBy: ObjectId("65ee1a1b2f3a4b5c6d7e8f9d")
      }
    ]
  }
},

{
  _id: ObjectId("65ee1a1b2f3a4b5c6d7e8f9e"),
  title: "Solar-Powered Medical Clinic - Chocó",
  funding: {
    goalAmount: 200000,
    raisedAmount: 30000,
    currency: "XLM",
    donorCount: 45,
    transactions: [
      {
        txHash: "89e32f1ca2ee5f8...",
        amount: 10000,
        timestamp: ISODate("2024-03-12T09:15:22Z"),
        donorId: ObjectId("65ee1a1b2f3a4b5c6d7e8f9f")
      }
    ]
  },
  timeline: {
    created: ISODate("2024-03-01T00:00:00Z"),
    startDate: ISODate("2024-03-10T00:00:00Z"),
    endDate: ISODate("2024-11-10T00:00:00Z"),
    milestones: [
      {
        title: "Land Acquisition",
        description: "Secure and verify land for clinic construction",
        targetDate: ISODate("2024-04-01T00:00:00Z"),
        completedDate: null,
        status: "pending"
      }
    ]
  },
  impact: {
    beneficiariesCount: 2500,
    metrics: [
      {
        name: "Water Collection Time",
        baseline: 240,
        current: 240,
        target: 15,
        unit: "minutes"
      },
      {
        name: "Households Served",
        baseline: 0,
        current: 0,
        target: 500,
        unit: "households"
      }
    ],
    sdgGoals: [6, 3]
  },
  verification: {
    status: "verified",
    localPartners: [
      {
        organizationId: ObjectId("65ee1a1b2f3a4b5c6d7e8f9c"),
        role: "implementingPartner",
        verificationDocuments: ["water_authority_approval.pdf", "environmental_impact_assessment.pdf"]
      }
    ],
    updates: [
      {
        date: ISODate("2024-03-14T16:30:00Z"),
        description: "Environmental impact assessment completed and approved",
        mediaUrls: ["site_survey_photo1.jpg", "approval_document.pdf"],
        verifiedBy: ObjectId("65ee1a1b2f3a4b5c6d7e8f9d")
      }
    ]
  }
},
{
  _id: ObjectId("65ee1a1b2f3a4b5c6d7e8fa0"),
  title: "Agricultural Training Center - Kilifi",
  funding: {
    goalAmount: 150000,
    raisedAmount: 150000,
    currency: "XLM",
    donorCount: 312,
    transactions: [
      // ... completed transaction history
    ]
  },
  timeline: {
    created: ISODate("2023-10-01T00:00:00Z"),
    startDate: ISODate("2023-10-15T00:00:00Z"),
    endDate: ISODate("2024-02-15T00:00:00Z"),
    milestones: [
      {
        title: "Facility Construction",
        description: "Complete main training facility building",
        targetDate: ISODate("2023-12-15T00:00:00Z"),
        completedDate: ISODate("2023-12-12T16:30:00Z"),
        status: "completed"
      },
      {
        title: "First Training Batch",
        description: "Complete training for first group of 50 farmers",
        targetDate: ISODate("2024-02-01T00:00:00Z"),
        completedDate: ISODate("2024-01-28T14:20:00Z"),
        status: "completed"
      }
    ]
  },
  impact: {
    beneficiariesCount: 1200,
    metrics: [
      {
        name: "Farmers Trained",
        baseline: 0,
        current: 200,
        target: 200,
        unit: "farmers"
      },
      {
        name: "Crop Yield Increase",
        baseline: 0,
        current: 40,
        target: 35,
        unit: "percentage"
      }
    ],
    sdgGoals: [1, 2, 8]
  },
  verification: {
    status: "completed",
    updates: [
      {
        date: ISODate("2024-02-15T10:00:00Z"),
        description: "Final project completion report submitted and verified",
        mediaUrls: ["completion_ceremony.jpg", "impact_report.pdf"],
        verifiedBy: ObjectId("65ee1a1b2f3a4b5c6d7e8fa1")
      }
    ]
  }
},
{
  _id: ObjectId("65ee1a1b2f3a4b5c6d7e8fa2"),
  title: "School Infrastructure Project - Putumayo",
  funding: {
    goalAmount: 175000,
    raisedAmount: 52500,
    currency: "XLM",
    donorCount: 89,
    transactions: [
    ]
  },
  timeline: {
    created: ISODate("2024-01-15T00:00:00Z"),
    startDate: ISODate("2024-02-01T00:00:00Z"),
    endDate: ISODate("2024-05-01T00:00:00Z"),
    milestones: [
      {
        title: "Initial Planning Phase",
        description: "Complete architectural plans and permits",
        targetDate: ISODate("2024-03-01T00:00:00Z"),
        completedDate: ISODate("2024-03-10T00:00:00Z"),
        status: "completed"
      }
    ]
  },
  verification: {
    status: "verified",
    updates: [
      {
        date: ISODate("2024-03-12T15:30:00Z"),
        description: "Campaign needs additional promotion to meet funding goal",
        mediaUrls: ["current_school_conditions.jpg"],
        verifiedBy: ObjectId("65ee1a1b2f3a4b5c6d7e8fa3")
      }
    ]
  }
},
{
  _id: ObjectId("65ee1a1b2f3a4b5c6d7e8fa4"),
  title: "Community Health & Sanitation - Siaya County",
  funding: {
    goalAmount: 100000,
    raisedAmount: 100000,
    currency: "XLM",
    donorCount: 256,
    transactions: [
    ]
  },
  timeline: {
    created: ISODate("2024-02-01T00:00:00Z"),
    startDate: ISODate("2024-02-15T00:00:00Z"),
    endDate: ISODate("2024-07-15T00:00:00Z"),
    milestones: [
      {
        title: "Community Assessment",
        description: "Complete community needs assessment and location planning",
        targetDate: ISODate("2024-03-01T00:00:00Z"),
        completedDate: ISODate("2024-02-28T00:00:00Z"),
        status: "completed"
      },
      {
        title: "Initial Construction Phase",
        description: "Complete first 10 household sanitation facilities",
        targetDate: ISODate("2024-04-01T00:00:00Z"),
        completedDate: null,
        status: "in-progress"
      }
    ]
  },
  impact: {
    beneficiariesCount: 0,  // Will increase as facilities are completed
    metrics: [
      {
        name: "Sanitation Facilities",
        baseline: 0,
        current: 0,
        target: 50,
        unit: "facilities"
      },
      {
        name: "Households Served",
        baseline: 0,
        current: 0,
        target: 400,
        unit: "households"
      }
    ],
    sdgGoals: [3, 6]
  }
}
