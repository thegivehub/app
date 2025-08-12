const { defineConfig } = require("cypress");

module.exports = defineConfig({
  e2e: {
    baseUrl: "https://app.thegivehub.com", // The Give Hub dev URL
    video: true,                      // record headless runs by default
    videosFolder: "cypress/videos",
    screenshotsFolder: "cypress/screenshots",
    defaultCommandTimeout: 15000,
    viewportWidth: 1280,
    viewportHeight: 800,
    setupNodeEvents(on, config) {
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
      ADMIN_EMAIL: "admin@givehub.test",
      ADMIN_PASS: "pass123", // use real secrets via CI env vars
    }
  }
});
