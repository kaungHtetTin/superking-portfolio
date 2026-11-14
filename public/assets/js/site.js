(() => {
  const menu = document.querySelector('.menu-toggle'), nav = document.querySelector('#site-nav');
  const closeMenu = () => { if(!menu || !nav) return; nav.classList.remove('open'); menu.setAttribute('aria-expanded','false'); };
  menu?.addEventListener('click', () => { const open = nav.classList.toggle('open'); menu.setAttribute('aria-expanded', String(open)); });
  document.addEventListener('keydown', event => { if(event.key==='Escape' && nav?.classList.contains('open')) { closeMenu(); menu.focus(); } });
  document.addEventListener('click', event => { if(!event.target.closest('.site-header')) closeMenu(); });
  matchMedia('(min-width:1081px)').addEventListener('change', event => { if(event.matches) closeMenu(); });
})();
