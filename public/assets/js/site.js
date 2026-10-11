(() => {
  const menu = document.querySelector('.menu-toggle'), nav = document.querySelector('#site-nav');
  menu?.addEventListener('click', () => { const open = nav.classList.toggle('open'); menu.setAttribute('aria-expanded', String(open)); menu.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation'); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && nav?.classList.contains('open')) { nav.classList.remove('open'); menu.setAttribute('aria-expanded','false'); menu.focus(); } });
  const carousel=document.querySelector('.hero-carousel');
  if (carousel && window.jQuery?.fn.owlCarousel) {
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const multiple = carousel.children.length>1;
    const autoplay = carousel.dataset.autoplay==='1' && !reduced && multiple;
    const syncSlides=()=>{carousel.querySelectorAll('.owl-item').forEach(item=>{const active=item.classList.contains('active');item.setAttribute('aria-hidden',String(!active));item.querySelectorAll('a,button').forEach(link=>{if(active)link.removeAttribute('tabindex');else link.setAttribute('tabindex','-1');});});};
    jQuery(carousel).on('initialized.owl.carousel translated.owl.carousel',syncSlides);
    jQuery(carousel).owlCarousel({items:1,loop:multiple,nav:multiple,dots:multiple,autoplay,autoplayTimeout:6500,autoplayHoverPause:true,smartSpeed:reduced?0:450,navText:['<span aria-hidden="true">←</span>','<span aria-hidden="true">→</span>']});
    document.querySelector('.owl-prev')?.setAttribute('aria-label','Previous slide');
    document.querySelector('.owl-next')?.setAttribute('aria-label','Next slide');
    document.querySelectorAll('.owl-dot').forEach((dot,i)=>dot.setAttribute('aria-label',`Go to slide ${i+1}`));
    if(autoplay){const pause=document.createElement('button');pause.className='btn carousel-pause';pause.textContent='Pause slides';pause.setAttribute('aria-pressed','false');carousel.parentElement.append(pause);pause.addEventListener('click',()=>{const paused=pause.getAttribute('aria-pressed')!=='true';jQuery(carousel).trigger(paused?'stop.owl.autoplay':'play.owl.autoplay');pause.setAttribute('aria-pressed',String(paused));pause.textContent=paused?'Play slides':'Pause slides';});}
  }
})();
