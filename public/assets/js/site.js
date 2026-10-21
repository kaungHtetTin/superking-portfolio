(() => {
  const menu = document.querySelector('.menu-toggle'), nav = document.querySelector('#site-nav');
  const closeMenu = () => { if(!menu || !nav) return; nav.classList.remove('open'); menu.setAttribute('aria-expanded','false'); };
  menu?.addEventListener('click', () => { const open = nav.classList.toggle('open'); menu.setAttribute('aria-expanded', String(open)); });
  document.addEventListener('keydown', event => { if(event.key==='Escape' && nav?.classList.contains('open')) { closeMenu(); menu.focus(); } });
  document.addEventListener('click', event => { if(!event.target.closest('.site-header')) closeMenu(); });
  matchMedia('(min-width:1081px)').addEventListener('change', event => { if(event.matches) closeMenu(); });
  const consent = document.querySelector('.analytics-consent'), key='superking-analytics-consent';
  const readConsent=()=>{try{return localStorage.getItem(key);}catch{return null;}};
  const saveConsent=value=>{try{localStorage.setItem(key,value);}catch{/* Storage may be disabled. */}};
  const startAnalytics = () => {
    if(!consent || document.querySelector('[data-superking-analytics]')) return;
    const id=consent.dataset.measurement;
    window.dataLayer=window.dataLayer||[];
    window.gtag=function(){window.dataLayer.push(arguments);};
    window.gtag('js',new Date()); window.gtag('config',id,{anonymize_ip:true});
    const script=document.createElement('script'); script.async=true; script.dataset.superkingAnalytics='1';
    script.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(id); document.head.append(script);
  };
  if(consent) {
    const choice=readConsent(); consent.hidden=choice!==null;
    if(choice==='yes') startAnalytics();
    consent.querySelectorAll('[data-consent]').forEach(button=>button.addEventListener('click',()=>{
      saveConsent(button.dataset.consent); consent.hidden=true; if(button.dataset.consent==='yes')startAnalytics();
    }));
  }
  document.querySelector('#reset-consent')?.addEventListener('click',()=>{
    try{localStorage.removeItem(key);}catch{/* Storage may be disabled. */}
    // Reload stops an already loaded analytics script and shows the choice again.
    location.reload();
  });
})();
