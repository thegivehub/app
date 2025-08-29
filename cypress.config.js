const { defineConfig } = require("cypress");

module.exports = defineConfig({
  // set `projectId` from env or replace with your Cypress Dashboard project id
  projectId: process.env.CYPRESS_PROJECT_ID || '<YOUR_CYPRESS_PROJECT_ID>',
  e2e: {
    baseUrl: process.env.BASE_URL || "https://app.thegivehub.com", // The Give Hub dev URL
    video: true,                      // record headless runs by default
    videosFolder: "cypress/videos",
    screenshotsFolder: "cypress/screenshots",
    defaultCommandTimeout: 15000,
    viewportWidth: 1280,
    viewportHeight: 800,
    setupNodeEvents(on, config) {
      // Expose TEST_ADMIN_TOKEN from process env into Cypress config
      config.env.TEST_ADMIN_TOKEN = process.env.TEST_ADMIN_TOKEN || config.env.TEST_ADMIN_TOKEN;
      require('cypress-mochawesome-reporter/plugin')(on);
      on('task', {
        // example DB seed/reset
        'db:seed': async () => {
          // run a script or call your Node service to reset MySQL
          // e.g. child_process.execSync('node scripts/reset-db.js', {stdio:'inherit'})
          return null;
        },
        // write artifacts (e.g., tx hash) to disk
        'save:artifact': ({ path, data }) => {
          const fs = require('fs');
          fs.mkdirSync(require('path').dirname(path), { recursive: true });
          fs.writeFileSync(path, data);
          return null;
        },
      });
      return config;
    },
    reporter: "cypress-mochawesome-reporter",
    reporterOptions: {
      reportFilename: "givehub-proof",
      overwrite: true,
      embeddedScreenshots: true,
      inlineAssets: true,
    },
    env: {
      ADMIN_EMAIL: "test120@thegivehub.com",
      ADMIN_PASS: "Iaavsw1!", // use real secrets via CI env vars
      APP_ENV: "testing"
    }
  }
});
