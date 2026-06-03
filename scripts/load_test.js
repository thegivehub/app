#!/usr/bin/env node
const autocannon = require('autocannon');

const url = process.argv[2] || 'http://localhost:8080';
const duration = parseInt(process.argv[3] || '10', 10);
const connections = parseInt(process.argv[4] || '50', 10);

function run() {
  const inst = autocannon({ url, duration, connections });
  inst.on('done', (result) => {
    const out = {
      url,
      duration,
      connections,
      requests: result.requests.average,
      latency: result.latency.average,
      throughput: result.throughput.average
    };
    console.log(JSON.stringify(out, null, 2));
  });
  inst.on('error', (err) => {
    console.error('Load test failed', err);
    process.exit(1);
  });
}

run();
