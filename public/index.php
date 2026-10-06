<?php
// Single entry point for every page. Static files are served directly by the
// web server (see .htaccess); everything else lands here.

if (PHP_SAPI === 'cli-server') { // `php -S` dev server: let it serve real files
    $f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($f)) {
        return false;
    }
}

require __DIR__ . '/../app/bootstrap.php';
require APP_DIR . '/routes.php';

// Work out the folder we're installed in and the path inside the app.
$uriPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
$base = '';
if ($scriptDir !== '' && ($uriPath === $scriptDir || str_starts_with($uriPath, $scriptDir . '/'))) {
    $base = $scriptDir;
} elseif (str_ends_with($scriptDir, '/public')) { // repo root used as web root, rewritten into /public
    $parent = substr($scriptDir, 0, -7);
    if ($parent !== '' && ($uriPath === $parent || str_starts_with($uriPath, $parent . '/'))) {
        $base = $parent;
    }
}
$path = substr($uriPath, strlen($base)) ?: '/';
if (str_starts_with($path, '/index.php')) {
    $path = substr($path, 10) ?: '/';
}
if ($path !== '/') {
    $path = rtrim($path, '/');
}
$GLOBALS['base_path'] = $base;

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

start_session();

try {
    $resolved = resolve_tenant($_SERVER['HTTP_HOST'] ?? '', $path);
} catch (PDOException $ex) {
    http_response_code(500);
    view('partials/error', [
        'code'    => 500,
        'message' => 'Cannot connect to the database. Check app/config.php and that database/schema.sql was imported.'
                   . (cfg('debug') ? ' (' . $ex->getMessage() . ')' : ''),
    ]);
}

$GLOBALS['request_path'] = $path;

if ($resolved === false) {
    not_found('There is no portal at this address. Check the spelling, or sign up for a new one.');
}

if ($resolved === null) {
    platform_routes($path);
} else {
    $GLOBALS['tenant'] = $resolved;
    if (in_array($resolved['status'], ['suspended', 'cancelled'], true)) {
        http_response_code(403);
        view('partials/error', ['code' => 403, 'message' => 'This portal is currently ' . $resolved['status'] . '. Please contact the administrator.']);
    }
    tenant_routes($path);
}
