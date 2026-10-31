<?php
// Link previews (WhatsApp, iMessage, LinkedIn, Facebook, X, Slack, Teams). Main site only; customer portals stay private.
// Optional variables from the view: $metaTitle, $metaDesc, $shareImage (file in assets/img/og/).
if (tenant()) return;
$_ogBase = strtolower((string) cfg('base_domain'));
$_ogHost = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
// Only trust the Host header when it is our own domain, so previews never point elsewhere.
$_ogOrigin = 'https://' . $_ogBase; // one address for search engines (www and other hosts point here)
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
<?php $_ogPrivate = (bool) preg_match('#^/(super|login|check-address|report-activity)#', $_ogPath); ?>
<?php if ($_ogPrivate): ?><meta name="robots" content="noindex, nofollow">
<?php else: ?>
<?php if ($_gsv = setting('google_site_verification', '')): ?><meta name="google-site-verification" content="<?= e($_gsv) ?>">
<?php endif; ?>
<?php if ($_ga = setting('ga4_id', '')): ?><script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($_ga) ?>"></script>
<script>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date()); gtag('config', <?= json_encode($_ga) ?>);</script>
<?php endif; ?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        ['@type' => 'Organization', '@id' => $_ogOrigin . '/#org', 'name' => cfg('app_name'), 'url' => $_ogOrigin . '/', 'logo' => $_ogOrigin . base_path() . '/assets/img/apple-touch-icon.png'],
        ['@type' => 'WebSite', 'name' => cfg('app_name'), 'url' => $_ogOrigin . '/', 'publisher' => ['@id' => $_ogOrigin . '/#org']],
        ['@type' => 'SoftwareApplication', 'name' => cfg('app_name'), 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web, iPad, Android',
            'description' => $_ogDesc, 'url' => $_ogOrigin . '/', 'image' => $_ogImgUrl,
            'offers' => ['@type' => 'AggregateOffer', 'priceCurrency' => 'USD', 'lowPrice' => min(array_column(plans(), 'amount')), 'highPrice' => max(array_column(plans(), 'amount')), 'offerCount' => count(plans())],
            'publisher' => ['@id' => $_ogOrigin . '/#org']],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
