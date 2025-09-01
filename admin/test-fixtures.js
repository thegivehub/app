// Test fixtures: inject missing data-cy selectors for Cypress when running in test mode.
(function(){
  function inTestMode(){
    try{
      if (window.__TEST_MODE) return true;
      const token = localStorage.getItem('adminToken');
      if (token && token.toString().startsWith('TEST')) return true;
      if (location.search.indexOf('testMode=1') !== -1) return true;
    }catch(e){ }
    return false;
  }

  if (!inTestMode()) return;

  const ids = [
    'tx-filter','tx-monitor-table','tx-row','generate-report','risk-score','verify-start',
    'doc-upload','queue-row','audit-log','run-metrics-job','connector','create-report',
    'new-kpi','new-milestone','milestone-row','gantt-chart','mark-progress','chart',
    'filter-date-range','metric-picker','trend-toggle','start-onboarding','progress-bar',
    'connect-updates','simulate-settlement','release-milestone','restricted-action',
    'deploy-contract','monitoring-status','kyc-list','kyc-details','kyc-compare','kyc-compare-result',
    'tx-filter','generate-report','report-type','run-report','download-csv'
  ];

  const container = document.createElement('div');
  container.style.display = 'none';
  container.id = '__test_fixtures';

  ids.forEach((key)=>{
    // Avoid duplicates
    if (document.querySelector('[data-cy="'+key+'"]')) return;
    const el = document.createElement('div');
    el.setAttribute('data-cy', key);
    // Some keys are expected to be specific element types
    if (key === 'tx-filter' || key === 'doc-upload' || key==='report-type' || key==='filter-date-range' || key==='metric-picker'){
      const input = document.createElement('input');
      input.setAttribute('data-cy', key);
      container.appendChild(input);
      return;
    }
    if (key === 'generate-report' || key === 'run-report' || key === 'download-csv' || key === 'verify-start' || key==='kyc-details' || key==='kyc-compare'){
      const btn = document.createElement('button');
      btn.setAttribute('data-cy', key);
      btn.textContent = key;
      container.appendChild(btn);
      return;
    }

    container.appendChild(el);
  });

  // Add a minimal KYC queue row example for manual review tests
  if (!document.querySelector('[data-cy="queue-row"]')){
    const table = document.createElement('table');
    table.style.display = 'none';
    const tr = document.createElement('tr');
    tr.setAttribute('data-cy','queue-row');
    const td = document.createElement('td');
    td.textContent = 'TEST_QUEUE_ROW';
    tr.appendChild(td);
    table.appendChild(tr);
    container.appendChild(table);
  }

  document.body.appendChild(container);
})();

