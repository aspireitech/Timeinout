<?php
$n = $d['now'];
$b = $d['before'];
$opens = setting('outreach_track_opens', '0') === '1';
$fmtDate = fn($s) => (new DateTime($s))->format('M j, Y');
$q = fn(array $over) => e(base_path() . '/super/sales?' . http_build_query(array_merge(['range' => $preset, 'from' => $from, 'to' => $to, 'group' => $group], $over)));
$presets = ['7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days', 'month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year'];
$tile = function (string $label, string $value, string $sub, array $delta, ?string $hint = null): string {
    return '<div class="kpi"><div class="kpi-l">' . e($label) . ($hint ? ' <span class="muted" title="' . e($hint) . '">ⓘ</span>' : '') . '</div>'
        . '<div class="kpi-v">' . e($value) . '</div><div class="kpi-s">' . e($sub) . '</div>'
        . ($delta[0] !== '' ? '<div class="kpi-d ' . e($delta[1]) . '">' . e($delta[0]) . '</div>' : '') . '</div>';
};
?>
<style>
  .sales { --s1: #2a78d6; --s2: #eb6834; --s3: #1baf7a; --s4: #eda100; --s5: #e87ba4;
           --ink: #0b0b0b; --ink2: #52514e; --muted: #898781; --grid: #e1e0d9; --axis: #c3c2b7; --track: #eef0f6; }
  .sales .filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 14px; }
  .sales .filters form { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin: 0; }
  .sales .filters input[type=date], .sales .filters select { width: auto; padding: 6px 10px; }
  .sales .kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; margin-bottom: 14px; }
  .sales .kpi { background: #fff; border: 1px solid rgba(11,11,11,.08); border-top: 4px solid var(--s1); border-radius: 14px; padding: 12px 16px 14px; }
  .sales .kpi:nth-child(5n+2) { border-top-color: var(--s2); } .sales .kpi:nth-child(5n+3) { border-top-color: var(--s3); }
  .sales .kpi:nth-child(5n+4) { border-top-color: var(--s4); } .sales .kpi:nth-child(5n+5) { border-top-color: var(--s5); }
  .sales .rings { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 14px; margin-bottom: 18px; }
  .sales .ring { text-align: center; } .sales .ring svg { width: 116px; height: 116px; display: block; margin: 0 auto 4px; }
  .sales .ring-v { font-size: 19px; font-weight: 800; fill: var(--ink); } .sales .ring-l { font-weight: 700; font-size: .9rem; } .sales .ring-s { font-size: .8rem; color: var(--ink2); }
  .sales .kpi-l { font-size: .82rem; color: var(--ink2); font-weight: 600; }
  .sales .kpi-v { font-size: 1.9rem; font-weight: 800; color: var(--ink); line-height: 1.2; margin-top: 4px; font-variant-numeric: tabular-nums; }
  .sales .kpi-s { font-size: .8rem; color: var(--ink2); }
  .sales .kpi-d { font-size: .76rem; margin-top: 4px; color: var(--ink2); }
  .sales .kpi-d.up { color: #006300; } .sales .kpi-d.down { color: #b4232a; }
  .sales .meters { display: grid; gap: 10px; }
  .sales .meter { display: grid; grid-template-columns: 170px 1fr 120px; gap: 12px; align-items: center; font-size: .9rem; }
  .sales .meter .bar { height: 14px; background: var(--track); border-radius: 4px; overflow: hidden; }
  .sales .meter .bar i { display: block; height: 100%; background: var(--s1); border-radius: 0 4px 4px 0; min-width: 2px; }
  .sales .meter .fig { font-variant-numeric: tabular-nums; color: var(--ink); text-align: right; }
  .sales .meter .fig small { color: var(--ink2); }
  .sales .multiples { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 14px; }
  .sales .viz { width: 100%; height: auto; display: block; }
  .sales .viz .grid { stroke: var(--grid); stroke-width: 1; } .sales .viz .base { stroke: var(--axis); stroke-width: 1; }
  .sales .viz .tick { font-size: 10px; fill: var(--muted); }
  .sales .viz .hit { fill: transparent; cursor: default; } .sales .viz .hit:hover { fill: rgba(11,11,11,.04); }
  .sales h3 { margin: 0 0 2px; font-size: 1rem; } .sales .chart-sum { font-size: .82rem; color: var(--ink2); margin-bottom: 6px; }
  .sales .seg { display: flex; gap: 2px; height: 22px; border-radius: 4px; overflow: hidden; margin: 8px 0 12px; }
  .sales .seg i { display: block; height: 100%; }
  .sales .legend { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 6px 18px; font-size: .88rem; }
  .sales .legend span.sw { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 8px; vertical-align: 0; }
  .sales .legend b { font-variant-numeric: tabular-nums; }
  .sales .mini { display: inline-block; width: 80px; height: 8px; background: var(--track); border-radius: 3px; vertical-align: middle; margin-right: 6px; overflow: hidden; }
  .sales .mini i { display: block; height: 100%; background: var(--s1); }
  .sales td.num, .sales th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  #viz-tip { position: fixed; pointer-events: none; background: #161B36; color: #fff; font-size: .8rem; padding: 6px 9px; border-radius: 8px; display: none; z-index: 50; white-space: nowrap; }
  @media (max-width: 640px) { .sales .meter { grid-template-columns: 1fr 90px; } .sales .meter .bar { grid-column: 1 / -1; grid-row: 2; } .sales .multiples { grid-template-columns: 1fr; } }
</style>

<div class="sales stack" style="margin-bottom:60px">
  <div class="card">
    <div class="card-head">
      <h2><?= icon('chart') ?> Sales dashboard</h2>
      <span class="muted small"><?= e($fmtDate($from)) ?> – <?= e($fmtDate($to)) ?> · <?= (int) $d['days'] ?> days · times in <?= e(outreach_tz()->getName()) ?></span>
    </div>
    <div class="filters">
      <?php foreach ($presets as $k => $label): ?>
        <a class="btn btn-sm <?= $preset === $k ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(base_path() . '/super/sales?range=' . $k) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <form method="get" action="<?= e(base_path() . '/super/sales') ?>">
        <input type="hidden" name="range" value="custom">
        <input type="date" name="from" value="<?= e($from) ?>" aria-label="From"> – <input type="date" name="to" value="<?= e($to) ?>" aria-label="To">
        <select name="group" aria-label="Group by"><?php foreach (['day' => 'By day', 'week' => 'By week', 'month' => 'By month'] as $k => $l): ?><option value="<?= $k ?>" <?= $group === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm btn-ghost">Apply</button>
      </form>
    </div>

    <div class="kpis">
      <?= $tile('Prospects found', number_format($n['found']), 'new organizations added', sales_delta($n['found'], $b['found'])) ?>
      <?= $tile('Emails sent', number_format($n['emails']), number_format($n['first']) . ' first emails · ' . number_format($n['emailed']) . ' people', sales_delta($n['emails'], $b['emails'])) ?>
      <?= $tile('Delivered', sales_pct($n['delivery_rate']), number_format($n['bounced']) . ' bounced', sales_delta($n['delivery_rate'], $b['delivery_rate'], true), 'Emailed and not bounced. Keep it above 97%.') ?>
      <?php if ($opens): ?><?= $tile('Opened (estimate)', sales_pct($n['open_rate']), number_format($n['opened']) . ' people', sales_delta($n['open_rate'], $b['open_rate'], true), 'iPhones and some company mail systems open every email by themselves, so this reads high.') ?><?php endif; ?>
      <?= $tile('Clicked', sales_pct($n['click_rate']), number_format($n['clicked']) . ' people clicked a link', sales_delta($n['click_rate'], $b['click_rate'], true), 'People who clicked the trial, demo or website link. Link scanners are left out.') ?>
      <?= $tile('Replied', sales_pct($n['reply_rate']), number_format($n['replied']) . ' replies', sales_delta($n['reply_rate'], $b['reply_rate'], true)) ?>
      <?= $tile('Demos booked', number_format($n['demo']), 'from prospects', sales_delta($n['demo'], $b['demo'])) ?>
      <?= $tile('Signed up', number_format($n['signed_up']), sales_pct($n['signup_rate']) . ' of people emailed', sales_delta($n['signed_up'], $b['signed_up'])) ?>
    </div>
    <p class="muted small" style="margin:0">Rates are out of the <?= number_format($n['emailed']) ?> prospects emailed in this period. Arrows compare with the <?= (int) $d['days'] ?> days before it.
      <?php if (!$opens): ?>Opens are not counted (recommended): turn it on in Prospects → settings if you want an estimate.<?php endif; ?></p>
  </div>

  <div class="card" id="hot">
    <div class="card-head">
      <h2 style="margin:0">🔥 Call today <span class="muted small" style="font-weight:400">clicked in the last 7 days, no reply yet</span></h2>
      <span class="badge <?= $hot ? 'out' : 'gray' ?>"><?= count($hot) ?> to call</span>
    </div>
    <?php if ($hot): ?>
      <p class="chart-sum">They looked at your offer. A two-minute call now ("I saw you had a look, any questions?") converts far better than another email. After the call, set them to Replied or Booked demo in Prospects. This list is also emailed to you every weekday at 8:00.</p>
      <div class="table-wrap"><table class="table">
        <tr><th>Who</th><th>Call / email</th><th>Looked at</th><th>Last click</th></tr>
        <?php foreach ($hot as $p): ?>
          <tr class="small">
            <td><b><?= e($p['org']) ?></b><br><span class="muted"><?= e(PROSPECT_KINDS[$p['kind']] ?? $p['kind']) ?><?= $p['city'] ? ' · ' . e($p['city']) . ', ' . e((string) $p['state']) : '' ?></span></td>
            <td><?php if ($p['phone']): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $p['phone'])) ?>"><b><?= e($p['phone']) ?></b></a><br><?php endif; ?><a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a><?= $p['first_name'] ? '<br><span class="muted">ask for ' . e($p['first_name']) . '</span>' : '' ?></td>
            <td><?= e(ucfirst($p['looked_at'])) ?><?= (int) $p['clicks'] > 1 ? '<br><span class="muted">' . (int) $p['clicks'] . ' clicks</span>' : '' ?></td>
            <td class="nowrap"><?= e((new DateTime($p['last_click'], new DateTimeZone('UTC')))->setTimezone(outreach_tz())->format('D M j, g:i A')) ?></td>
          </tr>
        <?php endforeach; ?>
      </table></div>
    <?php else: ?>
      <p class="muted small" style="margin:0">Nobody to call yet. When a prospect clicks the trial, demo or website link in an outreach email and does not reply, they appear here.</p>
    <?php endif; ?>
  </div>

  <div class="card" id="traffic">
    <h2 style="margin-top:0">Website traffic</h2>
    <div class="kpis">
      <?= $tile('Visits', number_format($web['visits']), number_format($web['views']) . ' page views', ['', ''], 'One visit per person per day, counted on our server (ad blockers do not hide it). Bots are left out.') ?>
      <?= $tile('On a phone', $web['visits'] ? sales_pct($web['mobile'] / max(1, $web['views']), 0) : '–', 'of page views', ['', '']) ?>
      <?= $tile('Demo requests', number_format($web['demos']), 'all sources', ['', '']) ?>
      <?= $tile('Sign-ups (trials)', number_format($web['signups']), 'all sources', ['', '']) ?>
      <?= $tile('Visit → lead', sales_pct($web['conv']), 'visits that became a demo request or sign-up', ['', ''], 'A good small-business software site converts 2–5% of visits.') ?>
    </div>
    <div class="multiples" style="margin-bottom:14px">
      <div>
        <h3>Visits</h3>
        <?php $vs = array_sum($visits); $vk = $vs ? array_search(max($visits), $visits, true) : null; ?>
        <div class="chart-sum"><?= number_format($vs) ?> in total<?= $vk !== null ? ' · most: ' . e(preg_replace('/^Week of /', 'week of ', $d['buckets'][$vk])) . ' (' . number_format($visits[$vk]) . ')' : '' ?></div>
        <?= sales_columns($visits, $d['buckets'], 'visits') ?>
      </div>
      <div>
        <h3>Where visitors land</h3>
        <div class="chart-sum">First page of each visit</div>
        <div class="meters">
          <?php $pmax = max(1, ...array_map('intval', array_column($web['pages'], 'n') ?: [0])); foreach (array_slice($web['pages'], 0, 7) as $pg): ?>
            <div class="meter" style="grid-template-columns:150px 1fr 60px"><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($pg['path']) ?></span><span class="bar"><i style="width:<?= round($pg['n'] / $pmax * 100, 1) ?>%"></i></span><span class="fig"><b><?= number_format((int) $pg['n']) ?></b></span></div>
          <?php endforeach; ?>
          <?php if (!$web['pages']): ?><p class="muted small">No visits counted yet in this period.</p><?php endif; ?>
        </div>
      </div>
    </div>
    <h3>Where visitors come from, and which sources turn into leads</h3>
    <div class="table-wrap"><table class="table">
      <tr><th>Source</th><th class="num">Visits</th><th class="num">Demo requests</th><th class="num">Sign-ups</th><th>Visit → lead</th></tr>
      <?php foreach (array_slice($web['by_source'], 0, 15, true) as $src => $r): $cv = $r['visits'] ? ($r['demos'] + $r['signups']) / $r['visits'] : null; ?>
        <tr class="small"><td><b><?= e((string) $src) ?></b></td><td class="num"><?= number_format($r['visits']) ?></td><td class="num"><?= number_format($r['demos']) ?></td><td class="num"><?= number_format($r['signups']) ?></td>
          <td class="nowrap"><?php if ($cv !== null): ?><span class="mini"><i style="width:<?= round(min(1, $cv / 0.1) * 100, 1) ?>%"></i></span><?= sales_pct($cv) ?><?php else: ?><span class="muted">no visits counted</span><?php endif; ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$web['by_source']): ?><tr><td colspan="5" class="empty">Nothing yet in this period.</td></tr><?php endif; ?>
    </table></div>
    <p class="muted small" style="margin:8px 0 0">Tag every link you share with <code>?src=</code> (e.g. <code>clockinoutapp.com/?src=flyer</code>) or use the short links <code>/facebook</code>, <code>/linkedin</code>, <code>/email</code>, <code>/friend</code>: each visit, demo request and sign-up is then credited to it, even when they come back days later. The "Visit → lead" bar is full at 10%.</p>
  </div>

  <div class="card">
    <h2 style="margin-top:0">Funnel</h2>
    <div class="rings">
      <?= sales_ring($n['delivery_rate'], 'Delivered', number_format($n['delivered']) . ' of ' . number_format($n['emailed'])) ?>
      <?php if ($opens): ?><?= sales_ring($n['open_rate'], 'Opened (estimate)', number_format($n['opened']) . ' people') ?><?php endif; ?>
      <?= sales_ring($n['click_rate'], 'Clicked', number_format($n['clicked']) . ' people') ?>
      <?= sales_ring($n['reply_rate'], 'Replied', number_format($n['replied']) . ' people') ?>
      <?= sales_ring($n['interest_rate'], 'Interested', 'replied, demo or sign-up') ?>
      <?= sales_ring($n['signup_rate'], 'Signed up', number_format($n['signed_up']) . ' trials') ?>
    </div>
    <p class="chart-sum">Of <?= number_format($n['emailed']) ?> prospects emailed, <?= number_format($n['clicked']) ?> clicked, <?= number_format($n['replied']) ?> replied and <?= number_format($n['signed_up']) ?> signed up.</p>
    <div class="meters">
      <?php
      $stages = ['Emailed' => $n['emailed'], 'Delivered' => $n['delivered']] + ($opens ? ['Opened (estimate)' => $n['opened']] : [])
          + ['Clicked a link' => $n['clicked'], 'Replied' => $n['replied'], 'Booked a demo' => $n['demo'], 'Signed up' => $n['signed_up']];
      $base = max(1, $n['emailed']);
      foreach ($stages as $label => $v): $pct = $v / $base; ?>
        <div class="meter"><span><?= e($label) ?></span><span class="bar" title="<?= e($label . ': ' . number_format($v)) ?>"><i style="width:<?= round(min(1, $pct) * 100, 2) ?>%"></i></span>
          <span class="fig"><b><?= number_format($v) ?></b> <small><?= $n['emailed'] ? sales_pct($pct) : '' ?></small></span></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <h2 style="margin-top:0">Over time <span class="muted small" style="font-weight:400">by <?= e($group) ?></span></h2>
    <div class="multiples">
      <?php foreach (['found' => ['Prospects found', 'prospects found'], 'sent' => ['Emails sent', 'emails sent'], 'clicked' => ['People who clicked', 'people clicked'],
                      'replied' => ['Replies', 'replies'], 'won' => ['Demos and sign-ups', 'demos and sign-ups']] as $k => [$title, $unit]):
          $vals = $d['series'][$k]; $sum = array_sum($vals); $peakKey = $sum ? array_search(max($vals), $vals, true) : null; ?>
        <div>
          <h3><?= e($title) ?></h3>
          <div class="chart-sum"><?= number_format($sum) ?> in total<?= $peakKey !== null ? ' · most: ' . e(preg_replace('/^Week of /', 'week of ', $d['buckets'][$peakKey])) . ' (' . number_format($vals[$peakKey]) . ')' : '' ?></div>
          <?= sales_columns($vals, $d['buckets'], $unit) ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <h2 style="margin-top:0">Where every prospect stands today</h2>
    <?php $mixTotal = max(1, array_sum($d['mix'])); $cols = ['var(--s1)', 'var(--s2)', 'var(--s3)', 'var(--s4)', 'var(--s5)']; ?>
    <p class="chart-sum"><?= number_format(array_sum($d['mix'])) ?> prospects in the list (all time, not only this period).</p>
    <div class="seg" role="img" aria-label="Prospects by stage">
      <?php $i = 0; foreach ($d['mix'] as $label => $v): if ($v > 0): ?><i style="flex:<?= $v ?>;background:<?= $cols[$i] ?>" title="<?= e($label . ': ' . number_format($v)) ?>"></i><?php endif; $i++; endforeach; ?>
    </div>
    <div class="legend">
      <?php $i = 0; foreach ($d['mix'] as $label => $v): ?>
        <div><span class="sw" style="background:<?= $cols[$i++] ?>"></span><?= e($label) ?>: <b><?= number_format($v) ?></b> <span class="muted">(<?= sales_pct($v / $mixTotal, 0) ?>)</span></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <h2 style="margin-top:0">Which businesses answer?</h2>
    <p class="chart-sum">Interested = replied, booked a demo or signed up. Put more effort into the types and states at the top of "Interested".</p>
    <?php foreach (['kinds' => ['Type of business', fn($k) => PROSPECT_KINDS[$k] ?? ($k ?: 'Other')], 'states' => ['State', fn($k) => $k ?: 'Unknown']] as $key => [$head, $name]): ?>
      <div class="table-wrap" style="margin-bottom:14px">
      <table class="table">
        <tr><th><?= e($head) ?></th><th class="num">Emailed</th><th class="num">Bounced</th><th class="num">Clicked</th><th class="num">Replied</th><th>Interested</th><th class="num">Signed up</th></tr>
        <?php foreach ($d[$key] as $g => $r): $s = max(1, $r['sent'] ?? 0); $int = ($r['interested'] ?? 0) / $s; ?>
          <tr class="small">
            <td><b><?= e($name((string) $g)) ?></b></td>
            <td class="num"><?= number_format($r['sent'] ?? 0) ?></td>
            <td class="num"><?= number_format($r['bounced'] ?? 0) ?></td>
            <td class="num"><?= number_format($r['clicked'] ?? 0) ?> <span class="muted"><?= sales_pct(($r['clicked'] ?? 0) / $s, 0) ?></span></td>
            <td class="num"><?= number_format($r['replied'] ?? 0) ?> <span class="muted"><?= sales_pct(($r['replied'] ?? 0) / $s, 0) ?></span></td>
            <td class="nowrap"><span class="mini"><i style="width:<?= round(min(1, $int) * 100, 1) ?>%"></i></span><?= number_format($r['interested'] ?? 0) ?> <span class="muted"><?= sales_pct($int, 0) ?></span></td>
            <td class="num"><?= number_format($r['signed_up'] ?? 0) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$d[$key]): ?><tr><td colspan="7" class="empty">No emails in this period.</td></tr><?php endif; ?>
      </table>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h2 style="margin-top:0">All sources</h2>
    <p class="chart-sum">Everything that came in during this period, from outreach and from the website, ads or word of mouth.</p>
    <div class="kpis" style="margin:0">
      <?= $tile('Demo requests', number_format((int) $d['leads']['all_n']), (int) $d['leads']['outreach'] . ' from outreach links', ['', '']) ?>
      <?= $tile('New sign-ups (trials)', number_format((int) $d['signups']['all_n']), (int) $d['signups']['outreach'] . ' from outreach links', ['', '']) ?>
      <?= $tile('Paying customers from outreach', number_format($d['paying']), 'all time, active subscriptions', ['', '']) ?>
    </div>
  </div>

  <p class="muted small">How it is counted: each step is recorded when it happens (finder, hourly send, inbox check for replies and bounces, link clicks, demo and sign-up forms, and statuses you set in Prospects).
    <?php if ($d['estimated']): ?>Send dates before <?= e((new DateTime((string) setting('sales_events_since'), new DateTimeZone('UTC')))->setTimezone(outreach_tz())->format('M j, Y')) ?> are estimated from the email schedule, and replies or sign-ups before then are dated at the last email.<?php endif; ?>
    Clicks count people, not clicks, and leave out link scanners that open every link right after delivery.</p>
</div>
<div id="viz-tip" role="tooltip"></div>
<script>
(function () {
  var tip = document.getElementById('viz-tip');
  document.querySelectorAll('.sales .hit').forEach(function (el) {
    el.addEventListener('mousemove', function (ev) { tip.textContent = el.getAttribute('data-tip'); tip.style.display = 'block';
      tip.style.left = Math.min(ev.clientX + 12, window.innerWidth - tip.offsetWidth - 8) + 'px'; tip.style.top = (ev.clientY - 34) + 'px'; });
    el.addEventListener('mouseleave', function () { tip.style.display = 'none'; });
  });
})();
</script>
