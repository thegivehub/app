// MongoDB setup script for volunteer system
db = db.getSiblingDB('givehub');

// Drop existing collections to start fresh
db.volunteers.drop();
db.volunteer_opportunities.drop();
db.volunteer_applications.drop();
db.volunteer_hours.drop();

// Create collections with schema validation
db.createCollection("volunteers", {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["userId", "status", "created"],
            properties: {
                userId: { bsonType: "objectId" },
                status: { enum: ["active", "inactive", "pending", "suspended"] },
                skills: {
                    bsonType: "object",
                    properties: {
                        languages: { bsonType: "array", items: { bsonType: "string" } },
                        professionalSkills: { bsonType: "array", items: { bsonType: "string" } },
                        interests: { bsonType: "array", items: { bsonType: "string" } }
                    }
                },
                schedule: {
                    bsonType: "object",
                    properties: {
                        availability: {
                            bsonType: "array",
                            items: {
                                bsonType: "object",
                                properties: {
                                    day: { enum: ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"] },
                                    startTime: { bsonType: "string" },
                                    endTime: { bsonType: "string" }
                                }
                            }
                        },
                        timezone: { bsonType: "string" }
                    }
                },
                stats: {
                    bsonType: "object",
                    properties: {
                        totalHours: { bsonType: "decimal" },
                        projectsCompleted: { bsonType: "int" },
                        impactScore: { bsonType: "int" }
                    }
                },
                created: { bsonType: "date" },
                updated: { bsonType: "date" }
            }
        }
    }
});

// Create indexes
db.volunteers.createIndex({ "userId": 1 }, { unique: true });
db.volunteers.createIndex({ "status": 1 });
db.volunteers.createIndex({ "skills.languages": 1 });
db.volunteers.createIndex({ "created": -1 });

// Sample volunteer data
const volunteers = [
    {
        userId: ObjectId(),
        status: "active",
        skills: {
            languages: ["English", "Spanish"],
            professionalSkills: ["Photography", "Project Management", "Social Media"],
            interests: ["water_access", "education"]
        },
        schedule: {
            availability: [
                { day: "monday", startTime: "09:00", endTime: "17:00" },
                { day: "wednesday", startTime: "09:00", endTime: "17:00" }
            ],
            timezone: "America/Los_Angeles"
        },
        stats: {
            totalHours: NumberDecimal("45.5"),
            projectsCompleted: 3,
            impactScore: 89
        },
        created: new Date("2024-01-15"),
        updated: new Date()
    },
    {
        userId: ObjectId(),
        status: "active",
        skills: {
            languages: ["English", "French", "Arabic"],
            professionalSkills: ["Teaching", "Grant Writing", "Community Outreach"],
            interests: ["education", "community"]
        },
        schedule: {
            availability: [
                { day: "tuesday", startTime: "13:00", endTime: "18:00" },
                { day: "thursday", startTime: "13:00", endTime: "18:00" },
                { day: "saturday", startTime: "10:00", endTime: "15:00" }
            ],
            timezone: "Europe/Paris"
        },
        stats: {
            totalHours: NumberDecimal("78.5"),
            projectsCompleted: 5,
            impactScore: 92
        },
        created: new Date("2023-11-20"),
        updated: new Date()
    },
    {
        userId: ObjectId(),
        status: "active",
        skills: {
            languages: ["English", "Swahili"],
            professionalSkills: ["Civil Engineering", "Water Systems", "Construction Management"],
            interests: ["water_access", "infrastructure"]
        },
        schedule: {
            availability: [
                { day: "monday", startTime: "08:00", endTime: "16:00" },
                { day: "friday", startTime: "08:00", endTime: "16:00" }
            ],
            timezone: "Africa/Nairobi"
        },
        stats: {
            totalHours: NumberDecimal("120.0"),
            projectsCompleted: 4,
            impactScore: 95
        },
        created: new Date("2023-09-10"),
        updated: new Date()
    },
    {
        userId: ObjectId(),
        status: "active",
        skills: {
            languages: ["English", "Portuguese"],
            professionalSkills: ["Agricultural Science", "Sustainable Farming", "Training"],
            interests: ["agriculture", "environmental"]
        },
        schedule: {
            availability: [
                { day: "wednesday", startTime: "09:00", endTime: "15:00" },
                { day: "thursday", startTime: "09:00", endTime: "15:00" }
            ],
            timezone: "America/Sao_Paulo"
        },
        stats: {
            totalHours: NumberDecimal("67.5"),
            projectsCompleted: 2,
            impactScore: 88
        },
        created: new Date("2024-02-01"),
        updated: new Date()
    },
    {
        userId: ObjectId(),
        status: "active",
        skills: {
            languages: ["English", "Hindi", "Bengali"],
            professionalSkills: ["Medical Training", "Public Health", "Data Analysis"],
            interests: ["healthcare", "education"]
        },
        schedule: {
            availability: [
                { day: "tuesday", startTime: "10:00", endTime: "18:00" },
                { day: "saturday", startTime: "09:00", endTime: "17:00" }
            ],
            timezone: "Asia/Kolkata"
        },
        stats: {
            totalHours: NumberDecimal("89.0"),
            projectsCompleted: 3,
            impactScore: 91
        },
        created: new Date("2023-12-15"),
        updated: new Date()
    },
    {
        userId: ObjectId(),
        status: "active",
        skills: {
            languages: ["English", "Mandarin"],
            professionalSkills: ["Solar Installation", "Electrical Engineering", "Project Planning"],
            interests: ["renewable_energy", "technology"]
        },
        schedule: {
            availability: [
                { day: "monday", startTime: "09:00", endTime: "18:00" },
                { day: "friday", startTime: "09:00", endTime: "18:00" }
            ],
            timezone: "Asia/Shanghai"
        },
        stats: {
            totalHours: NumberDecimal("56.0"),
            projectsCompleted: 2,
            impactScore: 87
        },
        created: new Date("2024-01-20"),
        updated: new Date()
    },
    {
        userId: ObjectId(),
        status: "active",
        skills: {
            languages: ["English", "Spanish", "Italian"],
            professionalSkills: ["Videography", "Content Creation", "Marketing"],
            interests: ["community", "education"]
        },
        schedule: {
            availability: [
                { day: "wednesday", startTime: "12:00", endTime: "20:00" },
                { day: "sunday", startTime: "10:00", endTime: "18:00" }
            ],
            timezone: "Europe/Rome"
        },
        stats: {
            totalHours: NumberDecimal("34.5"),
            projectsCompleted: 1,
            impactScore: 82
        },
        created: new Date("2024-02-15"),
        updated: new Date()
    },
    {
        userId: ObjectId(),
        status: "active",
        skills: {
            languages: ["English", "French", "Wolof"],
            professionalSkills: ["Agroforestry", "Community Organization", "Teacher Training"],
            interests: ["agriculture", "education"]
        },
        schedule: {
            availability: [
                { day: "tuesday", startTime: "08:00", endTime: "16:00" },
                { day: "thursday", startTime: "08:00", endTime: "16:00" }
            ],
            timezone: "Africa/Dakar"
        },
        stats: {
            totalHours: NumberDecimal("98.5"),
            projectsCompleted: 4,
            impactScore: 93
        },
        created: new Date("2023-10-05"),
        updated: new Date()
    },
    {
        userId: ObjectId(),
        status: "active",
        skills: {
            languages: ["English", "Tagalog"],
            professionalSkills: ["Web Development", "Digital Literacy Training", "Database Management"],
            interests: ["technology", "education"]
        },
        schedule: {
            availability: [
                { day: "monday", startTime: "18:00", endTime: "22:00" },
                { day: "wednesday", startTime: "18:00", endTime: "22:00" },
                { day: "saturday", startTime: "09:00", endTime: "17:00" }
            ],
            timezone: "Asia/Manila"
        },
        stats: {
            totalHours: NumberDecimal("45.0"),
            projectsCompleted: 2,
            impactScore: 85
        },
        created: new Date("2024-01-10"),
        updated: new Date()
    },
    {
        userId: ObjectId(),
        status: "active",
        skills: {
            languages: ["English", "Russian", "Ukrainian"],
            professionalSkills: ["Civil Engineering", "Water Quality Testing", "GIS Mapping"],
            interests: ["water_access", "environmental"]
        },
        schedule: {
            availability: [
                { day: "monday", startTime: "09:00", endTime: "17:00" },
                { day: "thursday", startTime: "09:00", endTime: "17:00" }
            ],
            timezone: "Europe/Kiev"
        },
        stats: {
            totalHours: NumberDecimal("67.0"),
            projectsCompleted: 3,
            impactScore: 90
        },
        created: new Date("2023-12-01"),
        updated: new Date()
    }
];

// Insert sample volunteers
db.volunteers.insertMany(volunteers);

print("Volunteer database setup complete!");
print("Created " + db.volunteers.count() + " volunteer records");

// Optional: Print a sample record to verify
print("\nSample volunteer record:");
print(JSON.stringify(db.volunteers.findOne(), null, 2));
