const express = require('express');
const router = express.Router();
const ImpactMetric = require('../schemas/impactMetric');

router.post('/impactMetrics', async (req, res) => {
  try {
    const impactMetric = new ImpactMetric(req.body);
    await impactMetric.save();
    res.status(201).json(impactMetric);
  } catch (error) {
    res.status(400).json({ error: error.message });
  }
});


