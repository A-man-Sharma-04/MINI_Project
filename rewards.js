(function() {
  const emailInput = document.getElementById('notify-email');
  const submitBtn = document.getElementById('notify-submit');
  const note = document.getElementById('notify-note');
  const notifyBtn = document.getElementById('notify-btn');

  function showMessage(msg, ok = true) {
    if (!note) return;
    note.textContent = msg;
    note.style.color = ok ? '#22d3ee' : '#f97316';
  }

  function isValidEmail(email) {
    return /.+@.+\..+/.test(email);
  }

  function handleSubmit() {
    const email = (emailInput?.value || '').trim();
    if (!isValidEmail(email)) {
      showMessage('Enter a valid email to get notified.', false);
      return;
    }
    showMessage('Thanks! You are on the early access list.');
  }

  submitBtn?.addEventListener('click', handleSubmit);
  notifyBtn?.addEventListener('click', () => {
    if (emailInput) {
      emailInput.focus();
      emailInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });
})();
