#!/usr/bin/env node
/**
 * GiveHub API Router (Node.js)
 * Mirrors the PHP version: /api/{class}/{method}/{id}
 */

const express = require('express');
const cors    = require('cors');
const fs      = require('fs');
const path    = require('path');

const app  = express();
const PORT = process.env.PORT || 3000;

app.use(cors());
app.use(express.json({ limit: '2mb' }));       // body parsing
app.disable('x-powered-by');

/* -------------------------------------------------- helpers */

function sendAPIJson(res, code, data) {
  res.status(code).json(data);
}

function logMessage(msg, context = {}, level = 'info') {
  const ts   = new Date().toISOString().replace('T', ' ').slice(0, 19);
  const body = Object.keys(context).length ? JSON.stringify(context) : '';
  const line = `[${ts}] [${level}] ${msg} ${body}\n`;

  const logDir  = path.join(__dirname, 'logs');
  const logFile = path.join(logDir, `${new Date().toISOString().slice(0, 10)}.log`);
  if (!fs.existsSync(logDir)) fs.mkdirSync(logDir, { recursive: true });

  fs.appendFileSync(logFile, line);
}

/* -------------------------------------------------- dynamic loader */

function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function getInstance(className) {
  const file = path.join(__dirname, 'lib', `${className}.js`);

  // custom resource
  if (fs.existsSync(file)) return new (require(file))();

  // fallback to generic collection
  const Collection = require(path.join(__dirname, 'lib', 'Collection.js'));
  return new (class extends Collection {
    constructor() {
      super();
      this.collectionName = className;
    }
  })();
}

/* -------------------------------------------------- base route */

app.all('/api/:resource/:method?/:id?', async (req, res) => {
  const httpMethod = req.method;
  const resource   = capitalize(req.params.resource || '');
  const custom     = req.params.method;
  const id         = req.params.id ?? req.query.id ?? null;

  /* root scrapes */
  if (!resource) return sendAPIJson(res, 200, { name: 'GiveHub API', version: '1.0', status: 'online' });

  /* sanity check */
  if (!/^[A-Za-z0-9_]+$/.test(resource))
    return sendAPIJson(res, 400, { error: 'Invalid resource name' });

  let instance;
  try {
    instance = getInstance(resource);
  } catch (e) {
    logMessage('Failed to load resource', { resource, error: e.message }, 'error');
    return sendAPIJson(res, 500, { error: 'Resource configuration error' });
  }

  /* --------------------------------- CRUD if no custom method part */
  if (!custom || /^[0-9]+$/.test(custom)) {
    const body = req.body;

    try {
      switch (httpMethod) {
        case 'GET':
          return sendAPIJson(res, 200, await instance.read(id || custom || null));

        case 'POST':
          if (!body) return sendAPIJson(res, 400, { error: 'Missing data' });
          return sendAPIJson(res, 201, await instance.create(body));

        case 'PUT':
          if (!(id || custom) || !body)
            return sendAPIJson(res, 400, { error: 'ID and data required' });
          return sendAPIJson(res, 200, await instance.update(id || custom, body));

        case 'DELETE':
          if (!(id || custom))
            return sendAPIJson(res, 400, { error: 'ID required' });
          return sendAPIJson(res, 200, await instance.delete(id || custom));

        default:
          return sendAPIJson(res, 405, { error: 'Method not allowed' });
      }
    } catch (e) {
      logMessage('CRUD failure', { error: e.message, resource, httpMethod }, 'error');
      return sendAPIJson(res, 500, { error: 'Internal error' });
    }
  }

  /* --------------------------------- custom method call */
  if (typeof instance[custom] !== 'function')
    return sendAPIJson(res, 404, { error: 'Method not found' });

  try {
    const result = await instance[custom](id, req.body);
    return sendAPIJson(res, 200, result);
  } catch (e) {
    logMessage('Custom method failure', { error: e.message, resource, custom }, 'error');
    return sendAPIJson(res, 500, { error: 'Internal error' });
  }
});

/* -------------------------------------------------- pre‑flight */
app.options('/api/*', (req, res) => {
  res.header('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE,OPTIONS');
  res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  res.sendStatus(204);
});

/* -------------------------------------------------- boot */
app.listen(PORT, () => console.log(`GiveHub API listening on :${PORT}`));
