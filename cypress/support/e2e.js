// cypress/support/e2e.js
// Global support for The Give Hub Cypress tests

/// <reference types="cypress" />

import 'cypress-mochawesome-reporter/register';

// ---------- Utilities ----------
const ts = () => new Date().toISOString().replace(/[:.]/g, '-');

// Don’t fail tests on app-side console errors unless you want strict mode
Cypress.on('uncaught:exception', () => false);

// ---------- Custom Commands ----------
Cypress.Commands.add('saveArtifact', (path, data) => {
  const payload = typeof data === 'string' ? data : JSON.stringify(data, null, 2);
  return cy.task('save:artifact', { path, data: payload });
});

Cypress.Commands.add('seedDB', () => {
  return cy.task('db:seed');
});

Cypress.Commands.add('adminLogin', () => {
  // Single place to keep selectors/flow
  cy.visit('/login');
  cy.get('[data-cy=email]').type(Cypress.env('ADMIN_EMAIL'));
  cy.get('[data-cy=password]').type(Cypress.env('ADMIN_PASS'), { log: false });
  cy.get('[data-cy=submit]').click();
  cy.url().should('include', '/dashboard');
});

Cypress.Commands.add('adminSession', () => {
  // Cache session across specs for speed/stability
  cy.session('admin', () => {
    cy.visit('/login');
    cy.get('[data-cy=email]').type(Cypress.env('ADMIN_EMAIL'));
    cy.get('[data-cy=password]').type(Cypress.env('ADMIN_PASS'), { log: false });
    cy.get('[data-cy=submit]').click();
    cy.url().should('include', '/dashboard');
  });
});

Cypress.Commands.add('stamp', () => {
  return cy.wrap(ts());
});

Cypress.Commands.add('captureTxFrom', (selector = '[data-cy=tx-hash]') => {
  // Saves href + visible hash text if present
  cy.get(selector)
    .should('exist')
    .then(($el) => {
      const href = $el.attr('href') || '';
      const text = $el.text().trim();
      if (href) cy.saveArtifact(`cypress/artifacts/tx-url-${ts()}.txt`, href);
      if (text) cy.saveArtifact(`cypress/artifacts/tx-hash-${ts()}.txt`, text);
    });
});

// Example: network capture helper for proof artifacts
Cypress.Commands.add('interceptAndSave', (method, urlPattern, alias) => {
  cy.intercept(method, urlPattern).as(alias);
  return cy.wrap(null);
});

Cypress.Commands.add('waitAndSave', (alias, basename) => {
  cy.wait(alias).then(({ request, response }) => {
    cy.saveArtifact(`cypress/artifacts/${basename}-request-${ts()}.json`, request?.body ?? {});
    cy.saveArtifact(`cypress/artifacts/${basename}-response-${ts()}.json`, response?.body ?? {});
  });
});

// ---------- Global Hooks ----------
beforeEach(() => {
  // Consistent viewport + session ready
  cy.viewport(1280, 800);
  // If you want every test pre-authenticated, uncomment:
  // cy.adminSession();
});

// Auto-screenshot + HTML DOM snapshot on failure (great for auditors)
afterEach(function () {
  if (this.currentTest && this.currentTest.state === 'failed') {
    const name = (this.currentTest.title || 'failed').replace(/[^\w.-]+/g, '_');
    cy.screenshot(`FAILED-${name}-${ts()}`, { capture: 'runner' });
    cy.document().then((doc) => {
      cy.saveArtifact(`cypress/artifacts/FAILED-${name}-${ts()}.html`, doc.documentElement.outerHTML);
    });
  }
});

// ---------- Type Augmentation (JS-friendly) ----------
/**
 * @typedef {import('cypress').Chainable} Chainable
 * @typedef {{
 *  saveArtifact(path: string, data: any): Chainable<any>;
 *  seedDB(): Chainable<any>;
 *  adminLogin(): Chainable<any>;
 *  adminSession(): Chainable<any>;
 *  stamp(): Chainable<string>;
 *  captureTxFrom(selector?: string): Chainable<any>;
 *  interceptAndSave(method: string, urlPattern: string|RegExp, alias: string): Chainable<any>;
 *  waitAndSave(alias: string, basename: string): Chainable<any>;
 * }} CustomCommands
 */
