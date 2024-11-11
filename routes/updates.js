const express = require('express');
const router = express.Router();
const Update = require('../schemas/update');

router.post('/updates', async (req, res) => {
  try {
    const update = new Update(req.body);
    await update.save();
    res.status(201).json(update);
  } catch (error) {
    res.status(400).json({ error: error.message });
  }
});


