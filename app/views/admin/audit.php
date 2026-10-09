<?php $qs = http_build_query(array_filter($f, fn($v) => $v !== '')); ?>
<div class="topbar">
  <div><h1>Audit log</h1><div class="muted">Every admin change, sign-in, sign-in code and report, with who did it, when and whether it worked.</div></div>
  <a class="btn btn-ghost" href="<?= e(url('/admin/audit?' . $qs . '&export=csv')) ?>"><?= icon('download') ?> Download CSV</a>
</div>
<div class="card">
  <form class="inline-form" method="get" style="margin-bottom:14px">
    <div class="field"><label for="a-from">From</label><input type="date" id="a-from" name="from" value="<?= e($f['from']) ?>"></div>
    <div class="field"><label for="a-to">To</label><input type="date" id="a-to" name="to" value="<?= e($f['to']) ?>"></div>
    <div class="field"><label for="a-res">Result</label><select id="a-res" name="result"><option value="">All</option><option value="success" <?= $f['result'] === 'success' ? 'selected' : '' ?>>Success</option><option value="failed" <?= $f['result'] === 'failed' ? 'selected' : '' ?>>Failed</option></select></div>
    <div class="field"><label for="a-q">Search</label><input type="search" id="a-q" name="q" value="<?= e($f['q']) ?>" placeholder="User, action, student…"></div>
    <button class="btn btn-primary">Filter</button>
  </form>
  <?php require APP_DIR . '/views/partials/audit_table.php'; ?>
  <?php if ($total > 100): ?>
    <div class="row" style="justify-content:center;margin-top:14px">
      <?php if ($page > 1): ?><a class="btn btn-sm" href="?<?= e($qs . '&page=' . ($page - 1)) ?>">← Newer</a><?php endif; ?>
      <span class="muted small">Page <?= $page ?> of <?= (int) ceil($total / 100) ?> · <?= $total ?> entries</span>
      <?php if ($page * 100 < $total): ?><a class="btn btn-sm" href="?<?= e($qs . '&page=' . ($page + 1)) ?>">Older →</a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
