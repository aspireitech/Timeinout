<?php foreach (flashes() as [$type, $msg]): ?>
  <div class="flash <?= e($type) ?>"><?= icon($type === 'success' ? 'check' : 'shield', 18) ?> <?= e($msg) ?></div>
<?php endforeach; ?>
