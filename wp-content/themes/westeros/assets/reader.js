/* Reading room: resilient local bookmarks, a native dialog and article progress. */
(() => {
  'use strict';
  const storageKey = 'westeros-saved-posts';
  const themeKey = 'westeros-reading-theme';
  const maxSaved = 100;
  const dialog = document.querySelector('[data-saved-dialog]');
  const list = dialog?.querySelector('[data-saved-list]');
  const empty = dialog?.querySelector('[data-saved-empty]');
  const feedback = dialog?.querySelector('[data-saved-feedback]');
  const saveButtons = [...document.querySelectorAll('[data-save-post]')];
  const status = document.querySelector('[data-reader-status]');
  let saved = [];
  let storageAvailable = true;

  // Storage is user-editable. Only render same-origin links, and use text nodes.
  const normalize = (records) => {
    const found = new Set();
    if (!Array.isArray(records)) return [];
    return records.slice(0, maxSaved).flatMap((record) => {
      if (!record || typeof record !== 'object' || typeof record.title !== 'string' || !record.title.trim() || typeof record.url !== 'string') return [];
      const id = String(record.id || '');
      if (!/^\d+$/.test(id) || found.has(id)) return [];
      try {
        const url = new URL(record.url, window.location.origin);
        if (url.origin !== window.location.origin || url.username || url.password || !['http:', 'https:'].includes(url.protocol)) return [];
        found.add(id);
        return [{id, title:record.title.slice(0, 300), url:url.href}];
      } catch { return []; }
    });
  };
  const read = () => {
    let raw;
    try { raw = window.localStorage.getItem(storageKey); storageAvailable = true; }
    catch { saved = []; storageAvailable = false; return; }
    try { saved = normalize(JSON.parse(raw || '[]')); }
    catch { saved = []; }
  };
  const announce = (message) => { if (status) status.textContent = message; if (feedback) feedback.textContent = message; };
  const write = (next) => {
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(next));
      saved = next;
      storageAvailable = true;
      return true;
    } catch {
      storageAvailable = false;
      announce('ذخیره در این مرورگر ممکن نیست. تنظیمات مرورگر یا فضای ذخیره را بررسی کنید.');
      return false;
    }
  };
  const render = () => {
    document.querySelectorAll('[data-saved-count]').forEach((count) => {
      count.textContent = new Intl.NumberFormat('fa-IR').format(saved.length);
      count.hidden = !saved.length;
    });
    saveButtons.forEach((button) => {
      const active = saved.some((item) => item.id === button.dataset.postId);
      button.setAttribute('aria-pressed', String(active));
      const label = button.querySelector('[data-save-label]');
      if (label) label.textContent = active ? 'نشان شده' : 'نشان کردن';
    });
    if (list) {
      list.replaceChildren();
      saved.forEach((item) => {
        const row = document.createElement('li');
        const link = document.createElement('a');
        link.href = item.url;
        link.textContent = item.title;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = 'برداشتن';
        remove.setAttribute('aria-label', `برداشتن نشان: ${item.title}`);
        remove.addEventListener('click', () => {
          const index = saved.findIndex((record) => record.id === item.id);
          if (!write(saved.filter((record) => record.id !== item.id))) return;
          render();
          const remaining = [...list.querySelectorAll('button')];
          (remaining[Math.min(index, remaining.length - 1)] || dialog.querySelector('[data-close-saved]'))?.focus();
          announce('نشان برداشته شد.');
        });
        row.append(link, remove);
        list.append(row);
      });
    }
    if (empty) empty.hidden = saved.length > 0;
  };
  read();
  render();
  saveButtons.forEach((button) => button.addEventListener('click', () => {
    const id = button.dataset.postId;
    const exists = saved.some((item) => item.id === id);
    if (!exists && saved.length >= maxSaved) { announce('فهرست پر شده است؛ برای نشان کردن مطلب جدید، یکی از نشان‌ها را بردارید.'); return; }
    const next = exists ? saved.filter((item) => item.id !== id) : normalize([{id, title:button.dataset.postTitle, url:button.dataset.postUrl}, ...saved]);
    if (!write(next)) return;
    render();
    announce(exists ? 'نشان این روایت برداشته شد.' : 'روایت در نشان‌شده‌های همین مرورگر ذخیره شد.');
  }));
  document.querySelectorAll('[data-open-saved]').forEach((button) => button.addEventListener('click', () => {
    if (!dialog || typeof dialog.showModal !== 'function') return;
    read(); render();
    if (feedback) feedback.textContent = storageAvailable ? '' : 'ذخیره‌سازی مرورگر در دسترس نیست.';
    if (!dialog.open) dialog.showModal();
  }));
  dialog?.querySelector('[data-close-saved]')?.addEventListener('click', () => dialog.close());
  dialog?.addEventListener('click', (event) => {
    if (event.target !== dialog) return;
    const bounds = dialog.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
  });
  window.addEventListener('storage', (event) => {
    if (event.key === storageKey || event.key === null) { read(); render(); }
  });

  const themeButtons = [...document.querySelectorAll('[data-reading-theme]')];
  const updateTheme = () => {
    const night = document.documentElement.classList.contains('ws-night-reading');
    themeButtons.forEach((button) => {
      button.setAttribute('aria-pressed', String(night));
      const label = button.querySelector('[data-theme-label]');
      if (label) label.textContent = night ? 'حالت روشن' : 'حالت شب';
    });
  };
  themeButtons.forEach((button) => button.addEventListener('click', () => {
    const night = !document.documentElement.classList.contains('ws-night-reading');
    document.documentElement.classList.toggle('ws-night-reading', night);
    try { window.localStorage.setItem(themeKey, night ? 'night' : 'day'); } catch { /* Reading works even when persistence is unavailable. */ }
    updateTheme();
  }));
  updateTheme();
  window.addEventListener('storage', (event) => {
    if (event.key === themeKey || event.key === null) {
      document.documentElement.classList.toggle('ws-night-reading', event.newValue === 'night');
      updateTheme();
    }
  });

  // Preview only after a deliberate click; never move focus or scroll on page load.
  const previewButton = document.querySelector('[data-comment-preview]');
  const comment = document.getElementById('comment');
  const preview = document.getElementById('ws-comment-preview');
  if (previewButton && comment && preview) {
    const updatePreview = () => { preview.textContent = comment.value.trim() || 'هنوز دیدگاهی ننوشته‌اید.'; };
    previewButton.addEventListener('click', () => {
      const open = previewButton.getAttribute('aria-expanded') !== 'true';
      updatePreview();
      preview.hidden = !open;
      previewButton.setAttribute('aria-expanded', String(open));
      previewButton.textContent = open ? 'بستن پیش‌نمایش' : 'پیش‌نمایش دیدگاه';
    });
    comment.addEventListener('input', () => { if (!preview.hidden) updatePreview(); });
  }

  const content = document.querySelector('[data-reading-content]');
  const progress = document.querySelector('[data-reading-progress]');
  if (content && progress) {
    let queued = false;
    const paint = () => {
      queued = false;
      const box = content.getBoundingClientRect();
      const viewport = window.innerHeight;
      const distance = Math.max(1, box.height - viewport * .65);
      const fraction = Math.max(0, Math.min(1, (viewport * .2 - box.top) / distance));
      const bar = progress.querySelector('span');
      if (bar) bar.style.transform = `scaleX(${fraction})`;
      progress.setAttribute('aria-valuenow', String(Math.round(fraction * 100)));
    };
    const schedule = () => { if (!queued) { queued = true; window.requestAnimationFrame(paint); } };
    window.addEventListener('scroll', schedule, {passive:true});
    window.addEventListener('resize', schedule, {passive:true});
    window.addEventListener('load', schedule, {once:true});
    if ('ResizeObserver' in window) new ResizeObserver(schedule).observe(content);
    paint();
  }
})();
