<?php
$colors = ['ok' => ['#0E8A66', '#E3F7F0'], 'warn' => ['#B7791F', '#FFF4DB'], 'down' => ['#C62828', '#FDECEC'], 'info' => ['#6B7090', '#F1F2F7']];
$banner = ['ok' => 'All systems operational', 'warn' => 'Some systems are degraded', 'down' => 'Some systems have an outage'][$overall];
[$fg, $bg] = $colors[$overall];
?>
<div style="max-width:860px;margin:10px auto 60px" class="stack">
  <div class="card" style="background:<?= $bg ?>;border:1px solid <?= $fg ?>33">
    <div class="row" style="justify-content:space-between;align-items:center">
      <h2 style="margin:0;color:<?= $fg ?>"><span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:<?= $fg ?>;margin-right:8px"></span><?= e($banner) ?></h2>
      <span class="muted small">Checked <?= e(gmdate('M j, Y H:i')) ?> UTC · <a href="<?= e(base_path() . '/status') ?>">Refresh</a></span>
    </div>
  </div>
  <div class="card">
    <h2 style="margin-top:0"><?= icon('shield') ?> <?= e(cfg('app_name')) ?> services</h2>
    <?php foreach ($components as $x): if ($x['public'] === '' && !$detail) continue; [$f, $b] = $colors[$x['state']]; ?>
      <div style="display:flex;gap:14px;align-items:flex-start;padding:14px 0;border-top:1px solid #ECEEF6">
        <span style="flex:none;width:12px;height:12px;border-radius:50%;background:<?= $f ?>;margin-top:6px"></span>
        <div style="flex:1;min-width:0">
          <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
            <b><?= e($x['name']) ?></b>
            <span class="badge" style="background:<?= $b ?>;color:<?= $f ?>"><?= e(STATUS_LABELS[$x['state']]) ?></span>
          </div>
          <?php if ($x['public'] !== ''): ?><div class="muted small"><?= e($x['public']) ?></div><?php endif; ?>
          <?php if ($detail && $x['detail'] !== ''): ?><pre class="small" style="white-space:pre-wrap;margin:6px 0 0;background:#F4F5FA;padding:8px;border-radius:8px;max-height:180px;overflow:auto"><?= e($x['detail']) ?></pre><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="muted small" style="text-align:center">
    <?= $detail ? 'You are signed in as the provider, so details are shown. Customers see only the plain status.' : 'Something not working? Email ' . e(contact_email()) . '.' ?>
    Machine-readable: <a href="<?= e(base_path() . '/status.json') ?>">/status.json</a>
  </p>
</div>
