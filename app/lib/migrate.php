<?php
// Automatic database upgrades. Each request does one cheap version check; new
// tables/columns are added once, so uploading a new zip is all an update needs.

const SCHEMA_VERSION = 12;

function setting(string $key, $default = null)
{
    if (!isset($GLOBALS['_settings'])) {
        $GLOBALS['_settings'] = array_column(rows('SELECT k, v FROM settings'), 'v', 'k');
    }
    return $GLOBALS['_settings'][$key] ?? $default;
}

function setting_set(string $key, ?string $value): void
{
    q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$key, $value]);
    unset($GLOBALS['_settings']);
}

function column_exists(string $table, string $column): bool
{
    return (bool) val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);
}

function migrate(): void
{
    try {
        $v = (int) val("SELECT v FROM settings WHERE k = 'schema_version'");
    } catch (PDOException $e) {
        $v = 0; // settings table not created yet
    }
    if ($v >= SCHEMA_VERSION) {
        return;
    }
    // Only one request should upgrade; others wait for the lock (max 30 s)
    if (!(int) val("SELECT GET_LOCK('tio_migrate', 30)")) {
        return;
    }
    try {
        $v = (int) (column_exists('settings', 'v') ? val("SELECT v FROM settings WHERE k = 'schema_version'") : 0);
        if ($v < 1) {
            migration_1();
        }
        if ($v < 2) {
            migration_2();
        }
        if ($v < 3) {
            migration_3();
        }
        if ($v < 4) {
            migration_4();
        }
        if ($v < 5) {
            migration_5();
        }
        if ($v < 6) {
            migration_6();
        }
        if ($v < 7) {
            migration_7();
        }
        if ($v < 8) {
            migration_8();
        }
        if ($v < 9) {
            migration_9();
        }
        if ($v < 10) {
            migration_10();
        }
        if ($v < 11) {
            migration_11();
        }
        if ($v < 12) {
            migration_12();
        }
        q("INSERT INTO settings (k, v) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [(string) SCHEMA_VERSION]);
    } finally {
        q("SELECT RELEASE_LOCK('tio_migrate')");
    }
}

/** Security tables, audit log, report schedule, room for encrypted contact details. */
function migration_1(): void
{
    q('CREATE TABLE IF NOT EXISTS settings (k VARCHAR(64) PRIMARY KEY, v TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    q("CREATE TABLE IF NOT EXISTS audit_log (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT UNSIGNED NULL,
        actor_type ENUM('user','provider','system') NOT NULL DEFAULT 'user',
        user_id INT UNSIGNED NULL,
        user_name VARCHAR(120) NULL,
        user_email VARCHAR(190) NULL,
        action VARCHAR(80) NOT NULL,
        target VARCHAR(255) NULL,
        details TEXT NULL,
        result ENUM('success','failed') NOT NULL,
        ip VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY ix_audit_tenant (tenant_id, created_at),
        KEY ix_audit_time (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    q("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        scope VARCHAR(40) NOT NULL,
        email VARCHAR(190) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        success TINYINT(1) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY ix_la_email (scope, email, created_at),
        KEY ix_la_ip (ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach ([
        ['tenants', 'device_timezone', 'VARCHAR(64) NULL AFTER timezone'],
        ['tenants', 'report_hour', 'TINYINT UNSIGNED NOT NULL DEFAULT 7'],
        ['tenants', 'report_weekday', 'TINYINT UNSIGNED NOT NULL DEFAULT 1'],
        ['tenants', 'report_attach', "VARCHAR(20) NOT NULL DEFAULT 'pdf,csv'"],
    ] as [$table, $col, $def]) {
        if (!column_exists($table, $col)) {
            q("ALTER TABLE $table ADD COLUMN $col $def");
        }
    }
    // Encrypted values are longer than the plain text
    q('ALTER TABLE guardians MODIFY phone VARCHAR(512) NULL, MODIFY email VARCHAR(512) NULL');
    q('ALTER TABLE teachers MODIFY phone VARCHAR(512) NULL, MODIFY email VARCHAR(512) NULL');
}

/** Encrypt contact details that were stored before encryption existed. */
function migration_2(): void
{
    foreach (['guardians', 'teachers'] as $table) {
        foreach (rows("SELECT id, phone, email FROM $table") as $r) {
            $phone = $r['phone'] !== null && !is_encrypted($r['phone']) ? encrypt_pii($r['phone']) : $r['phone'];
            $email = $r['email'] !== null && !is_encrypted($r['email']) ? encrypt_pii($r['email']) : $r['email'];
            if ($phone !== $r['phone'] || $email !== $r['email']) {
                q("UPDATE $table SET phone = ?, email = ? WHERE id = ?", [$phone, $email, $r['id']]);
            }
        }
    }
}

/** Industries, kiosk tiles, visitors, state/city addresses, Stripe & Wave billing. */
function migration_3(): void
{
    foreach ([
        ['industry', "VARCHAR(30) NOT NULL DEFAULT 'school'"],
        ['state', 'VARCHAR(4) NULL'],
        ['city', 'VARCHAR(40) NULL'],
        ['terms', 'TEXT NULL'],
        ['billing_provider', 'VARCHAR(10) NULL'],
        ['subscription_status', 'VARCHAR(20) NULL'],
        ['current_period_end', 'DATETIME NULL'],
        ['stripe_customer_id', 'VARCHAR(64) NULL'],
        ['stripe_subscription_id', 'VARCHAR(64) NULL'],
        ['wave_customer_id', 'VARCHAR(190) NULL'],
        ['wave_invoice_id', 'VARCHAR(190) NULL'],
    ] as [$col, $def]) {
        if (!column_exists('tenants', $col)) {
            q("ALTER TABLE tenants ADD COLUMN $col $def");
        }
    }
    q("CREATE TABLE IF NOT EXISTS kiosk_tiles (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT UNSIGNED NOT NULL,
        type ENUM('members','staff','pickup','visitor') NOT NULL,
        label VARCHAR(40) NOT NULL,
        subtitle VARCHAR(120) NULL,
        icon VARCHAR(20) NOT NULL DEFAULT 'users',
        color VARCHAR(10) NOT NULL DEFAULT 'violet',
        needs_contact TINYINT(1) NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        KEY ix_tiles_tenant (tenant_id, sort_order),
        CONSTRAINT fk_tiles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    q("CREATE TABLE IF NOT EXISTS payments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT UNSIGNED NULL,
        provider ENUM('stripe','wave','manual') NOT NULL,
        external_id VARCHAR(190) NULL,
        amount_cents INT NOT NULL DEFAULT 0,
        currency CHAR(3) NOT NULL DEFAULT 'USD',
        status ENUM('paid','open','failed','refunded') NOT NULL,
        description VARCHAR(255) NULL,
        invoice_url VARCHAR(500) NULL,
        period_start DATE NULL,
        period_end DATE NULL,
        paid_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY uq_payment (provider, external_id),
        KEY ix_pay_tenant (tenant_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    q("ALTER TABLE attendance MODIFY person_type ENUM('student','teacher','visitor') NOT NULL");
    if (!column_exists('attendance', 'tile_id')) {
        q('ALTER TABLE attendance ADD COLUMN tile_id INT UNSIGNED NULL AFTER person_type');
    }
    // Existing portals were schools: give them the school tiles they already had
    foreach (rows('SELECT id, industry FROM tenants') as $t) {
        if (!val('SELECT 1 FROM kiosk_tiles WHERE tenant_id = ?', [$t['id']])) {
            seed_tiles((int) $t['id'], $t['industry'] ?: 'school');
        }
    }
}

/** Dedicated databases per portal, and demo-request leads. */
function migration_4(): void
{
    foreach ([
        ['db_host', 'VARCHAR(190) NULL'],
        ['db_port', 'SMALLINT UNSIGNED NULL'],
        ['db_name', 'VARCHAR(64) NULL'],
        ['db_user', 'VARCHAR(64) NULL'],
        ['db_pass', 'VARCHAR(512) NULL'],
    ] as [$col, $def]) {
        if (!column_exists('tenants', $col)) {
            q("ALTER TABLE tenants ADD COLUMN $col $def");
        }
    }
    q("CREATE TABLE IF NOT EXISTS leads (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        company VARCHAR(160) NULL,
        phone VARCHAR(40) NULL,
        industry VARCHAR(30) NULL,
        employees VARCHAR(10) NULL,
        message TEXT NULL,
        status ENUM('new','contacted','qualified','won','lost') NOT NULL DEFAULT 'new',
        notes TEXT NULL,
        ip VARCHAR(45) NULL,
        emailed TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        KEY ix_leads_time (created_at),
        KEY ix_leads_status (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Deleted portals: closed at once, data kept for DELETE_GRACE_DAYS, then removed. */
function migration_5(): void
{
    if (!column_exists('tenants', 'deleted_at')) {
        q('ALTER TABLE tenants ADD COLUMN deleted_at DATETIME NULL');
    }
}

/** Logins by user ID (email optional for staff) and "must change password at first sign-in". */
function migration_6(): void
{
    if (!column_exists('users', 'username')) {
        q('ALTER TABLE users ADD COLUMN username VARCHAR(40) NULL AFTER email');
        q('ALTER TABLE users ADD UNIQUE KEY uq_user_username (tenant_id, username)');
    }
    if (!column_exists('users', 'must_change_password')) {
        q('ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0');
    }
    q('ALTER TABLE users MODIFY email VARCHAR(190) NULL');
}

/** Extra info from imported columns (encrypted JSON) and remembered column choices per portal. */
function migration_7(): void
{
    foreach (['students', 'teachers'] as $table) {
        if (!column_exists($table, 'extra')) {
            q("ALTER TABLE $table ADD COLUMN extra TEXT NULL");
        }
    }
    q("CREATE TABLE IF NOT EXISTS import_maps (
        tenant_id INT UNSIGNED NOT NULL,
        type VARCHAR(20) NOT NULL,
        mapping TEXT NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (tenant_id, type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Feature requests sent from customer portals (stored in the main database). */
function migration_8(): void
{
    q("CREATE TABLE IF NOT EXISTS feature_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT UNSIGNED NULL,
        portal VARCHAR(160) NULL,
        user_name VARCHAR(120) NULL,
        user_email VARCHAR(190) NULL,
        title VARCHAR(150) NOT NULL,
        category VARCHAR(20) NOT NULL,
        priority VARCHAR(20) NOT NULL,
        details TEXT NOT NULL,
        problem TEXT NULL,
        people VARCHAR(60) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'new',
        reply TEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        KEY ix_fr_tenant (tenant_id, created_at),
        KEY ix_fr_status (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Which campaign page or link a demo request came from (?src=…). */
function migration_9(): void
{
    if (!column_exists('leads', 'source')) {
        q('ALTER TABLE leads ADD COLUMN source VARCHAR(40) NULL AFTER message');
    }
}

/** V10: monthly or yearly billing per portal. */
function migration_10(): void
{
    if (!column_exists('tenants', 'billing_cycle')) {
        q("ALTER TABLE tenants ADD COLUMN billing_cycle VARCHAR(10) NOT NULL DEFAULT 'monthly' AFTER billing_provider");
    }
}

/** V11: kiosk may offer "Authorized relative" with a typed name. */
function migration_11(): void
{
    if (!column_exists('tenants', 'allow_typed_relative')) {
        q('ALTER TABLE tenants ADD COLUMN allow_typed_relative TINYINT(1) NOT NULL DEFAULT 1 AFTER allow_past_days');
    }
}

/** V12: where a self sign-up came from (?src=, e.g. linkedin-schools). */
function migration_12(): void
{
    if (!column_exists('tenants', 'source')) {
        q('ALTER TABLE tenants ADD COLUMN source VARCHAR(40) NULL AFTER industry');
    }
}
