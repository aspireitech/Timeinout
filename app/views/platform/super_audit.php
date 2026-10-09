<?php $qs = http_build_query(array_filter($f + ['tenant' => $tenant ?: ''], fn($v) => $v !== '')); $showSchool = true; ?>
<div class="card" style="margin-bottom:60px">
  <div class="card-head">
    <h2><?= icon('list') ?> Audit log, all schools</h2>
    <a class="btn btn-ghost btn-sm" href="<?= e(base_path() . '/super/audit?' . $qs . '&export=csv') ?>"><?= icon('download', 16) ?> Download CSV</a>
  </div>
  <p class="muted small">Times are UTC. Includes school admin changes, sign-ins and codes, report sends, and your own provider actions.</p>
  <form class="inline-form" method="get" style="margin-bottom:14px">
    <div class="field"><label for="sa-school">School</label><select id="sa-school" name="tenant"><option value="">All schools</option><?php foreach ($tenants as $tn): ?><option value="<?= (int) $tn['id'] ?>" <?= $tenant === (int) $tn['id'] ? 'selected' : '' ?>><?= e($tn['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="sa-from">From</label><input type="date" id="sa-from" name="from" value="<?= e($f['from']) ?>"></div>
    <div class="field"><label for="sa-to">To</label><input type="date" id="sa-to" name="to" value="<?= e($f['to']) ?>"></div>
    <div class="field"><label for="sa-res">Result</label><select id="sa-res" name="result"><option value="">All</option><option value="success" <?= $f['result'] === 'success' ? 'selected' : '' ?>>Success</option><option value="failed" <?= $f['result'] === 'failed' ? 'selected' : '' ?>>Failed</option></select></div>
    <div class="field"><label for="sa-q">Search</label><input type="search" id="sa-q" name="q" value="<?= e($f['q']) ?>"></div>
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
