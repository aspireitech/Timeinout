// Small enhancements for the admin and sign-up pages.
(function () {
  // Confirm before destructive actions: <form data-confirm="Are you sure?">
  document.querySelectorAll('form[data-confirm]').forEach((f) =>
    f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

  // Sign-up: live preview of state prefix + optional city + short name, checked with the server
  const box = document.getElementById('addr-preview');
  if (box) {
    const st = document.getElementById('state'), city = document.getElementById('city'), short = document.getElementById('short');
    let timer;
    const check = () => {
      clearTimeout(timer);
      timer = setTimeout(async () => {
        if (!st.value || short.value.length < 1) return;
        const q = new URLSearchParams({ state: st.value === 'XX' ? '' : st.value, city: city.value, name: short.value });
        const d = await (await fetch(box.dataset.check + '?' + q)).json();
        box.className = 'addr-preview ' + (d.error ? 'bad' : 'ok');
        box.lastElementChild.textContent = d.error ? d.error : d.url + ' is available';
      }, 250);
    };
    short.addEventListener('input', () => { short.value = short.value.toLowerCase().replace(/[^a-z0-9]/g, ''); check(); });
    city.addEventListener('input', check);
    st.addEventListener('change', check);
    if (short.value) check();
  }

  // Default the time zone select to the browser's zone
  const tz = document.querySelector('select[data-auto]');
  if (tz && tz.value === 'America/New_York') { // only replace the untouched default
    try {
      const mine = Intl.DateTimeFormat().resolvedOptions().timeZone;
      if (mine && [...tz.options].some((o) => o.value === mine)) tz.value = mine;
    } catch (e) { /* keep default */ }
  }
})();
