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
  cy.visit('/login.html');
  cy.get('[data-cy=email]').type(Cypress.env('ADMIN_EMAIL'));
  cy.get('[data-cy=password]').type(Cypress.env('ADMIN_PASS'), { log: false });
  cy.get('[data-cy=submit]').click();
  
  // Wait for login to complete and tokens to be stored
  cy.window().then((win) => {
    cy.wait(2000); // Give time for token storage
    const accessToken = win.localStorage.getItem('accessToken');
    const refreshToken = win.localStorage.getItem('refreshToken');
    expect(accessToken).to.not.be.null;
    expect(refreshToken).to.not.be.null;
  });
});

Cypress.Commands.add('adminSession', () => {
  // If a test token is provided via Cypress env, use it directly (fast path for CI)
  const testToken = Cypress.env('TEST_ADMIN_TOKEN');
  if (testToken) {
    Cypress.env('ACCESS_TOKEN', testToken);
    Cypress.env('REFRESH_TOKEN', '');
    cy.visit('/');
    cy.window().then((win) => {
      win.localStorage.setItem('accessToken', testToken);
      if (Cypress.env('REFRESH_TOKEN')) win.localStorage.setItem('refreshToken', Cypress.env('REFRESH_TOKEN'));
    });
    return;
  }

  // Simple authentication without cy.session to avoid promise issues
  cy.request({
    method: 'POST',
    url: '/api/auth/login',
    headers: {
      'Content-Type': 'application/json',
      'X-APP-ENV': 'testing'
    },
    body: {
      username: Cypress.env('ADMIN_EMAIL'),
      password: Cypress.env('ADMIN_PASS')
    }
  }).then((response) => {
    // Check if login was successful
    expect(response.status).to.eq(200);
    expect(response.body).to.have.property('success', true);
    expect(response.body).to.have.property('tokens');
    expect(response.body.tokens).to.have.property('accessToken');
    
    // Store tokens in Cypress env for the duration of test run
    Cypress.env('ACCESS_TOKEN', response.body.tokens.accessToken);
    Cypress.env('REFRESH_TOKEN', response.body.tokens.refreshToken);
  });
  
  // Visit a page to establish browser context  
  cy.visit('/');
  
  // Store tokens in localStorage
  cy.window().then((win) => {
    win.localStorage.setItem('accessToken', Cypress.env('ACCESS_TOKEN'));
    win.localStorage.setItem('refreshToken', Cypress.env('REFRESH_TOKEN'));
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

// Authenticated API request command
Cypress.Commands.add('apiRequest', (method, url, body = null) => {
  return cy.window().then((win) => {
    const accessToken = win.localStorage.getItem('accessToken');
    
    const requestOptions = {
      method: method,
      url: url,
      headers: {
        'Authorization': `Bearer ${accessToken}`,
        'Content-Type': 'application/json'
      }
    };
    
    if (body && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
      requestOptions.body = body;
    }
    
    return cy.request(requestOptions);
  });
});

// Override cy.request to automatically include auth headers for API calls
Cypress.Commands.overwrite('request', (originalFn, options) => {
  // Convert string URL to options object
  if (typeof options === 'string') {
    options = { url: options };
  }
  
  // Only add auth headers for API endpoints (except public ones)
  if (options.url && options.url.includes('/api/') && !options.url.includes('/api/public/')) {
    // Get token from Cypress environment
    const accessToken = Cypress.env('ACCESS_TOKEN');
    
    if (accessToken && !options.headers?.Authorization) {
      options.headers = {
        ...options.headers,
        'Authorization': `Bearer ${accessToken}`
      };
    }
  }
  
  // For all requests, proceed with (potentially modified) options
  return originalFn(options);
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

// Ensure authenticated session is active for all tests
Cypress.Commands.add('ensureAuth', () => {
  cy.window().then((win) => {
    const accessToken = win.localStorage.getItem('accessToken');
    if (!accessToken) {
      cy.adminSession();
    }
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
 *  apiRequest(method: string, url: string, body?: any): Chainable<any>;
 *  stamp(): Chainable<string>;
 *  captureTxFrom(selector?: string): Chainable<any>;
 *  interceptAndSave(method: string, urlPattern: string|RegExp, alias: string): Chainable<any>;
 *  waitAndSave(alias: string, basename: string): Chainable<any>;
 * }} CustomCommands
 */
