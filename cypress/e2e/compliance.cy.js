/// <reference types="cypress" />

describe('Tranche2 - Compliance', () => {
  before(() => cy.task('db:seed'));
  beforeEach(() => { cy.viewport(1280,800); cy.adminSession(); });

  it('T98: Implement transaction monitoring', () => {
    cy.visit('/admin/compliance');
    cy.get('[data-cy=tx-monitor-table]').should('exist');
    cy.get('[data-cy=tx-filter]').type('amount>1000{enter}');
    cy.get('[data-cy=tx-row]').should('have.length.greaterThan', 0);
  });

  it('T99: Create compliance reporting', () => {
    cy.visit('/admin/compliance/reports');
    cy.get('[data-cy=generate-report]').click();
    cy.get('[data-cy=report-type]').select('SAR Summary');
    cy.get('[data-cy=run-report]').click();
    cy.get('[data-cy=report-ready]').should('be.visible');
    cy.get('[data-cy=download-csv]').click();
    cy.request('/api/admin/compliance.csv').then(r => expect(r.status).to.eq(200));
  });

  it('T100: Add risk scoring system', () => {
    cy.visit('/admin/compliance/risk');
    cy.get('[data-cy=risk-score]').first().invoke('text').then(t => {
      const v = parseFloat(t); expect(v).to.be.greaterThan(-1);
    });
  });
});

