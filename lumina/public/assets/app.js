document.querySelectorAll('form[data-confirm]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    const message = form.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });
});

document.querySelectorAll('select[data-submit]').forEach((select) => {
  select.addEventListener('change', () => {
    if (select.form) {
      select.form.submit();
    }
  });
});

const summary = document.querySelector('[data-slot-summary]');
const slotField = document.querySelector('#prenota input[name="slot"]');
document.querySelectorAll('input[type="radio"][name="slot"]').forEach((input) => {
  input.addEventListener('change', () => {
    const card = input.closest('.slot');
    if (summary && card) {
      summary.textContent = card.getAttribute('data-label') || 'Orario selezionato';
    }
    if (slotField) slotField.value = input.value;
  });
});
document.querySelectorAll('[data-account]').forEach((input) => {
  const sync = () => {
    const business = document.querySelector('[data-business]');
    const chosen = document.querySelector('[data-account]:checked');
    if (business && chosen) {
      business.hidden = chosen.value !== 'business';
    }
  };
  input.addEventListener('change', sync);
  sync();
});

document.querySelectorAll('[data-digits]').forEach((box) => {
  const digits = [...box.querySelectorAll('.digit')];
  const hidden = box.parentElement ? box.parentElement.querySelector('input[name="code"]') : null;
  const write = () => {
    if (hidden) hidden.value = digits.map((digit) => digit.value).join('');
  };
  digits.forEach((digit, index) => {
    digit.addEventListener('input', () => {
      digit.value = digit.value.replace(/\D/g, '').slice(-1);
      if (digit.value && digits[index + 1]) digits[index + 1].focus();
      write();
    });
    digit.addEventListener('keydown', (event) => {
      if (event.key === 'Backspace' && digit.value === '' && digits[index - 1]) {
        digits[index - 1].focus();
      }
    });
    digit.addEventListener('paste', (event) => {
      const clip = event.clipboardData;
      if (!clip) return;
      const text = clip.getData('text').replace(/\D/g, '').slice(0, 6);
      if (text.length === 0) return;
      event.preventDefault();
      [...text].forEach((char, offset) => {
        if (digits[offset]) digits[offset].value = char;
      });
      digits[Math.min(text.length, digits.length) - 1].focus();
      write();
    });
  });
});

const prenota = document.querySelector('#prenota');
if (prenota) {
  prenota.addEventListener('submit', (event) => {
    if (!slotField || slotField.value === '') {
      event.preventDefault();
      if (summary) summary.textContent = 'Scegli un orario nella lista.';
    }
  });
}
