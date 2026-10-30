// Small enhancements for the admin and sign-up pages.
(function () {
  // Confirm before destructive actions: <form data-confirm="Are you sure?">
  document.querySelectorAll('form[data-confirm]').forEach((f) =>
    f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

  // Sign-up: live preview of state prefix + optional city + short name, checked with the server
  const box = document.getElementById('addr-preview');
  if (box) {
    const org = document.getElementById('org_name'), st = document.getElementById('state'), slug = document.getElementById('slug');
    const custom = document.getElementById('addr-custom'), text = document.getElementById('addr-text');
    let own = slug.value !== '', timer;
    const show = (d) => {
      box.className = 'addr-preview ' + (d.error ? 'bad' : 'ok');
      text.textContent = d.error ? d.error : d.url + (own ? ' is available' : ' (suggested)');
    };
    const check = () => {
      clearTimeout(timer);
      timer = setTimeout(async () => {
        const q = own ? new URLSearchParams({ slug: slug.value }) : new URLSearchParams({ org: org.value, state: st.value === 'XX' ? '' : st.value });
        if (own ? slug.value.length < 1 : org.value.trim().length < 2) return;
        try {
          const d = await (await fetch(box.dataset.check + '?' + q)).json();
          if (!own) slug.value = d.slug;
          show(d);
        } catch (e) { /* offline: the server checks again on submit */ }
      }, 250);
    };
    document.getElementById('addr-edit').addEventListener('click', () => {
      own = true;
      custom.classList.remove('hidden');
      slug.focus();
      slug.select();
      check();
    });
    slug.addEventListener('input', () => {
      own = true;
      slug.value = slug.value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/--+/g, '-');
      check();
    });
    org.addEventListener('input', () => { if (!own) check(); });
    st.addEventListener('change', () => { if (!own) check(); });
    if (org.value || slug.value) check();
  }

  // Password rules shown under the box and ticked off while typing: <input data-pw-min="10">
  document.querySelectorAll('input[data-pw-min]').forEach((input) => {
    const roleSel = input.dataset.pwRole ? document.getElementById(input.dataset.pwRole) : null;
    const min = () => (roleSel ? (roleSel.value === 'staff' ? 8 : 10) : +input.dataset.pwMin);
    const list = document.createElement('ul');
    list.className = 'pw-rules';
    list.setAttribute('aria-live', 'polite');
    input.insertAdjacentElement('afterend', list);
    const rules = () => [
      [`At least ${min()} characters`, (v) => v.length >= min()],
      ['One uppercase letter (A–Z)', (v) => /[A-Z]/.test(v)],
      ['One lowercase letter (a–z)', (v) => /[a-z]/.test(v)],
      ['One number (0–9)', (v) => /\d/.test(v)],
      ['A symbol like ! @ # $ (recommended)', (v) => /[^A-Za-z0-9]/.test(v), true],
    ];
    const draw = () => {
      const v = input.value;
      const blank = v === '' && input.hasAttribute('data-pw-optional');
      list.innerHTML = (blank ? '<li class="note">Leave empty and we will create a strong one.</li>' : '') +
        rules().map(([label, test, optional]) => `<li class="${test(v) ? 'ok' : (optional ? 'opt' : '')}">${label}</li>`).join('');
      const bad = !blank && rules().some(([, test, optional]) => !optional && !test(v));
      input.setCustomValidity(bad && v !== '' ? 'Please meet the password rules shown below.' : '');
    };
    input.addEventListener('input', draw);
    if (roleSel) roleSel.addEventListener('change', draw);
    draw();
  });
  document.querySelectorAll('input[data-pw-confirm]').forEach((again) => {
    const first = document.getElementById(again.dataset.pwConfirm);
    const note = document.createElement('div');
    note.className = 'pw-match';
    again.insertAdjacentElement('afterend', note);
    const draw = () => {
      const same = again.value !== '' && again.value === first.value;
      note.className = 'pw-match ' + (again.value === '' ? '' : (same ? 'ok' : 'bad'));
      note.textContent = again.value === '' ? '' : (same ? '✓ Passwords match' : 'Passwords do not match yet');
      again.setCustomValidity(again.value !== '' && !same ? 'The two passwords do not match.' : '');
    };
    again.addEventListener('input', draw);
    first.addEventListener('input', draw);
  });

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

  // Help & FAQ: filter questions as you type
  const faqQ = document.getElementById('faq-q');
  if (faqQ) {
    faqQ.addEventListener('input', () => {
      const q = faqQ.value.trim().toLowerCase();
      let any = false;
      document.querySelectorAll('.faq-section').forEach((sec) => {
        let shown = 0;
        sec.querySelectorAll('.faq-item').forEach((it) => {
          const hit = !q || it.textContent.toLowerCase().includes(q);
          it.hidden = !hit;
          if (q && hit) it.open = true;
          if (!q) it.open = false;
          shown += hit ? 1 : 0;
        });
        sec.hidden = shown === 0;
        any = any || shown > 0;
      });
      document.getElementById('faq-none').hidden = any;
    });
  }
})();
