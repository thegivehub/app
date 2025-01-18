// MongoDB setup script for volunteer-related collections
db = db.getSiblingDB('givehub');

// Drop existing collections
db.volunteer_opportunities.drop();
db.volunteer_applications.drop();
db.volunteer_hours.drop();

// Create Opportunities Collection
db.createCollection("volunteer_opportunities", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["title", "description", "status", "created"],
            properties: {
                title: { bsonType: "string" },
                description: { bsonType: "string" },
                status: { enum: ["draft", "active", "filled", "completed", "cancelled"] },
                requiredSkills: {
                    bsonType: "array",
                    items: { bsonType: "string" }
                },
                location: {
                    bsonType: "object",
                    properties: {
                        type: { bsonType: "string" },
                        remote: { bsonType: "bool" },
                        country: { bsonType: "string" },
                        city: { bsonType: "string" }
                    }
                },
                timeCommitment: {
                    bsonType: "object",
                    properties: {
                        hoursPerWeek: { bsonType: "int" },
                        duration: { bsonType: "string" },
                        startDate: { bsonType: "date" },
                        endDate: { bsonType: "date" }
                    }
                },
                created: { bsonType: "date" },
                updated: { bsonType: "date" }
            }
        }
    }
});

// Sample Opportunities
const opportunities = [
    {
        _id: ObjectId(),
        title: "Water Well Documentation Specialist",
        description: "Document the progress and impact of our water well construction project in rural Colombia. Skills in photography, video editing, and Spanish required.",
        status: "active",
        requiredSkills: ["Photography", "Video Editing", "Spanish", "Documentation"],
        location: {
            type: "hybrid",
            remote: true,
            country: "Colombia",
            city: "Medellín"
        },
        timeCommitment: {
            hoursPerWeek: 15,
            duration: "3 months",
            startDate: new Date("2024-04-01"),
            endDate: new Date("2024-06-30")
        },
        created: new Date("2024-03-01"),
        updated: new Date("2024-03-01")
    },
    {
        _id: ObjectId(),
        title: "Solar Installation Trainer",
        description: "Train local community members on solar panel installation and maintenance for our renewable energy project in Kenya.",
        status: "active",
        requiredSkills: ["Solar Installation", "Training", "English", "Technical Writing"],
        location: {
            type: "on-site",
            remote: false,
            country: "Kenya",
            city: "Nakuru"
        },
        timeCommitment: {
            hoursPerWeek: 30,
            duration: "2 months",
            startDate: new Date("2024-05-01"),
            endDate: new Date("2024-06-30")
        },
        created: new Date("2024-03-05"),
        updated: new Date("2024-03-05")
    },
    {
        _id: ObjectId(),
        title: "Education Program Coordinator",
        description: "Coordinate online English teaching program for rural schools in Vietnam. Experience in education and curriculum development required.",
        status: "active",
        requiredSkills: ["Teaching", "Curriculum Development", "Project Management", "English"],
        location: {
            type: "remote",
            remote: true,
            country: "Vietnam",
            city: "Ho Chi Minh City"
        },
        timeCommitment: {
            hoursPerWeek: 20,
            duration: "6 months",
            startDate: new Date("2024-04-15"),
            endDate: new Date("2024-10-15")
        },
        created: new Date("2024-03-10"),
        updated: new Date("2024-03-10")
    },
    {
        _id: ObjectId(),
        title: "Agricultural Project Assessor",
        description: "Assess and document the impact of sustainable farming practices in rural Brazil. Background in agriculture and data analysis needed.",
        status: "active",
        requiredSkills: ["Agricultural Science", "Data Analysis", "Portuguese", "Report Writing"],
        location: {
            type: "on-site",
            remote: false,
            country: "Brazil",
            city: "Manaus"
        },
        timeCommitment: {
            hoursPerWeek: 25,
            duration: "4 months",
            startDate: new Date("2024-06-01"),
            endDate: new Date("2024-09-30")
        },
        created: new Date("2024-03-15"),
        updated: new Date("2024-03-15")
    },
    {
        _id: ObjectId(),
        title: "Community Health Educator",
        description: "Develop and deliver health education programs in rural India. Focus on preventive care and maternal health.",
        status: "active",
        requiredSkills: ["Healthcare", "Training", "Hindi", "Community Outreach"],
        location: {
            type: "hybrid",
            remote: true,
            country: "India",
            city: "Pune"
        },
        timeCommitment: {
            hoursPerWeek: 20,
            duration: "6 months",
            startDate: new Date("2024-05-15"),
            endDate: new Date("2024-11-15")
        },
        created: new Date("2024-03-20"),
        updated: new Date("2024-03-20")
    }
];

// Create Applications Collection
db.createCollection("volunteer_applications", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["volunteerId", "opportunityId", "status", "created"],
            properties: {
                volunteerId: { bsonType: "objectId" },
                opportunityId: { bsonType: "objectId" },
                status: { enum: ["pending", "accepted", "rejected", "withdrawn", "completed"] },
                notes: { bsonType: "string" },
                availability: {
                    bsonType: "array",
                    items: {
                        bsonType: "object",
                        properties: {
                            day: { bsonType: "string" },
                            startTime: { bsonType: "string" },
                            endTime: { bsonType: "string" }
                        }
                    }
                },
                created: { bsonType: "date" },
                updated: { bsonType: "date" }
            }
        }
    }
});

// Sample Applications (using the first few volunteers and opportunities)
const applications = [
    {
        volunteerId: db.volunteers.findOne({ "skills.languages": "Spanish" })._id,
        opportunityId: opportunities[0]._id, // Water Well Documentation
        status: "accepted",
        notes: "Strong photography background and Spanish fluency. Perfect fit for the project.",
        availability: [
            { day: "monday", startTime: "09:00", endTime: "17:00" },
            { day: "wednesday", startTime: "09:00", endTime: "17:00" }
        ],
        created: new Date("2024-03-02"),
        updated: new Date("2024-03-03")
    },
    {
        volunteerId: db.volunteers.findOne({ "skills.professionalSkills": "Solar Installation" })._id,
        opportunityId: opportunities[1]._id, // Solar Installation
        status: "pending",
        notes: "Extensive experience in solar installation and training.",
        availability: [
            { day: "monday", startTime: "09:00", endTime: "18:00" },
            { day: "friday", startTime: "09:00", endTime: "18:00" }
        ],
        created: new Date("2024-03-06"),
        updated: new Date("2024-03-06")
    },
    {
        volunteerId: db.volunteers.findOne({ "skills.professionalSkills": "Teaching" })._id,
        opportunityId: opportunities[2]._id, // Education Program
        status: "accepted",
        notes: "Strong background in education and curriculum development.",
        availability: [
            { day: "tuesday", startTime: "13:00", endTime: "18:00" },
            { day: "thursday", startTime: "13:00", endTime: "18:00" }
        ],
        created: new Date("2024-03-11"),
        updated: new Date("2024-03-12")
    }
];

// Create Hours Collection
db.createCollection("volunteer_hours", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["volunteerId", "opportunityId", "hours", "date", "created"],
            properties: {
                volunteerId: { bsonType: "objectId" },
                opportunityId: { bsonType: "objectId" },
                hours: { bsonType: "decimal" },
                date: { bsonType: "date" },
                description: { bsonType: "string" },
                verified: { bsonType: "bool" },
                verifiedBy: { bsonType: "objectId" },
                verifiedAt: { bsonType: "date" }
            }
        }
    }
});

// Sample Hours (for accepted applications)
const hours = [
    {
        volunteerId: applications[0].volunteerId,
        opportunityId: applications[0].opportunityId,
        hours: NumberDecimal("8.5"),
        date: new Date("2024-03-15"),
        description: "Documented initial well site survey and community interviews",
        verified: true,
        verifiedBy: ObjectId(),
        verifiedAt: new Date("2024-03-16"),
        created: new Date("2024-03-15")
    },
    {
        volunteerId: applications[0].volunteerId,
        opportunityId: applications[0].opportunityId,
        hours: NumberDecimal("6.0"),
        date: new Date("2024-03-17"),
        description: "Processed photos and created progress report",
        verified: true,
        verifiedBy: ObjectId(),
        verifiedAt: new Date("2024-03-18"),
        created: new Date("2024-03-17")
    },
    {
        volunteerId: applications[2].volunteerId,
        opportunityId: applications[2].opportunityId,
        hours: NumberDecimal("4.5"),
        date: new Date("2024-03-16"),
        description: "Developed curriculum outline and learning objectives",
        verified: true,
        verifiedBy: ObjectId(),
        verifiedAt: new Date("2024-03-17"),
        created: new Date("2024-03-16")
    }
];

// Insert sample data
db.volunteer_opportunities.insertMany(opportunities);
db.volunteer_applications.insertMany(applications);
db.volunteer_hours.insertMany(hours);

// Create indexes
db.volunteer_opportunities.createIndex({ "status": 1 });
db.volunteer_opportunities.createIndex({ "requiredSkills": 1 });
db.volunteer_opportunities.createIndex({ "location.country": 1 });

db.volunteer_applications.createIndex({ "volunteerId": 1 });
db.volunteer_applications.createIndex({ "opportunityId": 1 });
db.volunteer_applications.createIndex({ "status": 1 });

db.volunteer_hours.createIndex({ "volunteerId": 1 });
db.volunteer_hours.createIndex({ "opportunityId": 1 });
db.volunteer_hours.createIndex({ "date": -1 });

print("Related collections setup complete!");
print("Created:");
print("- " + db.volunteer_opportunities.count() + " opportunities");
print("- " + db.volunteer_applications.count() + " applications");
print("- " + db.volunteer_hours.count() + " hour records");

// Print sample records
print("\nSample opportunity:");
print(JSON.stringify(db.volunteer_opportunities.findOne(), null, 2));
print("\nSample application:");
print(JSON.stringify(db.volunteer_applications.findOne(), null, 2));
print("\nSample hours:");
print(JSON.stringify(db.volunteer_hours.findOne(), null, 2));
