/// <reference types="cypress" />

describe('Tranche2 - Contracts', () => {
  before(() => cy.task('db:seed'));
  beforeEach(() => { cy.viewport(1280,800); cy.adminSession(); });

  it('T105-T108: Contract actions available', () => {
    cy.visit('/admin/verification-admin.html');
    cy.get('[data-cy=simulate-settlement]').should('exist').click({ force: true });
    cy.get('[data-cy=release-milestone]').should('exist').first().click({ force: true });
    cy.get('[data-cy=restricted-action]').should('exist').click({ force: true });
    cy.get('[data-cy=deploy-contract]').should('exist').click({ force: true });
  });
});
