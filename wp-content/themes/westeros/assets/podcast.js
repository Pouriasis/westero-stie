/* Native fallback only; the official Acast player owns its playback state. */
(() => {
  'use strict';
  const audios = [...document.querySelectorAll('.ws-podcast-fallback audio')];
  audios.forEach(audio => {
    audio.addEventListener('error', () => {
      const message = audio.closest('.ws-podcast-fallback')?.querySelector('.ws-podcast-audio-error');
      if (message) { message.hidden = false; message.setAttribute('role', 'status'); }
    });
    audio.addEventListener('play', () => { audios.forEach(other => { if (other !== audio) other.pause(); }); });
  });
})();
