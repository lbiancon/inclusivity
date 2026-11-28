(function () {
  const nav = document.getElementById('mainNav');
  const toggle = document.getElementById('navToggle');
  if (toggle && nav) {
    toggle.addEventListener('click', () => nav.classList.toggle('is-open'));
  }

  document.querySelectorAll('.nav-item__btn').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      if (window.matchMedia('(max-width: 960px)').matches) {
        e.preventDefault();
        btn.parentElement.classList.toggle('is-open');
      }
    });
  });

  const form = document.getElementById('formData');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const preloader = document.getElementById('preloader');
      const wrapper = document.getElementById('wrapper');
      if (preloader) preloader.hidden = false;
      if (wrapper) wrapper.hidden = true;

      try {
        const res = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const html = await res.text();
        if (wrapper) {
          wrapper.innerHTML = html;
          wrapper.hidden = false;
        }
      } catch (err) {
        if (wrapper) {
          wrapper.innerHTML = '<div class="alert alert--danger">Network error during search.</div>';
          wrapper.hidden = false;
        }
      } finally {
        if (preloader) preloader.hidden = true;
      }
    });
  }

  const pallet = document.getElementById('formPallet');
  if (pallet) {
    pallet.addEventListener('submit', async (e) => {
      e.preventDefault();
      const box = document.getElementById('palletResults');
      if (!box) return;
      box.hidden = false;
      box.innerHTML = '<div class="preloader"><div class="spinner"></div><span>Search…</span></div>';
      try {
        const res = await fetch(pallet.action, {
          method: 'POST',
          body: new FormData(pallet),
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        box.innerHTML = await res.text();
      } catch (err) {
        box.innerHTML = '<div class="alert alert--danger">Network error.</div>';
      }
    });
  }

  const btnLines = document.getElementById('btnLoadLines');
  if (btnLines) {
    const linesBox = document.getElementById('checklistLines');
    const configBox = document.getElementById('checklistConfig');
    btnLines.addEventListener('click', async () => {
      if (!linesBox) return;
      linesBox.innerHTML = '<div class="preloader"><div class="spinner"></div><span>Loading lines…</span></div>';
      try {
        const res = await fetch('/api/quality/checklist-lines.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        linesBox.innerHTML = await res.text();
      } catch (err) {
        linesBox.innerHTML = '<div class="alert alert--danger">Network error.</div>';
      }
    });

    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-load-config');
      if (!btn || !configBox) return;
      const line = btn.getAttribute('data-line') || '';
      const rev = btn.getAttribute('data-rev') || '1';
      configBox.hidden = false;
      configBox.innerHTML = '<div class="preloader"><div class="spinner"></div><span>Loading config…</span></div>';
      const body = new URLSearchParams({ line, rev, check_part: 'csl' });
      try {
        const res = await fetch('/api/quality/checklist-config.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body,
        });
        configBox.innerHTML = await res.text();
      } catch (err) {
        configBox.innerHTML = '<div class="alert alert--danger">Network error.</div>';
      }
    });
  }

  // Optional: after serial search, also load process vars into #processResults if present
  const serialForm = document.getElementById('formData');
  const processBox = document.getElementById('processResults');
  if (serialForm && processBox) {
    serialForm.addEventListener('submit', async () => {
      const sn = document.getElementById('part_sn');
      if (!sn || !sn.value) return;
      processBox.hidden = false;
      processBox.innerHTML = '<div class="preloader"><div class="spinner"></div><span>Process vars…</span></div>';
      try {
        const body = new FormData();
        body.set('part_sn', sn.value);
        body.set('proc_type', '0');
        body.set('fields', '20');
        const res = await fetch('/api/tracking/process.php', {
          method: 'POST',
          body,
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        processBox.innerHTML = await res.text();
      } catch (err) {
        processBox.innerHTML = '<div class="alert alert--danger">Process lookup failed.</div>';
      }
    });
  }
})();
