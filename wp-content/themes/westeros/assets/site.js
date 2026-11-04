/* Westeros: native disclosure menus and manually controlled editorial shelves. */
(() => {
  'use strict';
  const motion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
  const nav = document.querySelector('.ws-nav');
  const menuToggle = nav?.querySelector('.ws-mobile-toggle');
  const setMenu = (open) => {
    if (!nav || !menuToggle) return;
    nav.classList.toggle('is-open', open);
    menuToggle.setAttribute('aria-expanded', String(open));
  };
  menuToggle?.addEventListener('click', () => setMenu(menuToggle.getAttribute('aria-expanded') !== 'true'));
  nav?.querySelectorAll('.ws-submenu-toggle').forEach((button) => {
    const item = button.parentElement;
    const desktop = () => window.matchMedia('(min-width: 768px)').matches;
    const setSubmenu = (open) => {
      button.setAttribute('aria-expanded', String(open));
      item.classList.toggle('is-open', open);
    };
    button.addEventListener('click', () => {
      const open = button.getAttribute('aria-expanded') !== 'true';
      setSubmenu(open);
    });
    item.addEventListener('pointerenter', (event) => { if (desktop() && event.pointerType !== 'touch') setSubmenu(true); });
    item.addEventListener('pointerleave', () => { if (desktop() && !item.contains(document.activeElement)) setSubmenu(false); });
    item.addEventListener('focusin', (event) => { if (desktop() && event.target !== button) setSubmenu(true); });
    item.addEventListener('focusout', (event) => { if (!item.contains(event.relatedTarget)) setSubmenu(false); });
  });
  // Reveal disclosure controls only once their handlers are available.
  nav?.classList.add('is-ready');
  nav?.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    const submenu = event.target.closest('li.is-open');
    if (submenu) {
      submenu.classList.remove('is-open');
      const toggle = submenu.querySelector(':scope > .ws-submenu-toggle');
      toggle?.setAttribute('aria-expanded', 'false');
      toggle?.focus();
    } else if (menuToggle && window.matchMedia('(max-width: 767px)').matches) {
      setMenu(false);
      menuToggle.focus();
    }
  });
  document.addEventListener('click', (event) => {
    if (!nav || nav.contains(event.target)) return;
    setMenu(false);
    nav.querySelectorAll('li.is-open').forEach((item) => {
      item.classList.remove('is-open');
      item.querySelector(':scope > .ws-submenu-toggle')?.setAttribute('aria-expanded', 'false');
    });
  });

  document.querySelectorAll('[data-feature-slider]').forEach((slider) => {
    const slides = [...slider.querySelectorAll('.ws-feature-slide')];
    const dots = [...slider.querySelectorAll('[data-feature-index]')];
    const status = slider.querySelector('[data-feature-status]');
    if (!slides.length) return;
    let current = 0;
    const show = (index) => {
      current = (index + slides.length) % slides.length;
      slides.forEach((slide, i) => { slide.hidden = i !== current; });
      dots.forEach((dot, i) => {
        if (i === current) dot.setAttribute('aria-current', 'true');
        else dot.removeAttribute('aria-current');
      });
      if (status) status.textContent = slides[current].querySelector('h2')?.textContent || '';
    };
    slider.querySelector('[data-feature-prev]')?.addEventListener('click', () => show(current - 1));
    slider.querySelector('[data-feature-next]')?.addEventListener('click', () => show(current + 1));
    dots.forEach((dot) => dot.addEventListener('click', () => show(Number(dot.dataset.featureIndex))));
    // Arrow keys on controls work in the reading direction; article links retain normal keyboard behavior.
    slider.addEventListener('keydown', (event) => {
      if (!(event.target instanceof HTMLButtonElement)) return;
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        show(current + (event.key === 'ArrowLeft' ? 1 : -1));
      }
    });
    slides.forEach((slide, index) => { slide.hidden = index !== 0; });
    slider.classList.add('is-ready');
  });

  document.querySelectorAll('[data-shelf]').forEach((shelf) => {
    const track = shelf.querySelector('.ws-shelf-track');
    const cards = [...(track?.children || [])];
    const previous = shelf.querySelector('[data-shelf-prev]');
    const next = shelf.querySelector('[data-shelf-next]');
    if (!track || !cards.length) return;
    const isRtl = getComputedStyle(track).direction === 'rtl';
    const closest = () => {
      const box = track.getBoundingClientRect();
      const edge = isRtl ? box.right : box.left;
      let best = 0, distance = Infinity;
      cards.forEach((card, index) => {
        const bounds = card.getBoundingClientRect();
        const delta = Math.abs((isRtl ? bounds.right : bounds.left) - edge);
        if (delta < distance) { best = index; distance = delta; }
      });
      return best;
    };
    const pageSize = () => {
      const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
      return Math.max(1, Math.round((track.clientWidth + gap) / (cards[0].getBoundingClientRect().width + gap)));
    };
    const refresh = () => {
      const index = closest();
      if (previous) previous.disabled = index === 0;
      if (next) next.disabled = index + pageSize() >= cards.length;
    };
    const step = (direction) => {
      const target = Math.max(0, Math.min(cards.length - pageSize(), closest() + direction * pageSize()));
      const trackBox = track.getBoundingClientRect();
      const cardBox = cards[target].getBoundingClientRect();
      const delta = isRtl ? cardBox.right - trackBox.right : cardBox.left - trackBox.left;
      track.scrollBy({left: delta, behavior: motion()});
    };
    previous?.addEventListener('click', () => step(-1));
    next?.addEventListener('click', () => step(1));
    track.addEventListener('keydown', (event) => {
      if (event.target !== track) return;
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        step((event.key === 'ArrowLeft') === isRtl ? 1 : -1);
      }
    });
    track.addEventListener('scroll', refresh, {passive:true});
    if ('ResizeObserver' in window) new ResizeObserver(refresh).observe(track);
    else window.addEventListener('resize', refresh, {passive:true});
    refresh();
  });
})();
