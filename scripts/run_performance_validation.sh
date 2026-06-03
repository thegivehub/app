#!/usr/bin/env bash
set -euo pipefail

BASE_URL=${1:-http://localhost:8080}
DURATION=${2:-10}
CONNS=${3:-50}
OUT_DIR=tools/loadtest/results
mkdir -p "$OUT_DIR"
STAMP=$(date +%Y%m%d-%H%M%S)
OUT_FILE="$OUT_DIR/loadtest-${STAMP}.json"

echo "Running load test: url=$BASE_URL duration=$DURATION connections=$CONNS"
node scripts/load_test.js "$BASE_URL" "$DURATION" "$CONNS" | tee "$OUT_FILE"
echo "Saved results to $OUT_FILE"

