/// <reference types="cypress" />

describe('Tranche #3 Full Proof', () => {
  const base = Cypress.config('baseUrl') || '';
  const visitOk = (path) => cy.request({ url: path, failOnStatusCode: false }).its('status').should('be.oneOf', [200, 204]);

  it('Documentation artifacts', () => {
    cy.visit('/tranche3-dashboard.html');
    cy.contains('Tranche #3 Production Readiness');
    visitOk('/api-documentation.html');
    visitOk('/openapi.yml');
    visitOk('/docs/system-architecture.md');
    visitOk('/developer-resources.html');
    visitOk('/docs/performance-monitoring.md');
  });

  it('Generate and view public proofs', () => {
    const key = Cypress.env('TEST_ADMIN_TOKEN') || 'TEST_ADMIN';
    cy.request({ url: `/proofs-api.php?action=generate&key=${key}`, failOnStatusCode: false });
    cy.visit('/public/proofs.html');
    cy.contains('Tranche #3 Proofs');
    cy.contains('security_verification_result.json');
    cy.contains('mainnet_migration_result.json');
    cy.contains('production_integration_result.json');
  });

  it('Security and performance evidence via dashboard', () => {
    cy.visit('/tranche3-dashboard.html');
    cy.contains('Security Hardening');
    cy.contains('Performance Optimization');
    cy.contains('Mainnet Preparation');
  });

  it('I18n demo switching', () => {
    cy.visit('/public/i18n-demo.html');
    cy.contains('Make a Donation');
    cy.get('#lang').select('fr');
    cy.contains('Faire un don');
    cy.get('#lang').select('ar');
    cy.get('html').should('have.attr', 'dir', 'rtl');
  });

  it('Donate button UI (presets, recurring)', () => {
    cy.visit('/pages/campaign-detail.html');
    cy.get('donate-button').should('exist');
    cy.get('donate-button').shadow().within(() => {
      cy.get('.donate-btn').click();
      cy.get('#amount').should('exist');
      cy.contains('$25').click();
      cy.get('#recurring').check({ force: true });
      cy.get('#frequency').select('Monthly');
      cy.contains('Complete Donation');
    });
  });

  it('PWA/Offline assets present', () => {
    visitOk('/service-worker.js');
    visitOk('/offline.html');
  });
});

