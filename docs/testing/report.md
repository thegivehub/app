# Test Reports

This document summarizes how to generate and access automated test reports.

Unit Tests (PHPUnit)
- Command: `vendor/bin/phpunit --testsuite Unit --coverage-text`
- CI: `.github/workflows/phpunit.yml`
- Output: Console coverage summary (attach as artifact if desired)

E2E Tests (Cypress)
- Command: `npm run cypress:run`
- Reporter: `cypress-mochawesome-reporter`
- Config: `cypress.config.js` (reporter + plugin)
- Artifacts:
  - Videos: `cypress/videos/`
  - Screenshots: `cypress/screenshots/`
  - HTML report: `cypress/reports/` (uploaded in CI)

CI Artifacts
- Cypress workflow: `.github/workflows/cypress.yml` uploads videos, screenshots, and reports.

Notes
- For local runs, open Mochawesome HTML report under `cypress/reports/`.
- Keep `APP_ENV=testing` for deterministic test behavior where applicable.

