/// <reference types="cypress" />

describe('Tranche2 - Contracts', () => {
  before(() => cy.task('db:seed'));
  beforeEach(() => { cy.viewport(1280,800); cy.adminSession(); });

  it('T105: Implement donation settlement contract', () => {
    cy.visit('/admin/contracts/settlement');
    cy.get('[data-cy=simulate-settlement]').click();
    cy.contains(/settled/i).should('exist');
  });

  it('T106: Add milestone release contract', () => { cy.visit('/admin/contracts/milestones'); cy.get('[data-cy=release-milestone]').first().click(); cy.contains(/released/i).should('exist'); });

  it('T107: Implement access control logic', () => { cy.visit('/admin/contracts/access'); cy.get('[data-cy=restricted-action]').click(); cy.contains(/permission denied|forbidden/i).should('exist'); });

  it('T108: Deploy & update contracts', () => { cy.visit('/admin/contracts'); cy.get('[data-cy=deploy-contract]').click(); cy.contains(/deployed|updated/i).should('exist'); });
});

