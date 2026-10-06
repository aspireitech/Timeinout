// Small enhancements for the admin and sign-up pages.
(function () {
  // Confirm before destructive actions: <form data-confirm="Are you sure?">
  document.querySelectorAll('form[data-confirm]').forEach((f) =>
    f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

  // Sign-up: suggest the portal address from the organization name and check it live
  const org = document.getElementById('org_name');
  const slug = document.getElementById('slug');
  const hint = document.getElementById('slug-hint');
  if (org && slug) {
    let touched = slug.value !== '';
    const clean = (s) => s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 30);
    let timer;
    const check = () => {
      clearTimeout(timer);
      timer = setTimeout(async () => {
        if (!slug.value) return;
        const r = await fetch(slug.dataset.check + '?slug=' + encodeURIComponent(slug.value));
        const d = await r.json();
        hint.textContent = d.error ? d.error : '✓ ' + d.slug + ' is available';
        hint.style.color = d.error ? 'var(--danger)' : 'var(--in)';
      }, 300);
    };
    org.addEventListener('input', () => { if (!touched) { slug.value = clean(org.value); check(); } });
    slug.addEventListener('input', () => { touched = true; slug.value = slug.value.toLowerCase().replace(/[^a-z0-9-]+/g, '-').slice(0, 30); check(); });
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
