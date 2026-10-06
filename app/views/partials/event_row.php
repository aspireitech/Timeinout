<?php $cls = ['sign_in' => 'in', 'sign_out' => 'out', 'material_pickup' => 'pickup'][$r['kind']]; ?>
<div class="person" style="padding:8px 0;border-bottom:1px solid var(--line)">
  <span class="avatar <?= $r['kind'] === 'material_pickup' ? 'material' : ($r['person_type'] === 'teacher' ? 'teacher' : '') ?>"><?= e(initials($r['person_name'])) ?></span>
  <div style="flex:1;min-width:0">
    <b><?= e($r['person_name']) ?></b>
    <div class="muted small"><?= e(date('M j', strtotime($r['event_date']))) ?> · <?= e(fmt_time($r['event_time'])) ?><?= $r['guardian_name'] ? ' · ' . e($r['guardian_name']) : '' ?><?= $r['materials'] ? ' · ' . e($r['materials']) : '' ?></div>
  </div>
  <span class="badge <?= $cls ?>"><?= e(kind_label($r['kind'])) ?></span>
</div>
