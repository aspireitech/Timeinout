<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<?php if ($t = tenant()): ?>
<style>:root { --brand: <?= e(hex_color($t['primary_color'], '#6C5CE7')) ?>; --accent: <?= e(hex_color($t['accent_color'], '#00B894')) ?>; }</style>
<?php endif; ?>
