#!/usr/bin/env node
const autocannon = require('autocannon');

const url = process.argv[2] || 'http://localhost:8080';
const duration = parseInt(process.argv[3] || '10', 10);
const connections = parseInt(process.argv[4] || '50', 10);

async function run() {
  const result = await autocannon({
    url,
    duration,
    connections
  });
  console.log(JSON.stringify({
    url,
    duration,
    connections,
    requests: result.requests.average,
    latency: result.latency.average,
    throughput: result.throughput.average
  }, null, 2));
}

run().catch(err => {
  console.error('Load test failed', err);
  process.exit(1);
});
