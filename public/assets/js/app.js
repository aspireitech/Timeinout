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

  // Provider "New portal": suggest the customer database name and user from the address
  const dbn = document.getElementById('p-dbn'), dbu = document.getElementById('p-dbu'), slug = document.getElementById('p-slug');
  if (dbn && dbu && slug) {
    const hint = document.getElementById('p-dbu-hint'), hintText = hint ? hint.textContent : '';
    const suggest = () => dbn.dataset.dbPrefix + slug.value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    [dbn, dbu].forEach((el) => {
      el.dataset.auto = el.value === '' || el.value === suggest() ? '1' : '';
      el.addEventListener('input', () => { el.dataset.auto = ''; check(); }); // typed by hand: stop filling it in
    });
    const check = () => {
      if (!hint) return;
      const long = dbu.value.length > 32;
      hint.textContent = long ? `This user name is ${dbu.value.length} characters; MySQL allows at most 32. Shorten it (in hPanel and here).` : hintText;
      hint.style.color = long ? 'var(--danger)' : '';
    };
    slug.addEventListener('input', () => {
      const v = slug.value.trim() ? suggest() : '';
      if (dbn.dataset.auto) dbn.value = v;
      if (dbu.dataset.auto) dbu.value = v;
      check();
    });
    check();
  }

  // Import "Match your columns": show the relationship box for guardians, the guardian picker for phone/email/relationship
  const mapForm = document.getElementById('import-map');
  if (mapForm) {
    mapForm.querySelectorAll('select.map-field').forEach((sel) => {
      const sync = () => {
        const row = sel.closest('tr');
        row.querySelector('.map-label').hidden = sel.value !== 'guardian';
        row.querySelector('.map-target').hidden = !sel.value.startsWith('g_');
      };
      sel.addEventListener('change', sync);
      sync();
    });
  }
})();
