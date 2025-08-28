Setup and Recording
-------------------

To record test runs to the Cypress Dashboard you need:

- a Cypress Dashboard `projectId` (set as `CYPRESS_PROJECT_ID`), and
- a Dashboard record key (set as `CYPRESS_RECORD_KEY` in CI secrets).

Local usage:
- Run `npm run cypress:record` after setting `CYPRESS_RECORD_KEY` and `CYPRESS_PROJECT_ID` in your shell.

CI example (GitHub Actions):

1. Add `CYPRESS_RECORD_KEY` and `CYPRESS_PROJECT_ID` to repository Secrets.
2. In your workflow step run:
   - `npm ci`
   - `npm run cypress:record`

Note: the project `package.json` script passes `--key $CYPRESS_RECORD_KEY`. The `projectId` is read from
`CYPRESS_PROJECT_ID` (or replace the placeholder in `cypress.config.js`). Ensure the CI image includes a
browser (Chrome/Electron) or use the official Cypress Docker images.
