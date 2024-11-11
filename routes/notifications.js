const express = require('express');
const router = express.Router();
const Notification = require('../schemas/notification');

router.post('/notifications', async (req, res) => {
  try {
    const notification = new Notification(req.body);
    await notification.save();
    res.status(201).json(notification);
  } catch (error) {
    res.status(400).json({ error: error.message });
  }
});


