// Kiosk flow: pick day → tile → name → (contact) → sign in/out or pickup → done.
// Visitor tiles: type your details to sign in, or tap your name to sign out.
(function () {
  const root = document.getElementById('kiosk');
  if (!root) return;
  const API = root.dataset.api, CSRF = root.dataset.csrf, TODAY = root.dataset.today, TZ = root.dataset.tz;
  const $ = (s) => root.querySelector(s);
  const $$ = (s) => Array.from(root.querySelectorAll(s));
  const S = { day: TODAY, tile: null, person: null, guardian: null };
  let resetTimer = null, idleTimer = null;

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const current = () => ($$('[data-step]').find((s) => !s.classList.contains('hidden')) || {}).dataset?.step;
  const isStaff = () => S.tile && S.tile.type === 'staff';

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
    if (current() === 'visitor') loadVisitors();
  }
  $$('#daybar button').forEach((b) => b.addEventListener('click', () => setDay(b.dataset.day)));
  const dayInput = $('#day-input');
  if (dayInput) dayInput.addEventListener('change', () => dayInput.value && setDay(dayInput.value));

  // ---- Step 1: tiles
  $$('[data-tile]').forEach((b) => b.addEventListener('click', () => {
    S.tile = { id: +b.dataset.tile, type: b.dataset.type, contact: b.dataset.contact === '1', label: b.dataset.label };
    S.person = S.guardian = null;
    if (S.tile.type === 'visitor') return openVisitor();
    $('#search-title').textContent = S.tile.label + ': find ' + (S.tile.type === 'pickup' ? 'the name' : 'your name');
    $('#search').value = '';
    $('#results').innerHTML = '';
    setHint('Start typing a first or last name.');
    show('search');
  }));
  $$('[data-back]').forEach((b) => b.addEventListener('click', () => show(b.dataset.back)));
  $('#action-back').addEventListener('click', () => show(S.tile.contact && !isStaff() ? 'guardian' : 'search'));
  $('#mat-back').addEventListener('click', () => show(S.tile.contact ? 'guardian' : 'search'));

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
    const list = await getJSON(`/search?type=${isStaff() ? 'staff' : 'members'}&q=${encodeURIComponent(q)}&date=${S.day}`);
    if (mine !== seq) return; // a newer search is already running
    $('#results').innerHTML = list.map((p, i) =>
      `<button class="pick" data-i="${i}"><span class="avatar ${isStaff() ? 'teacher' : ''}">${esc(p.initials)}</span>` +
      `<span><div class="nm">${esc(p.name)}</div>${p.grade ? `<div class="sb">${esc(p.grade)}</div>` : ''}</span>${S.tile.type === 'pickup' ? '' : badge(p.status)}</button>`).join('');
    setHint(list.length ? '' : 'No match. Try fewer letters, or ask the front desk.');
    $$('#results .pick').forEach((b) => b.addEventListener('click', () => pickPerson(list[+b.dataset.i])));
  }

  // "In since 8:02 AM" / "Out at 3:15 PM" for a search result or the selected person
  function badge(st) {
    if (!st) return '';
    return st.state === 'in'
      ? `<span class="st st-in">In since ${esc(st.time)}</span>`
      : `<span class="st st-out">Out at ${esc(st.time)}</span>`;
  }

  function whoHTML() {
    let sub = S.person.grade || (isStaff() ? S.tile.label : '');
    if (S.guardian) sub = (sub ? sub + ' · ' : '') + 'with ' + S.guardian.name + (S.guardian.relationship ? ` (${S.guardian.relationship})` : '');
    return `<span class="avatar ${isStaff() ? 'teacher' : ''}">${esc(S.person.initials)}</span><div><div style="font-weight:800;font-size:1.15rem">${esc(S.person.name)}</div><div class="muted">${esc(sub)}</div></div>${S.tile.type === 'pickup' ? '' : badge(S.person.status)}`;
  }

  // ---- Step 3: only this person's contacts (when the tile asks for one)
  async function pickPerson(p) {
    S.person = p; S.guardian = null;
    if (!S.tile.contact || isStaff()) return S.tile.type === 'pickup' ? openMaterial() : openAction();
    $('#who-g').innerHTML = whoHTML();
    $('#guardian-title').textContent = S.tile.type === 'pickup' ? 'Who is picking up?' : 'Who is with them?';
    $('#guardians').innerHTML = '<div class="spinner"></div>';
    show('guardian');
    const list = await getJSON(`/guardians?student_id=${p.id}`);
    if (!list.length) {
      $('#guardians').innerHTML = '<div class="k-hint">No contact is on file for this person. Please see the front desk.</div>';
      return;
    }
    $('#guardians').innerHTML = list.map((g, i) =>
      `<button class="pick" data-i="${i}"><span class="avatar material">${esc(g.initials)}</span>` +
      `<span><div class="nm">${esc(g.name)}</div><div class="sb">${esc(g.relationship || 'Contact')}</div></span></button>`).join('');
    $$('#guardians .pick').forEach((b) => b.addEventListener('click', () => {
      S.guardian = list[+b.dataset.i];
      S.tile.type === 'pickup' ? openMaterial() : openAction();
    }));
  }

  // ---- Step 4a: sign in / out, with only the valid choice enabled
  function openAction() {
    $('#who-a').innerHTML = whoHTML();
    show('action');
    loadStatus();
  }
  async function loadStatus() {
    const note = $('#status-note');
    const btnIn = $('.action.in'), btnOut = $('.action.out');
    const withContact = S.tile.contact && !isStaff();
    const inSub = withContact ? 'Arriving / drop-off' : 'Start / arrive';
    const outSub = withContact ? 'Leaving / pick-up' : 'Finish / leave';
    note.className = 'status-note hidden';
    [btnIn, btnOut].forEach((b) => { b.disabled = true; b.classList.remove('suggest'); }); // no taps until we know
    const st = await getJSON(`/status?type=${isStaff() ? 'teacher' : 'student'}&id=${S.person.id}&date=${S.day}`);
    S.person.status = st.state === 'none' ? null : st;
    $('#who-a').innerHTML = whoHTML();
    const day = S.day === TODAY ? 'today' : 'that day';
    const by = st.guardian ? ` by ${st.guardian}` : '';
    if (st.state === 'in') {
      note.textContent = `✓ Signed in at ${st.time}${by}. Waiting for sign-out.`;
      note.className = 'status-note is-in';
      btnOut.disabled = false; btnOut.classList.add('suggest');
      $('#in-sub').textContent = `Already signed in at ${st.time}`;
      $('#out-sub').textContent = outSub;
    } else {
      note.textContent = st.state === 'out' ? `Signed out at ${st.time}${by}. Tap Sign In if they are back.` : `Not signed in ${day} yet.`;
      note.className = 'status-note ' + (st.state === 'out' ? 'is-out' : 'is-none');
      btnIn.disabled = false; btnIn.classList.add('suggest');
      $('#in-sub').textContent = inSub;
      $('#out-sub').textContent = st.state === 'out' ? `Already signed out at ${st.time}` : `Not signed in ${day}`;
    }
  }

  // ---- Step 4b: item pickup
  function openMaterial() {
    $('#who-m').innerHTML = whoHTML();
    $$('input[name=mat]').forEach((c) => { c.checked = false; });
    $('#mat-other').value = '';
    show('material');
  }

  // ---- Visitors
  function openVisitor() {
    $('#visitor-title').textContent = S.tile.label;
    ['#v-name', '#v-company', '#v-host'].forEach((s) => { $(s).value = ''; });
    show('visitor');
    setTimeout(() => $('#v-name').focus(), 60);
    loadVisitors();
  }
  async function loadVisitors() {
    const box = $('#v-list');
    const list = await getJSON(`/visitors?date=${S.day}`);
    box.innerHTML = list.length ? list.map((v, i) =>
      `<button class="pick" data-i="${i}"><span class="avatar material">${esc(v.initials)}</span>` +
      `<span><div class="nm">${esc(v.name)}</div><div class="sb">${esc([v.company, v.host && 'visiting ' + v.host].filter(Boolean).join(' · '))}</div></span>` +
      `<span class="st st-in">In since ${esc(v.since)}</span></button>`).join('') : '<div class="k-hint">No visitors are signed in.</div>';
    $$('#v-list .pick').forEach((b) => b.addEventListener('click', () => submit('visitor', {
      tile_id: S.tile.id, action: 'sign_out', visit_id: list[+b.dataset.i].visit_id, date: S.day, time: pastTime('visitor'),
    }, b)));
  }
  $('#v-submit').addEventListener('click', (e) => {
    if ($('#v-name').value.trim().length < 2) return fail('visitor', 'Please type your full name.');
    submit('visitor', { tile_id: S.tile.id, action: 'sign_in', visitor_name: $('#v-name').value, company: $('#v-company').value,
      host: $('#v-host').value, date: S.day, time: pastTime('visitor') }, e.currentTarget);
  });

  const pastTime = (step) => ($(`[data-step="${step}"] .past-time`) || {}).value || '';
  function fail(step, m) {
    const err = $(`[data-step="${step}"] .k-error`);
    err.textContent = m; err.classList.remove('hidden');
  }

  async function submit(step, payload, btn) {
    $(`[data-step="${step}"] .k-error`).classList.add('hidden');
    if (S.day !== TODAY && !payload.time) return fail(step, 'Please enter the time for that day.');
    btn.disabled = true;
    try {
      const r = await fetch(API + '/record', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, Accept: 'application/json' },
        body: JSON.stringify(payload),
      });
      if (r.status === 419) { fail(step, 'Session expired. Reloading…'); setTimeout(() => location.reload(), 1200); return; }
      const data = await r.json().catch(() => ({}));
      if (!r.ok || !data.ok) return fail(step, data.error || 'Something went wrong. Please try again.');
      done(data);
    } catch (e) {
      fail(step, 'Connection problem. Please try again.');
    } finally {
      btn.disabled = false;
    }
  }

  $$('.action').forEach((b) => b.addEventListener('click', () => submit('action', {
    tile_id: S.tile.id, person_id: S.person.id, guardian_id: S.guardian ? S.guardian.id : 0,
    action: b.dataset.action, date: S.day, time: pastTime('action'),
  }, b)));

  $('#mat-submit').addEventListener('click', (e) => {
    const items = $$('input[name=mat]:checked').map((c) => c.value);
    const other = $('#mat-other').value.trim();
    if (!items.length && !other) return fail('material', 'Please choose at least one item.');
    submit('material', {
      tile_id: S.tile.id, person_id: S.person.id, guardian_id: S.guardian ? S.guardian.id : 0,
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
    S.tile = S.person = S.guardian = null;
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
