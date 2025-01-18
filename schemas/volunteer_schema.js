// MongoDB Schema for volunteers collection
const volunteerSchema = {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["userId", "status", "created"],
            properties: {
                userId: {
                    bsonType: "objectId",
                    description: "Reference to user"
                },
                status: {
                    enum: ["active", "inactive", "pending", "suspended"],
                    description: "Volunteer status"
                },
                skills: {
                    bsonType: "object",
                    properties: {
                        languages: {
                            bsonType: "array",
                            items: {
                                bsonType: "string"
                            }
                        },
                        professionalSkills: {
                            bsonType: "array",
                            items: {
                                bsonType: "string"
                            }
                        },
                        interests: {
                            bsonType: "array",
                            items: {
                                bsonType: "string"
                            }
                        },
                        certifications: {
                            bsonType: "array",
                            items: {
                                bsonType: "object",
                                required: ["name", "issuedDate", "verified"],
                                properties: {
                                    name: { bsonType: "string" },
                                    issuedDate: { bsonType: "date" },
                                    expiryDate: { bsonType: "date" },
                                    issuer: { bsonType: "string" },
                                    documentUrl: { bsonType: "string" },
                                    verified: { bsonType: "bool" }
                                }
                            }
                        }
                    }
                },
                schedule: {
                    bsonType: "object",
                    properties: {
                        availability: {
                            bsonType: "array",
                            items: {
                                bsonType: "object",
                                required: ["day", "startTime", "endTime"],
                                properties: {
                                    day: {
                                        enum: ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"]
                                    },
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
                preferences: {
                    bsonType: "object",
                    properties: {
                        remoteOnly: { bsonType: "bool" },
                        minimumHours: { bsonType: "int" },
                        maximumHours: { bsonType: "int" },
                        projectTypes: {
                            bsonType: "array",
                            items: { bsonType: "string" }
                        }
                    }
                },
                created: { bsonType: "date" },
                updated: { bsonType: "date" },
                lastActive: { bsonType: "date" }
            }
        }
    },
    indices: [
        { key: { userId: 1 }, unique: true },
        { key: { status: 1 } },
        { key: { "skills.languages": 1 } },
        { key: { created: -1 } },
        { key: { lastActive: -1 } }
    ]
};

// Schema for volunteer opportunities
const opportunitySchema = {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["title", "description", "status", "created"],
            properties: {
                title: { bsonType: "string" },
                description: { bsonType: "string" },
                status: {
                    enum: ["draft", "active", "filled", "completed", "cancelled"]
                },
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
                        city: { bsonType: "string" },
                        coordinates: {
                            bsonType: "object",
                            properties: {
                                latitude: { bsonType: "double" },
                                longitude: { bsonType: "double" }
                            }
                        }
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
};

// Schema for volunteer applications
const applicationSchema = {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["userId", "opportunityId", "status", "created"],
            properties: {
                userId: { bsonType: "objectId" },
                opportunityId: { bsonType: "objectId" },
                status: {
                    enum: ["pending", "accepted", "rejected", "withdrawn", "completed"]
                },
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
    },
    indices: [
        { key: { userId: 1 } },
        { key: { opportunityId: 1 } },
        { key: { status: 1 } },
        { key: { created: -1 } }
    ]
};

// Schema for volunteer hours
const hoursSchema = {
    validator: {
        $jsonSchema: {
            bsonType: "object",
            required: ["userId", "opportunityId", "hours", "date", "created"],
            properties: {
                userId: { bsonType: "objectId" },
                opportunityId: { bsonType: "objectId" },
                hours: { bsonType: "decimal" },
                date: { bsonType: "date" },
                description: { bsonType: "string" },
                verified: { bsonType: "bool" },
                verifiedBy: { bsonType: "objectId" },
                verifiedAt: { bsonType: "date" },
                created: { bsonType: "date" }
            }
        }
    },
    indices: [
        { key: { userId: 1 } },
        { key: { opportunityId: 1 } },
        { key: { date: -1 } },
        { key: { verified: 1 } }
    ]
};
