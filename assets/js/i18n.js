/* Simple I18n loader for static pages
 * Usage:
 *  <script src="/assets/js/i18n.js"></script>
 *  <script>
 *    I18n.init({ defaultLang: 'en', supported: ['en','fr','es','ar'] })
 *  </script>
 * Mark elements with data-i18n="key" and optional data-i18n-attr="placeholder|title".
 */
const I18n = (() => {
  const state = { lang: 'en', dict: {}, supported: ['en'], defaultLang: 'en' };

  function isRTL(lang){ return ['ar','he','fa','ur'].includes(lang); }

  async function loadDictionary(lang){
    const res = await fetch(`/assets/i18n/${lang}.json`);
    if (!res.ok) throw new Error(`Failed to load i18n ${lang}`);
    state.dict = await res.json();
  }

  function apply(){
    document.documentElement.lang = state.lang;
    document.documentElement.dir = isRTL(state.lang) ? 'rtl' : 'ltr';
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      const attr = el.getAttribute('data-i18n-attr');
      const val = key.split('.').reduce((o,k)=>o&&o[k], state.dict) || key;
      if (attr) el.setAttribute(attr, val); else el.textContent = val;
    });
  }

  async function setLanguage(lang){
    if (!state.supported.includes(lang)) lang = state.defaultLang;
    state.lang = lang;
    localStorage.setItem('lang', lang);
    await loadDictionary(lang);
    apply();
  }

  async function init({defaultLang='en', supported=['en'] }={}){
    state.defaultLang = defaultLang; state.supported = supported;
    const saved = localStorage.getItem('lang');
    await setLanguage(saved || defaultLang);
    window.addEventListener('i18n:set', async (e)=>{ await setLanguage(e.detail.lang); });
  }

  return { init, setLanguage };
})();

window.I18n = I18n;

