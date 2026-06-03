#!/usr/bin/env bash
set -euo pipefail

# Stable sequential runner for Cypress specs to avoid terminal quirks
# Runs only first-level e2e specs (excludes legacy/ and temp proofs)

export APP_ENV="${APP_ENV:-testing}"
export CI="${CI:-true}"
export TEST_ADMIN_TOKEN="${TEST_ADMIN_TOKEN:-TEST_ADMIN}"
export BASE_URL="${BASE_URL:-https://app.thegivehub.com}"

echo "Using BASE_URL=$BASE_URL"

shopt -s nullglob
SPECS=(cypress/e2e/*.cy.js)

if [ ${#SPECS[@]} -eq 0 ]; then
  echo "No specs found under cypress/e2e/*.cy.js"
  exit 0
fi

for spec in "${SPECS[@]}"; do
  echo "\n=== Running spec: $spec ===\n"
  TERM=xterm-256color COLUMNS=120 LINES=40 \
  npx cypress run --headless --browser chrome --reporter spec --spec "$spec"
done

echo "\nAll specs completed successfully."

