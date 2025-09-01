/// <reference types="cypress" />

describe('Tranche2 - Portal / Onboarding', () => {
  before(() => cy.task('db:seed'));
  beforeEach(() => { cy.viewport(1280,800); cy.adminSession(); });

  it('T81: Build onboarding flow', () => {
    cy.visit('/portal/onboarding');
    cy.get('[data-cy=start-onboarding]').click();
    cy.get('[data-cy=step-profile-next]').click();
    cy.get('[data-cy=step-kyc-next]').click();
    cy.get('[data-cy=finish-onboarding]').click();
    cy.contains(/welcome/i).should('exist');
  });

  it('T82: Implement document upload management', () => {
    cy.visit('/portal/docs');
    cy.get('[data-cy=doc-upload]').selectFile('cypress/fixtures/nomad-doc.pdf', { force:true });
    cy.get('[data-cy=doc-submit]').click();
    cy.contains(/uploaded|processed/i).should('exist');
  });

  it('T83: Build progress tracking system', () => { cy.visit('/portal/progress'); cy.get('[data-cy=progress-bar]').should('exist'); });
  it('T84: Add real-time status updates', () => { cy.visit('/portal/status'); cy.get('[data-cy=connect-updates]').click(); cy.contains(/connected|live/i).should('exist'); });
});

