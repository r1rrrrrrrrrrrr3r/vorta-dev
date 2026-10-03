(function () {
  'use strict';

  const shiftSelect = document.getElementById('shiftSelect');
  const explanationContainer = document.getElementById('explanationContainer');
  const checkInForm = document.getElementById('checkInForm');
  const shiftHint = document.getElementById('shiftHint');
  const absenceReasonForm = document.getElementById('absenceReasonForm');

  function getCurrentTime() {
    const now = new Date();
    return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:00`;
  }

  // Ambang sama dengan server (attendance.php)
  function isLate(shift, timeStr) {
    const [h, m] = timeStr.split(':').map(Number);
    const minutes = h * 60 + m;
    if (shift === 'Morning' && minutes > 8 * 60 + 15) return true;
    if (shift === 'Afternoon' && minutes > 13 * 60 + 30) return true;
    if (['WFO', 'WAC', 'WFH', 'WFA'].includes(shift) && minutes > 9 * 60 + 30) return true;
    return false;
  }

  absenceReasonForm?.addEventListener('submit', function (e) {
    const now = new Date();
    if (now.getHours() === 23 && now.getMinutes() >= 59) {
      e.preventDefault();
      window.Vorta?.toast("The deadline for today's absence report (23:59) has passed.", { tone: 'bad' });
    }
  });

  shiftSelect?.addEventListener('change', function () {
    const late = isLate(this.value, getCurrentTime());
    explanationContainer.hidden = !late;
    if (shiftHint) {
      shiftHint.textContent = !this.value
        ? "Pick the shift you're working today."
        : (late ? "You're past the on-time limit for this shift, so a reason is needed." : "You're on time for this shift.");
    }

    const locationMap = { WFO: 'Office', WAC: 'Client', WFH: 'Home', WFA: 'Anywhere' };
    const locationInput = checkInForm?.querySelector('input[name="location"]');
    if (locationInput && locationMap[this.value]) {
      locationInput.value = locationMap[this.value];
    }
  });

  checkInForm?.addEventListener('submit', function (e) {
    const textarea = checkInForm.querySelector('textarea[name="explanation"]');
    const explanation = textarea?.value.trim();
    const err = checkInForm.querySelector('[data-late-error]');
    if (isLate(shiftSelect.value, getCurrentTime()) && (!explanation || explanation.length < 10)) {
      e.preventDefault();
      explanationContainer.hidden = false;
      if (err) err.hidden = false;
      textarea?.setAttribute('aria-invalid', 'true');
      textarea?.focus();
    }
  });

  // Waktu check-out di pesan konfirmasi mengikuti jam saat tombol ditekan
  document.querySelectorAll('[data-checkout-form]').forEach(form => {
    form.addEventListener('click', () => {
      const now = new Date();
      const hhmm = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
      form.dataset.confirmMessage = `Your check-out time will be ${hhmm}.`;
    }, true);
  });

  // Jam di kartu hari ini
  const clock = document.querySelector('[data-clock]');
  if (clock) {
    const tick = () => {
      const now = new Date();
      clock.textContent = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    };
    setInterval(tick, 15000);
  }
})();
