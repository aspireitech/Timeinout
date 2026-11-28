<?php
// QR badges (free feature, Admin → Features & add-ons). Each student and staff member gets a personal,
// hard-to-guess code. The kiosk camera or a USB scanner reads it and opens the normal sign-in / sign-out
// flow for that person, so taps and scans mix freely in the same records.

const BADGE_PREFIX = 'CIO1-';
const BADGE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O or 1/I mix-ups

function badge_new_code(): string
{
    $s = '';
    for ($i = 0; $i < 12; $i++) {
        $s .= BADGE_ALPHABET[random_int(0, strlen(BADGE_ALPHABET) - 1)];
    }
    return BADGE_PREFIX . $s;
}

function badge_valid(string $code): bool
{
    return (bool) preg_match('/^CIO1-[' . BADGE_ALPHABET . ']{12}$/', $code);
}

/** Gives every active student and staff member of the current portal a badge code, if they have none yet. */
function badges_ensure(): int
{
    $made = 0;
    foreach (['students', 'teachers'] as $table) {
        foreach (rows("SELECT id FROM $table WHERE tenant_id = ? AND active = 1 AND (badge_code IS NULL OR badge_code = '')", [tid()]) as $r) {
            q("UPDATE $table SET badge_code = ? WHERE id = ? AND tenant_id = ?", [badge_new_code(), $r['id'], tid()]);
            $made++;
        }
    }
    return $made;
}

/** The active person with this badge in the current portal: ['type' => 'student'|'teacher', 'row' => [...]] or null. */
function badge_find(string $code): ?array
{
    if (!badge_valid($code)) {
        return null;
    }
    foreach (['student' => 'students', 'teacher' => 'teachers'] as $type => $table) {
        $r = row("SELECT * FROM $table WHERE tenant_id = ? AND badge_code = ? AND active = 1", [tid(), $code]);
        if ($r) {
            return ['type' => $type, 'row' => $r];
        }
    }
    return null;
}

/** Cancels a person's badge and gives a new one (lost card). Returns the new code. */
function badge_reissue(string $type, int $id): ?string
{
    $table = $type === 'teacher' ? 'teachers' : 'students';
    $code = badge_new_code();
    $n = q("UPDATE $table SET badge_code = ? WHERE id = ? AND tenant_id = ?", [$code, $id, tid()])->rowCount();
    return $n ? $code : null;
}

// ---------- Check-in method per group: 'both' (tap or scan), 'scan' (badge only), 'tap' (names only) ----------

const CHECKIN_MODES = ['both' => 'Tap name or scan badge', 'scan' => 'Scan badge only', 'tap' => 'Tap name only'];
const DESK_UNLOCK_SECONDS = 120;

/** Group of a kiosk tile or person type: 'members' (students / members) or 'staff'. */
function checkin_group(string $typeOrTile): string
{
    return in_array($typeOrTile, ['staff', 'teacher'], true) ? 'staff' : 'members';
}

function checkin_mode(string $group, ?array $t = null): string
{
    $t = $t ?? tenant();
    if (!feature_on('qr_badges', $t)) {
        return 'tap';
    }
    $modes = json_decode((string) ($t['checkin_modes'] ?? ''), true);
    $m = is_array($modes) ? (string) ($modes[$group] ?? 'both') : 'both';
    return isset(CHECKIN_MODES[$m]) ? $m : 'both';
}

/** The front desk entered its code for "Forgot my badge?" (valid for a short time, on this kiosk only). */
function desk_unlocked(string $group): bool
{
    return (int) ($_SESSION['desk_unlock'][tid()][$group] ?? 0) > time();
}

/** Remember that this person was just identified by their badge (the following sign-in/out is allowed in scan-only mode). */
function badge_pass_set(string $type, int $id): void
{
    $_SESSION['badge_pass'][tid()] = [$type, $id, time() + 300];
}

/** May this person be signed in or out now, given the group's check-in method? */
function checkin_allowed(string $type, int $id): bool
{
    $group = checkin_group($type);
    if (checkin_mode($group) !== 'scan' || desk_unlocked($group)) {
        return true;
    }
    [$pt, $pid, $exp] = $_SESSION['badge_pass'][tid()] ?? ['', 0, 0];
    return $pt === $type && (int) $pid === $id && $exp > time();
}

/** Personal badge page (shows the QR on a phone). */
function badge_link(array $t, string $code): string
{
    return tenant_url($t, '/badge?c=' . rawurlencode($code));
}
