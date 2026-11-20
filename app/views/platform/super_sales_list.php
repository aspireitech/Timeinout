<?php
$keep = ['range' => $preset, 'from' => $from, 'to' => $to, 'group' => $group];
$back = base_path() . '/super/sales?' . http_build_query($keep);
$csv = base_path() . '/super/sales/list?' . http_build_query($keep + array_filter(['m' => $m, 'kind' => $kind, 'state' => $state, 'source' => $source]) + ['export' => 'csv']);
?>
<div class="stack" style="margin-bottom:60px">
  <div class="card">
    <div class="card-head">
      <div>
        <a class="small" href="<?= e($back) ?>">← Sales dashboard</a>
        <h2 style="margin:4px 0 0"><?= e($title) ?> <span class="badge gray"><?= number_format(count($rows)) ?></span></h2>
        <span class="muted small"><?= e((new DateTime($from))->format('M j, Y')) ?> – <?= e((new DateTime($to))->format('M j, Y')) ?><?= $m === 'paying' ? ' (all time)' : '' ?> · times in <?= e(outreach_tz()->getName()) ?></span>
      </div>
      <?php if ($rows): ?><a class="btn btn-accent btn-sm" href="<?= e($csv) ?>"><?= icon('download', 16) ?> Download CSV</a><?php endif; ?>
    </div>
    <div class="table-wrap">
    <table class="table">
      <tr><?php foreach ($cols as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr>
      <?php foreach ($rows as $r): ?>
        <tr class="small">
          <?php foreach (array_keys($cols) as $k): $v = (string) ($r[$k] ?? ''); ?>
            <td<?= $k === 'when' ? ' class="nowrap"' : '' ?>><?php
              if ($k === 'email' && $v !== '') { echo '<a href="mailto:' . e($v) . '">' . e($v) . '</a>'; }
              elseif ($k === 'phone' && $v !== '') { echo '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $v)) . '">' . e($v) . '</a>'; }
              elseif ($k === 'org') { echo '<b>' . e($v) . '</b>'; }
              else { echo e($v); }
            ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="<?= count($cols) ?>" class="empty">Nothing in this period.<?= in_array($m, ['clicked', 'opened'], true) ? ' Clicks are counted for emails sent after the tracking update.' : '' ?><?= $m === 'visits' ? ' Visits are counted from the day the traffic update was installed.' : '' ?></td></tr><?php endif; ?>
    </table>
    </div>
    <?php if (count($rows) >= 2000): ?><p class="muted small">Showing the newest 2,000. Pick a shorter date range to see the rest.</p><?php endif; ?>
  </div>
</div>
