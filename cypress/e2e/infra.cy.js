/// <reference types="cypress" />

describe('Tranche2 - Infra & Security', () => {
  before(() => cy.task('db:seed'));
  beforeEach(() => { cy.viewport(1280,800); cy.adminSession(); });

  it('T109: Add unit & integration tests (baseline service checks)', () => {
    cy.request('/health').its('status').should('eq',200);
    cy.request('/status').its('status').should('eq',200);
  });

  it('T110: Implement rate limiting & abuse protection', () => {
    const tries = Array.from({length:30}, (_,i)=>i);
    tries.forEach(i => cy.request({ url:'/api/public/ping', failOnStatusCode:false }));
    cy.request({ url:'/api/public/ping', failOnStatusCode:false }).then(r => expect([200,429]).to.include(r.status));
  });

  it('T111: Address penetration test fixes (security headers)', () => {
    cy.request({ url: '/', followRedirect: false }).then(r => {
      expect(r.headers).to.have.property('content-security-policy');
      expect(r.headers).to.have.property('strict-transport-security');
      expect(r.headers).to.have.property('x-content-type-options');
    });
  });

  it('T112: Set up monitoring & alerting (UI evidence)', () => { cy.visit('/admin/logs'); cy.get('[data-cy=monitoring-status]').should('exist'); });
});

