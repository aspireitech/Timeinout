<!doctype html>
<html lang="en">
<head>
<?php require APP_DIR . '/views/partials/head.php'; ?>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<title><?= e(tenant()['name']) ?> · Check-in</title>
</head>
<body class="kiosk-body">
<?= $content ?>
<script src="<?= asset('js/kiosk.js') ?>"></script>
</body>
</html>
