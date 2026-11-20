<?php
// Slide-in "free checklist" offer on the public home and landing pages: appears after 40 seconds or 60% scroll, at most once
// every 30 days per browser, never after signing up, and never covers the page (small corner card, thin on phones).
$_ckKind = preg_match('#^/for/(schools|tutoring)$#', (string) ($GLOBALS['request_path'] ?? '')) ? 'care' : (preg_match('#^/for/#', (string) ($GLOBALS['request_path'] ?? '')) ? 'work' : '');
?>
<style>
.ckp { position: fixed; right: 18px; bottom: 18px; z-index: 60; width: 340px; max-width: calc(100vw - 32px); background: #fff; color: #161B36; border-radius: 18px;
  box-shadow: 0 18px 50px rgba(22,27,54,.22); padding: 16px 16px 14px; transform: translateY(140%); transition: transform .45s ease; font-size: 14px; }
.ckp.on { transform: none; }
.ckp h4 { margin: 0 26px 4px 0; font-size: 16px; line-height: 1.3; }
.ckp p { margin: 0 0 10px; color: #4A5072; line-height: 1.4; }
.ckp-x { position: absolute; top: 8px; right: 10px; border: 0; background: none; font-size: 22px; line-height: 1; color: #8A8FA8; cursor: pointer; padding: 4px; }
.ckp input, .ckp select { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #D7DAE8; border-radius: 10px; font: inherit; margin-bottom: 8px; }
.ckp button[type=submit] { width: 100%; padding: 11px; border: 0; border-radius: 10px; background: #5B4CE0; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
.ckp small { display: block; color: #8A8FA8; margin-top: 6px; font-size: 11.5px; }
.ckp .hp { position: absolute; left: -9999px; }
@media (max-width: 720px) { .ckp { left: 12px; right: 12px; width: auto; bottom: calc(76px + env(safe-area-inset-bottom)); padding: 12px 14px; }
  .ckp p.ckp-long { display: none; } .ckp small { margin-top: 4px; } .ckp h4 { font-size: 15px; margin-bottom: 8px; }
  .ckp input, .ckp select { padding: 8px 10px; margin-bottom: 6px; } .ckp button[type=submit] { padding: 9px; } }
@media print { .ckp { display: none; } }
</style>
<div class="ckp" id="ckp" role="dialog" aria-labelledby="ckp-t" aria-hidden="true" data-csrf="<?= e(csrf_token()) ?>" data-action="<?= e(base_path() . '/subscribe') ?>">
  <button type="button" class="ckp-x" id="ckp-x" aria-label="Close">&times;</button>
  <h4 id="ckp-t">Free checklist: is your front door covered?</h4>
  <p class="ckp-long">12 quick checks for safe pickups, accurate staff hours and knowing who is in the building. Print it and tick it off.</p>
  <form id="ckp-f" method="post" action="<?= e(base_path() . '/subscribe') ?>">
    <?= csrf_field() ?>
    <label class="hp" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
    <select name="kind" aria-label="Your type of place" required>
      <?php if ($_ckKind === ''): ?><option value="" disabled selected>Your type of place…</option><?php endif; ?>
      <option value="care" <?= $_ckKind === 'care' ? 'selected' : '' ?>>School, daycare or tutoring center</option>
      <option value="work" <?= $_ckKind === 'work' ? 'selected' : '' ?>>Office, clinic, gym, shop or job site</option>
    </select>
    <input type="email" name="email" placeholder="Your work email" required autocomplete="email" aria-label="Your email">
    <button type="submit">Email me the free checklist</button>
    <small id="ckp-m">Plus occasional tips and new features. Unsubscribe any time.</small>
  </form>
</div>
<script>(function () {
  var box = document.getElementById('ckp'); if (!box) return;
  var KEY = 'cio_ckp', now = Date.now();
  function get() { try { return +localStorage.getItem(KEY) || 0; } catch (e) { return 0; } }
  function set(days) { try { localStorage.setItem(KEY, String(now + days * 864e5)); } catch (e) {} }
  if (get() > now || /(^|; )cio_sub=1/.test(document.cookie)) return;
  var shown = false;
  function show() { if (shown) return; shown = true; box.classList.add('on'); box.setAttribute('aria-hidden', 'false'); set(30); }
  var timer = setTimeout(show, 40000);
  window.addEventListener('scroll', function () {
    var h = document.documentElement; if ((h.scrollTop + innerHeight) / h.scrollHeight > 0.6) show();
  }, { passive: true });
  document.getElementById('ckp-x').addEventListener('click', function () { clearTimeout(timer); box.classList.remove('on'); box.setAttribute('aria-hidden', 'true'); set(30); });
  document.getElementById('ckp-f').addEventListener('submit', function (ev) {
    if (!window.fetch) return; ev.preventDefault();
    var f = ev.target, btn = f.querySelector('button'); btn.disabled = true;
    fetch(box.dataset.action, { method: 'POST', body: new FormData(f), headers: { 'Accept': 'application/json', 'X-CSRF-Token': box.dataset.csrf }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok) { document.cookie = 'cio_sub=1; path=/; max-age=31536000; samesite=lax'; set(365);
          location.href = d.url; }
        else { document.getElementById('ckp-m').textContent = d.error || 'Please try again.'; btn.disabled = false; }
      })
      .catch(function () { f.submit(); });
  });
})();</script>
