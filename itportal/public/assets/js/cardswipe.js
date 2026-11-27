/**
 * Magnetic badge swipe listener (Articona / plant badges).
 * Port of loginCard/js/jquery.cardswipe.js start-char ò (charCode 242).
 */
(function () {
  const IDLE = 0;
  const PENDING = 1;
  const READING = 2;
  let state = IDLE;
  let buffer = [];
  let timer = 0;

  function clearTimer() {
    if (timer) window.clearTimeout(timer);
    timer = 0;
  }

  function startTimer() {
    clearTimer();
    timer = window.setTimeout(() => {
      state = IDLE;
      buffer = [];
    }, 500);
  }

  function parseBadge(raw) {
    try {
      const part = raw.split('ò')[1] || '';
      const codeChunk = part.split('_')[0] || '';
      const split000 = codeChunk.split('000');
      return (split000[1] || codeChunk).trim();
    } catch (e) {
      return '';
    }
  }

  function finish() {
    const raw = buffer.join('');
    const code = parseBadge(raw);
    const swiped = document.getElementById('swiped');
    if (swiped) swiped.value = raw;

    if (!code) return;

    const pass = document.getElementById('pass');
    if (pass) {
      pass.value = code;
      const form = document.getElementById('formLogin');
      if (form) form.submit();
      return;
    }

    fetch('/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: new URLSearchParams({ pass: code, login: 'card' }),
    }).then(() => {
      window.location.href = '/index.php';
    });
  }

  document.addEventListener('keypress', (e) => {
    const which = e.which || e.keyCode;
    if (state === IDLE) {
      if (which === 242) {
        state = PENDING;
        buffer = [String.fromCharCode(which)];
        e.preventDefault();
        startTimer();
      }
      return;
    }

    if (state === PENDING) {
      state = READING;
      buffer.push(String.fromCharCode(which));
      e.preventDefault();
      startTimer();
      return;
    }

    if (state === READING) {
      buffer.push(String.fromCharCode(which));
      e.preventDefault();
      startTimer();
      if (which === 13) {
        clearTimer();
        state = IDLE;
        finish();
      }
    }
  });
})();
