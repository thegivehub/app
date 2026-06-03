(function(){
  const endpoint = '/analytics-beacon.php';
  function track(event, payload={}){
    const data = { event, payload, ts: Date.now(), ua: navigator.userAgent };
    const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
    if (navigator.sendBeacon) navigator.sendBeacon(endpoint, blob);
    else fetch(endpoint, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data)});
  }
  window.tghAnalytics = { track };
})();

