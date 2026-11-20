<?php $groups = []; foreach ($people as $i => $p) { $groups[$p['group']][$i] = $p; } ?>
<div class="page-head"><div><h1 style="color:#C62828"><?= icon('shield') ?> Emergency roll call</h1>
  <p class="muted">Everyone signed in right now (<?= e(date('D, M j', strtotime($today))) ?>). Tick each person as they are counted outside. Anyone left unticked is <b>missing</b>.</p></div></div>
<div class="card" style="max-width:820px" id="rc">
  <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
    <div style="font-size:1.3rem"><b id="rc-ok">0</b> of <b><?= count($people) ?></b> accounted for · <span id="rc-miss" style="color:#C62828;font-weight:800"><?= count($people) ?> missing</span></div>
    <div class="row"><button type="button" class="btn btn-ghost btn-sm" id="rc-missing">Show missing only</button><button type="button" class="btn btn-ghost btn-sm" id="rc-reset">Start over</button><button type="button" class="btn btn-ghost btn-sm" onclick="window.print()">Print</button></div>
  </div>
  <?php if (!$people): ?><p class="empty">Nobody is signed in right now.</p><?php endif; ?>
  <?php foreach ($groups as $g => $list): ?>
    <h3 style="margin:18px 0 6px"><?= e($g) ?> (<?= count($list) ?>)</h3>
    <?php foreach ($list as $i => $p): ?>
      <label class="check rc-row" style="padding:12px;border:1px solid #ECEEF6;border-radius:12px;margin:6px 0;font-size:1.05rem">
        <input type="checkbox" class="rc-box" data-i="<?= $i ?>" style="width:26px;height:26px">
        <span><b><?= e($p['name']) ?></b><br><span class="muted small"><?= e($p['sub']) ?></span></span>
      </label>
    <?php endforeach; ?>
  <?php endforeach; ?>
</div>
<script>
(() => {
  const key = 'rollcall-<?= e($today) ?>';
  const boxes = [...document.querySelectorAll('.rc-box')];
  let saved = {}; try { saved = JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) {}
  let missingOnly = false;
  const draw = () => {
    const ok = boxes.filter(b => b.checked).length;
    document.getElementById('rc-ok').textContent = ok;
    const miss = boxes.length - ok;
    const m = document.getElementById('rc-miss');
    m.textContent = miss ? miss + ' missing' : 'everyone accounted for';
    m.style.color = miss ? '#C62828' : '#0E8A66';
    boxes.forEach(b => { const row = b.closest('.rc-row'); row.style.background = b.checked ? '#E3F7F0' : ''; row.style.display = missingOnly && b.checked ? 'none' : ''; });
  };
  boxes.forEach(b => { b.checked = !!saved[b.dataset.i]; b.addEventListener('change', () => { saved[b.dataset.i] = b.checked; try { localStorage.setItem(key, JSON.stringify(saved)); } catch (e) {} draw(); }); });
  document.getElementById('rc-missing').addEventListener('click', e => { missingOnly = !missingOnly; e.target.textContent = missingOnly ? 'Show everyone' : 'Show missing only'; draw(); });
  document.getElementById('rc-reset').addEventListener('click', () => { if (!confirm('Clear all ticks and start over?')) return; saved = {}; try { localStorage.removeItem(key); } catch (e) {} boxes.forEach(b => b.checked = false); draw(); });
  draw();
})();
</script>
