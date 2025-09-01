describe('Temp T97 only', () => {
  before(() => cy.task('db:seed'));
  beforeEach(() => { cy.viewport(1280,800); cy.adminSession(); });
  it('T97: Enhance identity verification', () => {
    cy.visit('/admin/verification-admin.html');
    cy.get('[data-cy=kyc-list]').should('exist');
    cy.get('[data-cy=kyc-details]').first().click();
    cy.get('[data-cy=kyc-compare]').click();
    cy.contains(/Match Level: (STRONG|WEAK)/i).should('be.visible');
    cy.screenshot(`T97-kyc-approved-${ts()}`);
  });
});
