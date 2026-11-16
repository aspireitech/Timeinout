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

/** Personal badge page (shows the QR on a phone). */
function badge_link(array $t, string $code): string
{
    return tenant_url($t, '/badge?c=' . rawurlencode($code));
}
