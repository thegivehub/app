/// <reference types="cypress" />

describe('Tranche2 - Impact & Analytics', () => {
  before(() => cy.task('db:seed'));
  beforeEach(() => { cy.viewport(1280,800); cy.adminSession(); });

  it('T101: Build metrics processing engine', () => {
    cy.visit('/admin/impact.html');
    cy.get('[data-cy=run-metrics-job]').click();
    cy.contains(/metrics updated/i).should('exist');
  });

  it('T102: Implement data integration services', () => {
    cy.visit('/admin/impact/');
    cy.get('[data-cy=connector]').should('have.length.greaterThan',0);
    cy.get('[data-cy=test-connection]').first().click();
    cy.contains(/connection ok/i).should('exist');
  });

  it('T103: Create reporting system', () => {
    cy.visit('/impact/reports.html');
    cy.get('[data-cy=filter-date-range]').click();
    cy.get('[data-cy=apply-filters]').click();
    cy.get('#results').should('be.visible');
  });

  it('T104: Add custom calculations', () => {
    cy.visit('/impact/analyze.html');
    cy.get('[data-cy=metric-picker]').select('Donations');
    cy.get('[data-cy=run-analysis]').click();
    cy.contains(/analysis complete/i).should('exist');
  });

  // Older non‑Tranche 2 items moved out of scope
});
