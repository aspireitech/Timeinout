<?php
// Audit trail: every admin change, login, one-time code and report send, with
// who, when, from where and whether it worked. Shown in Admin → Audit log and,
// for all schools, in the provider console.

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/**
 * @param string      $action  short verb phrase, e.g. "Deleted student"
 * @param string|null $target  what it was done to, e.g. "Ava Johnson (#12)"
 * @param bool        $ok      success or failed
 * @param string|null $details extra facts, e.g. which fields changed (never passwords)
 * @param array|null  $actor   override who did it (used before the login completes)
 */
function audit(string $action, ?string $target, bool $ok, ?string $details = null, ?array $actor = null): void
{
    try {
        $t = tenant();
        $u = $actor ?? current_user();
        $provider = ($actor['provider'] ?? false) || (!$actor && !$t && !empty($_SESSION['super']));
        $system = !$u && !$provider && (PHP_SAPI === 'cli' || !empty($GLOBALS['audit_system']));
        insert('audit_log', [
            'tenant_id'  => $t['id'] ?? ($actor['tenant_id'] ?? null),
            'actor_type' => $provider ? 'provider' : ($system ? 'system' : 'user'),
            'user_id'    => $u['id'] ?? null,
            'user_name'  => mb_substr((string) ($u['name'] ?? ($provider ? 'Service provider' : ($system ? 'Scheduled job' : ''))), 0, 120) ?: null,
            'user_email' => mb_substr((string) ($u['email'] ?? ($provider ? (cfg('super_admin')['email'] ?? '') : '')), 0, 190) ?: null,
            'action'     => mb_substr($action, 0, 80),
            'target'     => $target !== null ? mb_substr($target, 0, 255) : null,
            'details'    => $details !== null ? mb_substr($details, 0, 4000) : null,
            'result'     => $ok ? 'success' : 'failed',
            'ip'         => PHP_SAPI === 'cli' ? null : client_ip(),
            'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            'created_at' => gmdate('Y-m-d H:i:s'), // stored in UTC, shown in local time
        ]);
    } catch (Throwable $e) {
        error_log('Audit log write failed: ' . $e->getMessage());
    }
}

/** Audit times are stored in UTC; show them in the school's (or provider's) time zone. */
function audit_time(string $utc, ?string $tz = null): string
{
    $d = new DateTime($utc, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone($tz ?? (tenant() ? tenant_tz() : 'UTC')));
    return $d->format('M j, Y g:i:s A');
}

/** "grade: Grade 2 → Grade 3; last_name: Kim → Park" (only the fields that changed). */
function audit_diff(array $before, array $after, array $skip = []): string
{
    $out = [];
    foreach ($after as $k => $v) {
        if (in_array($k, $skip, true) || !array_key_exists($k, $before)) {
            continue;
        }
        $old = (string) ($before[$k] ?? '');
        $new = (string) ($v ?? '');
        if ($old !== $new) {
            $out[] = str_replace('_', ' ', $k) . ': ' . ($old === '' ? '(empty)' : $old) . ' → ' . ($new === '' ? '(empty)' : $new);
        }
    }
    return $out ? implode('; ', $out) : 'No changes';
}

/**
 * Automatic audit for admin form posts: called by the router before the handler
 * runs; the entry is written when the request ends, as failed if the handler
 * showed an error message or crashed.
 */
function audit_request(string $action): void
{
    $GLOBALS['_audit'] = ['action' => $action, 'target' => null, 'details' => null, 'skip' => false];
    register_shutdown_function(function () {
        $a = $GLOBALS['_audit'] ?? null;
        if (!$a || $a['skip']) {
            return;
        }
        $failed = !empty($GLOBALS['_audit_error']);
        foreach ($_SESSION['flash'] ?? [] as [$type, $msg]) {
            if ($type === 'error') {
                $failed = true;
                $a['details'] = trim(($a['details'] ? $a['details'] . ' — ' : '') . $msg);
            }
        }
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $failed = true;
            $a['details'] = trim(($a['details'] ? $a['details'] . ' — ' : '') . 'Server error');
        }
        audit($a['action'], $a['target'], !$failed, $a['details']);
    });
}

/** Handlers call this to name what they changed: audit_note('Ava Johnson (#12)', 'grade: 2 → 3'). */
function audit_note(?string $target, ?string $details = null): void
{
    if (isset($GLOBALS['_audit'])) {
        $GLOBALS['_audit']['target'] = $target ?? $GLOBALS['_audit']['target'];
        $GLOBALS['_audit']['details'] = $details ?? $GLOBALS['_audit']['details'];
    }
}

/** For handlers that write their own, more specific entries. */
function audit_skip(): void
{
    if (isset($GLOBALS['_audit'])) {
        $GLOBALS['_audit']['skip'] = true;
    }
}

// ---------- Login protection that survives clearing cookies ----------
function login_blocked(string $scope, string $email): bool
{
    $email = strtolower(trim($email));
    $byEmail = (int) val('SELECT COUNT(*) FROM login_attempts WHERE scope = ? AND email = ? AND success = 0 AND created_at > NOW() - INTERVAL 15 MINUTE', [$scope, $email]);
    $byIp = (int) val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > NOW() - INTERVAL 15 MINUTE', [client_ip()]);
    return $byEmail >= 5 || $byIp >= 20;
}

function login_attempt(string $scope, string $email, bool $ok): void
{
    q('INSERT INTO login_attempts (scope, email, ip, success) VALUES (?, ?, ?, ?)', [$scope, strtolower(trim($email)), client_ip(), $ok ? 1 : 0]);
    if ($ok) { // a successful login clears earlier failures for this account
        q('DELETE FROM login_attempts WHERE scope = ? AND email = ? AND success = 0', [$scope, strtolower(trim($email))]);
    }
    if (mt_rand(1, 50) === 1) {
        q('DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 30 DAY');
    }
}
