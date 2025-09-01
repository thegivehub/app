/// <reference types="cypress" />

describe('Tranche2 - KYC & AML', () => {
  before(() => cy.task('db:seed'));
  beforeEach(() => { cy.viewport(1280,800); cy.adminSession(); });

  it('T97: Enhance identity verification', () => {
    cy.visit('/admin/verification-admin.html');
    cy.get('[data-cy=kyc-list]').should('exist');
    cy.get('[data-cy=kyc-details]').first().click();
    cy.get('[data-cy=kyc-compare]').click();
    cy.contains(/Match Level: (STRONG|WEAK)/i).should('be.visible');
  });

  it('T93: Implement multi-step verification process', () => {
    cy.visit('/admin/verify');
    cy.get('[data-cy=verify-start]').click();
    cy.get('[data-cy=step-1-next]').click();
    cy.get('[data-cy=step-2-next]').click();
    cy.get('[data-cy=step-3-complete]').click();
    cy.contains(/verification complete/i).should('exist');
  });

  it('T94: Create document processing pipeline', () => {
    cy.visit('/admin/verify/docs');
    cy.get('[data-cy=doc-upload]').selectFile('cypress/fixtures/proof.pdf', { force:true });
    cy.get('[data-cy=doc-process]').click();
    cy.contains(/processed|extracted/i).should('exist');
  });

  it('T95: Implement manual review workflow', () => {
    cy.visit('/admin/review');
    cy.get('[data-cy=queue-row]').first().click();
    cy.get('[data-cy=assign-reviewer]').select('admin');
    cy.get('[data-cy=decision-approve]').click();
    cy.contains(/approved/i).should('exist');
  });

  it('T96: Add audit logging for verification', () => {
    cy.visit('/admin/logs');
    cy.get('[data-cy=audit-log]').should('exist');
    cy.get('[data-cy=log-row]').should('have.length.greaterThan', 0);
  });
});

