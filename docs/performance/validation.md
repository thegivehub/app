# Performance Validation

Automate load testing against a target base URL and store results.

Run
```bash
# URL, duration(s), connections
scripts/run_performance_validation.sh http://localhost:8080 10 50
```

Results
- JSON metrics saved to `tools/loadtest/results/` with timestamp
- Fields: `requests`, `latency`, `throughput`

Notes
- Ensure the app is running and accessible at the chosen URL.
- For CI, upload the results directory as an artifact for traceability.

