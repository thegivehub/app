/// <reference types="cypress" />

describe('Tranche2 - Milestones', () => {
  before(() => cy.task('db:seed'));
  beforeEach(() => { cy.viewport(1280,800); cy.adminSession(); });

  it('T85: Design milestone creation interface', () => {
    cy.visit('/admin/milestones');
    cy.get('[data-cy=new-milestone]').click();
    cy.get('[data-cy=milestone-title]').type('Clinic Equipment Purchase');
    cy.get('[data-cy=milestone-budget]').type('1000');
    cy.get('[data-cy=milestone-save]').click();
    cy.contains(/clinic equipment purchase/i).should('exist');
  });

  it('T86: Implement budget allocation tools', () => {
    cy.visit('/admin/milestones');
    cy.get('[data-cy=milestone-row]').first().click();
    cy.get('[data-cy=allocate-budget]').type('250');
    cy.get('[data-cy=save-allocation]').click();
    cy.contains(/allocation saved/i).should('exist');
  });

  it('T87: Create timeline visualization', () => { cy.visit('/admin/milestones/timeline'); cy.get('[data-cy=gantt-chart]').should('exist'); });
  it('T88: Add progress tracking features', () => { cy.visit('/admin/milestones'); cy.get('[data-cy=mark-progress]').first().click(); cy.contains(/status:/i).should('exist'); });
});

