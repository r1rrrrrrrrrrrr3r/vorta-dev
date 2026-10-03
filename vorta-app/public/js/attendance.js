(function () {
  'use strict';
  // Listener didelegasikan ke document (dipasang sekali) supaya tetap jalan setelah
  // navigasi Turbo/morph tanpa menumpuk. Jam per halaman dibersihkan lewat Vorta.onLeave.

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

  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (form.id === 'absenceReasonForm') {
      const now = new Date();
      if (now.getHours() === 23 && now.getMinutes() >= 59) {
        e.preventDefault();
        window.Vorta?.toast("The deadline for today's absence report (23:59) has passed.", { tone: 'bad' });
      }
      return;
    }
    if (form.id === 'checkInForm') {
      const shiftSelect = document.getElementById('shiftSelect');
      const explanationContainer = document.getElementById('explanationContainer');
      const textarea = form.querySelector('textarea[name="explanation"]');
      const explanation = textarea?.value.trim();
      const err = form.querySelector('[data-late-error]');
      if (shiftSelect && isLate(shiftSelect.value, getCurrentTime()) && (!explanation || explanation.length < 10)) {
        e.preventDefault();
        if (explanationContainer) explanationContainer.hidden = false;
        if (err) err.hidden = false;
        textarea?.setAttribute('aria-invalid', 'true');
        textarea?.focus();
      }
    }
  });

  document.addEventListener('change', function (e) {
    const select = e.target;
    if (select.id !== 'shiftSelect') return;
    const explanationContainer = document.getElementById('explanationContainer');
    const checkInForm = document.getElementById('checkInForm');
    const shiftHint = document.getElementById('shiftHint');
    const late = isLate(select.value, getCurrentTime());
    if (explanationContainer) explanationContainer.hidden = !late;
    if (shiftHint) {
      shiftHint.textContent = !select.value
        ? "Pick the shift you're working today."
        : (late ? "You're past the on-time limit for this shift, so a reason is needed." : "You're on time for this shift.");
    }

    const locationMap = { WFO: 'Office', WAC: 'Client', WFH: 'Home', WFA: 'Anywhere' };
    const locationInput = checkInForm?.querySelector('input[name="location"]');
    if (locationInput && locationMap[select.value]) {
      locationInput.value = locationMap[select.value];
    }
  });

  // Waktu check-out di pesan konfirmasi mengikuti jam saat tombol ditekan
  // (capture, supaya jalan sebelum dialog konfirmasi di ui.js membaca data-confirm-message)
  document.addEventListener('click', function (e) {
    const form = e.target.closest && e.target.closest('[data-checkout-form]');
    if (!form) return;
    const now = new Date();
    const hhmm = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    form.dataset.confirmMessage = `Your check-out time will be ${hhmm}.`;
  }, true);

  // Jam di kartu hari ini (satu interval saja, juga setelah morph refresh)
  let clockTimer = null;
  function init() {
    clearInterval(clockTimer);
    clockTimer = null;
    if (!document.querySelector('[data-clock]')) return;
    const tick = () => {
      const clock = document.querySelector('[data-clock]');
      if (!clock) return;
      const now = new Date();
      clock.textContent = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    };
    clockTimer = setInterval(tick, 15000);
    if (window.Vorta && window.Vorta.onLeave) window.Vorta.onLeave(() => { clearInterval(clockTimer); clockTimer = null; });
  }

  document.addEventListener('turbo:load', init);
  if (!window.Turbo) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  }
})();
