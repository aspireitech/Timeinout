<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script>
  // Tell the server this device's time zone (used when the school's time zone is "Automatic")
  (function () {
    try {
      var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
      if (!tz) return;
      var had = (document.cookie.match(/(?:^|; )tio_tz=([^;]*)/) || [])[1];
      document.cookie = 'tio_tz=' + encodeURIComponent(tz) + '; path=/; max-age=31536000; samesite=lax';
      document.cookie = 'tio_tzo=' + (-new Date().getTimezoneOffset()) + '; path=/; max-age=31536000; samesite=lax';
      <?php if (($tt = tenant()) && !valid_tz($tt['timezone'] ?? '')): ?>
      // First visit from this device: reload once so "today" and the clock use its zone
      if (decodeURIComponent(had || '') !== tz && !sessionStorage.getItem('tio_tz_reloaded')) {
        sessionStorage.setItem('tio_tz_reloaded', '1');
        location.reload();
      }
      <?php endif; ?>
    } catch (e) {}
  })();
</script>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<?php if ($t = tenant()): ?>
<style>:root { --brand: <?= e(hex_color($t['primary_color'], '#6C5CE7')) ?>; --accent: <?= e(hex_color($t['accent_color'], '#00B894')) ?>; }</style>
<?php endif; ?>
