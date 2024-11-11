require('mongoose');

const notificationSchema = new mongoose.Schema({
  type: String,  // campaign update, donation, milestone, alert
  priority: String,  // high, medium, low
  status: String,  // unread, read, archived
  timestamp: Date,
  recipient: {
    userId: ObjectId,
    deliveryMethods: [String]  // email, push, in-app
  },
  content: {
    title: String,
    body: String,
    action: {
      type: String,
      link: String
    }
  },
  referenceData: {
    entityType: String,
    entityId: ObjectId,
    context: Mixed
  },
  delivery: {
    attempts: [{
      method: String,
      timestamp: Date,
      status: String
    }],
    lastDelivered: Date
  }
});

const Notification = mongoose.model('Notification', notificationSchema);
module.exports = Notification;

