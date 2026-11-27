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
})();
