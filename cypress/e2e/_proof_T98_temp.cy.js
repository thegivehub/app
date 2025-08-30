const ts = () => new Date().toISOString().replace(/[:.]/g,'-');
const save = (p, d) => cy.task('save:artifact', { path: p, data: typeof d === 'string' ? d : JSON.stringify(d, null, 2) });

describe('Temp T98 only', () => { before(() => cy.task('db:seed')); beforeEach(() => { cy.viewport(1280,800); cy.adminSession(); }); it('T98: Implement transaction monitoring', () => {
    cy.visit('/admin/compliance');
    cy.get('[data-cy=tx-monitor-table]').should('exist');
    cy.get('[data-cy=tx-filter]').type('amount>1000{enter}');
    cy.get('[data-cy=tx-row]').should('have.length.greaterThan', 0);
    cy.screenshot(`T98-txn-monitor-${ts()}`);
  }); });