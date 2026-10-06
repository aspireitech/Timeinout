<?php

function cfg(string $key, $default = null)
{
    return $GLOBALS['config'][$key] ?? $default;
}

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || cfg('force_https', false);
}

function scheme(): string
{
    return is_https() ? 'https' : 'http';
}

/** Folder the app is served from ('' when at the domain root). */
function base_path(): string
{
    return $GLOBALS['base_path'] ?? '';
}

/** Link inside the current site (tenant prefix included when in /s/<slug> mode). */
function url(string $path = '/'): string
{
    return base_path() . ($GLOBALS['tenant_prefix'] ?? '') . $path;
}

function asset(string $path): string
{
    $file = PUBLIC_DIR . '/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 1;
    return base_path() . '/assets/' . $path . '?v=' . $v;
}

function upload_url(?string $path): ?string
{
    return $path ? base_path() . '/' . ltrim($path, '/') : null;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function input(string $key, $default = '')
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

// ---------- Flash messages ----------
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------- Views ----------
function view(string $name, array $data = [], ?string $layout = null): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_DIR . '/views/' . $name . '.php';
    $content = ob_get_clean();
    if ($layout) {
        require APP_DIR . '/views/layouts/' . $layout . '.php';
    } else {
        echo $content;
    }
    exit;
}

function not_found(string $msg = 'Page not found'): void
{
    http_response_code(404);
    view('partials/error', ['code' => 404, 'message' => $msg]);
}

// ---------- Small utilities ----------
function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim(substr($s, 0, 30), '-');
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $i = mb_substr($parts[0] ?? '', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '');
    return mb_strtoupper($i);
}

function full_name(array $p): string
{
    return trim($p['first_name'] . ' ' . $p['last_name']);
}

function valid_date(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

function fmt_time(?string $t): string
{
    return $t ? date('g:i A', strtotime($t)) : '';
}

function fmt_date(?string $d): string
{
    return $d ? date('D, M j, Y', strtotime($d)) : '';
}

function kind_label(string $kind): string
{
    return ['sign_in' => 'Signed in', 'sign_out' => 'Signed out', 'material_pickup' => 'Material pickup'][$kind] ?? $kind;
}

function hex_color(string $c, string $fallback): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtoupper($c) : $fallback;
}

/** Small inline SVG icon set (stroke icons). */
function icon(string $name, int $size = 20): string
{
    $p = [
        'home'     => '<path d="M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3z"/>',
        'users'    => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6"/><path d="M16 4a4 4 0 0 1 0 8M22 21c0-3-2-5-4-5.5"/>',
        'teacher'  => '<circle cx="12" cy="7" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/>',
        'box'      => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        'upload'   => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
        'list'     => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/>',
        'chart'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'shield'   => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/>',
        'kiosk'    => '<rect x="4" y="3" width="16" height="12" rx="2"/><path d="M8 21h8M12 15v6"/>',
        'logout'   => '<path d="M15 4h4v16h-4M10 17l5-5-5-5M15 12H3"/>',
        'in'       => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M14 4h5v16h-5"/>',
        'out'      => '<path d="M14 7l5 5-5 5M19 12H7"/><path d="M10 4H5v16h5"/>',
        'check'    => '<path d="M4 12l5 5L20 6"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'back'     => '<path d="M15 18l-6-6 6-6"/>',
        'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'download' => '<path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 20h16"/>',
        'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    ][$name] ?? '';
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
