// Kiosk flow: pick day → role → name → guardian → sign in/out or pickup → done.
(function () {
  const root = document.getElementById('kiosk');
  if (!root) return;
  const API = root.dataset.api, CSRF = root.dataset.csrf, TODAY = root.dataset.today, TZ = root.dataset.tz;
  const $ = (s) => root.querySelector(s);
  const $$ = (s) => Array.from(root.querySelectorAll(s));
  const S = { day: TODAY, mode: null, person: null, guardian: null };
  let resetTimer = null, idleTimer = null;

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const current = () => ($$('[data-step]').find((s) => !s.classList.contains('hidden')) || {}).dataset?.step;

  function show(step) {
    $$('[data-step]').forEach((s) => s.classList.toggle('hidden', s.dataset.step !== step));
    $$('.k-error').forEach((e) => e.classList.add('hidden'));
    $$('.time-field').forEach((f) => f.classList.toggle('hidden', S.day === TODAY));
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (step === 'search') setTimeout(() => $('#search').focus(), 60);
    armIdle(step);
  }

  // ---- Clock in the organization's timezone; reload after midnight so "Today" stays right
  function localDate() {
    try { return new Date().toLocaleDateString('en-CA', { timeZone: TZ }); } catch (e) { return TODAY; }
  }
  function tick() {
    const now = new Date();
    try {
      $('#k-time').textContent = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', timeZone: TZ });
      $('#k-date').textContent = now.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric', timeZone: TZ });
    } catch (e) {
      $('#k-time').textContent = now.toLocaleTimeString();
    }
    if (current() === 'role' && localDate() > TODAY) location.reload();
  }
  tick();
  setInterval(tick, 10000);

  // ---- Day picker
  function setDay(d) {
    S.day = d;
    let matched = false;
    $$('#daybar button').forEach((b) => { const on = b.dataset.day === d; b.classList.toggle('on', on); matched = matched || on; });
    const inp = $('#day-input');
    if (inp) { inp.value = d; inp.parentElement.classList.toggle('on', !matched); }
    $$('.time-field').forEach((f) => f.classList.toggle('hidden', d === TODAY));
    if (current() === 'action') loadStatus();
  }
  $$('#daybar button').forEach((b) => b.addEventListener('click', () => setDay(b.dataset.day)));
  const dayInput = $('#day-input');
  if (dayInput) dayInput.addEventListener('change', () => dayInput.value && setDay(dayInput.value));

  // ---- Step 1: role
  const titles = { student: 'Student — find your name', teacher: 'Teacher / Staff — find your name', material: 'Material pickup — find the student' };
  $$('[data-mode]').forEach((b) => b.addEventListener('click', () => {
    S.mode = b.dataset.mode; S.person = S.guardian = null;
    $('#search-title').textContent = titles[S.mode];
    $('#search').value = '';
    $('#results').innerHTML = '';
    setHint('Start typing a first or last name.');
    show('search');
  }));
  $$('[data-back]').forEach((b) => b.addEventListener('click', () => show(b.dataset.back)));
  $('#action-back').addEventListener('click', () => show(S.mode === 'teacher' ? 'search' : 'guardian'));

  async function getJSON(path) {
    const r = await fetch(API + path, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (r.status === 401) { location.reload(); throw new Error('Please log in.'); }
    return r.json();
  }

  // ---- Step 2: type a few letters
  let seq = 0, debounce;
  function setHint(text) { const h = $('#search-hint'); h.textContent = text; h.classList.toggle('hidden', !text); }
  $('#search').addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(runSearch, 180); });
  async function runSearch() {
    const q = $('#search').value.trim();
    const mine = ++seq;
    if (!q) { $('#results').innerHTML = ''; setHint('Start typing a first or last name.'); return; }
    const type = S.mode === 'teacher' ? 'teacher' : 'student';
    const list = await getJSON(`/search?type=${type}&q=${encodeURIComponent(q)}`);
    if (mine !== seq) return; // a newer search is already running
    $('#results').innerHTML = list.map((p, i) =>
      `<button class="pick" data-i="${i}"><span class="avatar ${type === 'teacher' ? 'teacher' : ''}">${esc(p.initials)}</span>` +
      `<span><div class="nm">${esc(p.name)}</div>${p.grade ? `<div class="sb">${esc(p.grade)}</div>` : ''}</span></button>`).join('');
    setHint(list.length ? '' : 'No match. Try fewer letters, or ask the front desk.');
    $$('#results .pick').forEach((b) => b.addEventListener('click', () => pickPerson(list[+b.dataset.i])));
  }

  function whoHTML() {
    const cls = S.mode === 'teacher' ? 'teacher' : '';
    let sub = S.person.grade || (S.mode === 'teacher' ? 'Teacher / Staff' : '');
    if (S.guardian) sub = (sub ? sub + ' · ' : '') + 'with ' + S.guardian.name + (S.guardian.relationship ? ` (${S.guardian.relationship})` : '');
    return `<span class="avatar ${cls}">${esc(S.person.initials)}</span><div><div style="font-weight:800;font-size:1.15rem">${esc(S.person.name)}</div><div class="muted">${esc(sub)}</div></div>`;
  }

  // ---- Step 3: only this student's guardians
  async function pickPerson(p) {
    S.person = p; S.guardian = null;
    if (S.mode === 'teacher') return openAction();
    $('#who-g').innerHTML = whoHTML();
    $('#guardian-title').textContent = S.mode === 'material' ? 'Who is picking up?' : 'Who is dropping off or picking up?';
    $('#guardians').innerHTML = '<div class="spinner"></div>';
    show('guardian');
    const list = await getJSON(`/guardians?student_id=${p.id}`);
    if (!list.length) {
      $('#guardians').innerHTML = '<div class="k-hint">No parent or guardian is on file for this student. Please see the front desk.</div>';
      return;
    }
    $('#guardians').innerHTML = list.map((g, i) =>
      `<button class="pick" data-i="${i}"><span class="avatar material">${esc(g.initials)}</span>` +
      `<span><div class="nm">${esc(g.name)}</div><div class="sb">${esc(g.relationship || 'Guardian')}</div></span></button>`).join('');
    $$('#guardians .pick').forEach((b) => b.addEventListener('click', () => {
      S.guardian = list[+b.dataset.i];
      S.mode === 'material' ? openMaterial() : openAction();
    }));
  }

  // ---- Step 4a: sign in / out, with the likely choice highlighted
  function openAction() {
    $('#who-a').innerHTML = whoHTML();
    $('#in-sub').textContent = S.mode === 'teacher' ? 'Start my day' : 'Arriving / drop-off';
    $('#out-sub').textContent = S.mode === 'teacher' ? 'End my day' : 'Leaving / pick-up';
    show('action');
    loadStatus();
  }
  async function loadStatus() {
    const note = $('#status-note');
    note.classList.add('hidden');
    $$('.action').forEach((a) => a.classList.remove('suggest', 'dim'));
    const type = S.mode === 'teacher' ? 'teacher' : 'student';
    const st = await getJSON(`/status?type=${type}&id=${S.person.id}&date=${S.day}`);
    const suggest = st.state === 'in' ? 'sign_out' : 'sign_in';
    $$('.action').forEach((a) => a.classList.add(a.dataset.action === suggest ? 'suggest' : 'dim'));
    if (st.state !== 'none') {
      note.textContent = `${st.state === 'in' ? 'Signed in' : 'Signed out'} at ${st.time}${st.guardian ? ' by ' + st.guardian : ''}.`;
      note.classList.remove('hidden');
    }
  }

  // ---- Step 4b: material pickup
  function openMaterial() {
    $('#who-m').innerHTML = whoHTML();
    $$('input[name=mat]').forEach((c) => { c.checked = false; });
    $('#mat-other').value = '';
    show('material');
  }

  const pastTime = (step) => ($(`[data-step="${step}"] .past-time`) || {}).value || '';

  async function submit(step, payload, btn) {
    const err = $(`[data-step="${step}"] .k-error`);
    const fail = (m) => { err.textContent = m; err.classList.remove('hidden'); };
    err.classList.add('hidden');
    if (S.day !== TODAY && !payload.time) return fail('Please enter the time for that day.');
    btn.disabled = true;
    try {
      const r = await fetch(API + '/record', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, Accept: 'application/json' },
        body: JSON.stringify(payload),
      });
      if (r.status === 419) { fail('Session expired — reloading…'); setTimeout(() => location.reload(), 1200); return; }
      const data = await r.json().catch(() => ({}));
      if (!r.ok || !data.ok) return fail(data.error || 'Something went wrong. Please try again.');
      done(data);
    } catch (e) {
      fail('Connection problem. Please try again.');
    } finally {
      btn.disabled = false;
    }
  }

  $$('.action').forEach((b) => b.addEventListener('click', () => submit('action', {
    mode: S.mode, person_id: S.person.id, guardian_id: S.guardian ? S.guardian.id : 0,
    action: b.dataset.action, date: S.day, time: pastTime('action'),
  }, b)));

  $('#mat-submit').addEventListener('click', (e) => {
    const items = $$('input[name=mat]:checked').map((c) => c.value);
    const other = $('#mat-other').value.trim();
    if (!items.length && !other) {
      const err = $('[data-step="material"] .k-error');
      err.textContent = 'Please choose at least one item.';
      err.classList.remove('hidden');
      return;
    }
    submit('material', {
      mode: 'material', person_id: S.person.id, guardian_id: S.guardian.id,
      materials: items, other, date: S.day, time: pastTime('material'),
    }, e.currentTarget);
  });

  // ---- Step 5: confirmation, then back to start
  function done(data) {
    $('#done-title').textContent = data.message;
    $('#done-detail').textContent = data.detail;
    const bar = $('.bar-timer i');
    bar.style.animation = 'none'; void bar.offsetHeight; bar.style.animation = '';
    show('done');
    clearTimeout(resetTimer);
    resetTimer = setTimeout(reset, 6000);
  }
  function reset() {
    clearTimeout(resetTimer);
    S.mode = S.person = S.guardian = null;
    $$('.past-time').forEach((t) => { t.value = ''; }); // never carry a time over to the next person
    show('role');
    setDay(TODAY);
  }
  $('#done-btn').addEventListener('click', reset);

  // Shared tablet: go back to the start after 90 s without a touch
  function armIdle(step) {
    clearTimeout(idleTimer);
    if (step && step !== 'role' && step !== 'done') idleTimer = setTimeout(reset, 90000);
  }
  ['click', 'keydown', 'touchstart'].forEach((ev) => document.addEventListener(ev, () => armIdle(current()), { passive: true }));
})();
