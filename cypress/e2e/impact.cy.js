/// <reference types="cypress" />

describe('Tranche2 - Impact & Analytics', () => {
  before(() => cy.task('db:seed'));
  beforeEach(() => { cy.viewport(1280,800); cy.adminSession(); });

  it('T101: Build metrics processing engine', () => {
    cy.visit('/admin/impact');
    cy.get('[data-cy=run-metrics-job]').click();
    cy.contains(/metrics updated/i).should('exist');
  });

  it('T102: Implement data integration services', () => {
    cy.visit('/admin/impact/sources');
    cy.get('[data-cy=connector]').should('have.length.greaterThan',0);
    cy.get('[data-cy=test-connection]').first().click();
    cy.contains(/connected|ok/i).should('exist');
  });

  it('T103: Create reporting system', () => {
    cy.visit('/admin/impact/reports');
    cy.get('[data-cy=create-report]').click();
    cy.get('[data-cy=report-name]').type('Tranche2 Impact');
    cy.get('[data-cy=report-save]').click();
    cy.contains('Tranche2 Impact').should('exist');
  });

  it('T104: Add custom calculations', () => {
    cy.visit('/admin/impact/calculations');
    cy.get('[data-cy=new-kpi]').click();
    cy.get('[data-cy=kpi-name]').type('Cost per Patient');
    cy.get('[data-cy=kpi-formula]').type('total_spend / beneficiaries');
    cy.get('[data-cy=kpi-save]').click();
    cy.contains(/cost per patient/i).should('exist');
  });

  it('T89-T92: Visualization & Analysis', () => {
    cy.visit('/impact'); cy.get('[data-cy=chart]').should('have.length.greaterThan',0);
    cy.visit('/impact/reports'); cy.get('[data-cy=filter-date-range]').click(); cy.get('[data-cy=apply-filters]').click();
    cy.visit('/impact/analyze'); cy.get('[data-cy=metric-picker]').select('Donations'); cy.get('[data-cy=run-analysis]').click();
  });
});

