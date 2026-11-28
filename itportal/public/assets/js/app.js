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
    const workbench = document.getElementById('checklistWorkbench');
    const configBox = document.getElementById('checklistConfig');
    const wbLine = document.getElementById('wbLine');
    const wbRev = document.getElementById('wbRev');
    const wbBadge = document.getElementById('wbBadge');
    const wbOpStatus = document.getElementById('wbOpStatus');
    const wbSaveStatus = document.getElementById('wbSaveStatus');

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
      if (!btn || !configBox || !workbench) return;
      const line = btn.getAttribute('data-line') || '';
      const rev = btn.getAttribute('data-rev') || '1';
      if (wbLine) wbLine.value = line;
      if (wbRev) wbRev.value = rev;
      workbench.hidden = false;
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
        enhanceChecklistAnswers(configBox, line, rev);
      } catch (err) {
        configBox.innerHTML = '<div class="alert alert--danger">Network error.</div>';
      }
    });

    const btnOpLogin = document.getElementById('btnOpLogin');
    const btnOpLogout = document.getElementById('btnOpLogout');
    if (btnOpLogin) {
      btnOpLogin.addEventListener('click', async () => {
        const body = new URLSearchParams({
          line: wbLine ? wbLine.value : '',
          rev: wbRev ? wbRev.value : '1',
          badge: wbBadge ? wbBadge.value : '',
          check_part: 'csl',
        });
        const res = await fetch('/api/quality/checklist-login.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
          body,
        });
        const data = await res.json();
        if (wbOpStatus) wbOpStatus.textContent = data.message || '';
      });
    }
    if (btnOpLogout) {
      btnOpLogout.addEventListener('click', async () => {
        const body = new URLSearchParams({
          line: wbLine ? wbLine.value : '',
          check_part: 'csl',
        });
        const res = await fetch('/api/quality/checklist-logout.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
          body,
        });
        const data = await res.json();
        if (wbOpStatus) wbOpStatus.textContent = data.message || '';
      });
    }

    function enhanceChecklistAnswers(box, line, rev) {
      const rows = box.querySelectorAll('tbody tr');
      rows.forEach((tr) => {
        const cells = tr.querySelectorAll('td');
        if (cells.length < 2) return;
        const ctrl = cells[0].textContent.trim();
        const question = cells[1].textContent.trim();
        const actions = document.createElement('td');
        actions.innerHTML = '<button type="button" class="btn btn--primary btn-ans-ok">OK</button> '
          + '<button type="button" class="btn btn--secondary btn-ans-nok">NOT OK</button>';
        tr.appendChild(actions);
        actions.querySelector('.btn-ans-ok').addEventListener('click', () => saveAnswer(line, rev, ctrl, question, 'OK'));
        actions.querySelector('.btn-ans-nok').addEventListener('click', () => saveAnswer(line, rev, ctrl, question, 'NOT OK'));
      });
    }

    async function saveAnswer(line, rev, idQuestion, question, checkValue) {
      const body = new URLSearchParams({
        line,
        rev,
        id_question: idQuestion,
        question,
        check_value: checkValue,
        check_part: 'csl',
        status: 'Y',
      });
      const res = await fetch('/api/quality/checklist-save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body,
      });
      const data = await res.json();
      if (wbSaveStatus) wbSaveStatus.textContent = data.message || '';
    }
  }

  const scrapForm = document.getElementById('formScrap');
  if (scrapForm) {
    scrapForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const box = document.getElementById('scrapResults');
      if (!box) return;
      box.hidden = false;
      box.innerHTML = '<div class="preloader"><div class="spinner"></div><span>Running…</span></div>';
      try {
        const res = await fetch(scrapForm.action, {
          method: 'POST',
          body: new FormData(scrapForm),
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        box.innerHTML = await res.text();
      } catch (err) {
        box.innerHTML = '<div class="alert alert--danger">Network error.</div>';
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
