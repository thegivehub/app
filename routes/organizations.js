const express = require('express');
const router = express.Router();
const Organization = require('../schemas/organization');

router.post('/organizations', async (req, res) => {
  try {
    const organization = new Organization(req.body);
    await organization.save();
    res.status(201).json(organization);
  } catch (error) {
    res.status(400).json({ error: error.message });
  }
});


