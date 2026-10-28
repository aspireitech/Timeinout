<?php
// Link previews (WhatsApp, iMessage, LinkedIn, Facebook, X, Slack, Teams). Main site only; customer portals stay private.
// Optional variables from the view: $metaTitle, $metaDesc, $shareImage (file in assets/img/og/).
if (tenant()) return;
$_ogBase = strtolower((string) cfg('base_domain'));
$_ogHost = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
// Only trust the Host header when it is our own domain, so previews never point elsewhere.
$_ogOrigin = 'https://' . (($_ogHost === $_ogBase || str_ends_with($_ogHost, '.' . $_ogBase)) ? $_ogHost : $_ogBase);
$_ogPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$_ogTitle = $metaTitle ?? cfg('app_name') . ': time & attendance for any front door';
$_ogDesc = $metaDesc ?? 'Sign-in and sign-out kiosk for students, staff, members and visitors. Live attendance, safe pickups and reports emailed at 7:00.';
$_ogImg = $shareImage ?? 'og-default.jpg';
$_ogImgUrl = $_ogOrigin . base_path() . '/assets/img/og/' . $_ogImg;
?>
<link rel="canonical" href="<?= e($_ogOrigin . $_ogPath) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(cfg('app_name')) ?>">
<meta property="og:title" content="<?= e($_ogTitle) ?>">
<meta property="og:description" content="<?= e($_ogDesc) ?>">
<meta property="og:url" content="<?= e($_ogOrigin . $_ogPath) ?>">
<meta property="og:image" content="<?= e($_ogImgUrl) ?>">
<meta property="og:image:secure_url" content="<?= e($_ogImgUrl) ?>">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= e(cfg('app_name')) ?> logo and the check-in kiosk on a tablet">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($_ogTitle) ?>">
<meta name="twitter:description" content="<?= e($_ogDesc) ?>">
<meta name="twitter:image" content="<?= e($_ogImgUrl) ?>">
<meta name="theme-color" content="#161B36">
