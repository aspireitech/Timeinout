<?php
// Thin PDO wrapper. Every query uses bound parameters.
//
// Two kinds of database:
//   platform  the main database in config.php: the subscriber list (tenants), provider
//             settings, payments and leads. Always used by the main site and /super.
//   tenant    an optional separate database for one subscriber (tenants.db_name set).
//             While a dedicated portal is open, its people, attendance, users and audit
//             log are read from and written to that database only. Queries that name a
//             platform table (see PLATFORM_TABLES) still go to the platform database.

const PLATFORM_TABLES = ['tenants', 'settings', 'payments', 'leads', 'feature_requests'];

function pdo_connect(array $c): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'] ?: 'localhost', (int) ($c['port'] ?? 3306) ?: 3306, $c['name']);
    return new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 10,
    ]);
}

/** The main (platform) database. */
function pdb(): PDO
{
    static $pdo = null;
    return $pdo ??= pdo_connect(cfg('db'));
}

/** The database the current request works in: a dedicated portal's own, or the platform one. */
function db(): PDO
{
    return $GLOBALS['db_current'] ?? pdb();
}

/** Connection details of a dedicated portal, or null when it lives in the platform database. */
function tenant_db_config(array $t): ?array
{
    if (empty($t['db_name'])) {
        return null;
    }
    return ['host' => $t['db_host'] ?: 'localhost', 'port' => (int) ($t['db_port'] ?: 3306), 'name' => $t['db_name'],
            'user' => (string) $t['db_user'], 'pass' => (string) (decrypt_pii((string) $t['db_pass']) ?? '')];
}

function tenant_pdo(array $t): ?PDO
{
    static $pool = [];
    $c = tenant_db_config($t);
    if (!$c) {
        return null;
    }
    $key = $c['host'] . ':' . $c['port'] . '/' . $c['name'];
    return $pool[$key] ??= pdo_connect($c);
}

/** Switch every following query to this portal's database (no-op for shared portals). */
function use_tenant_db(?array $t): void
{
    $GLOBALS['db_current'] = $t ? tenant_pdo($t) : null;
}

/** Run $fn inside a portal's database, then switch back. */
function with_tenant_db(array $t, callable $fn)
{
    $prev = $GLOBALS['db_current'] ?? null;
    use_tenant_db($t);
    try {
        return $fn();
    } finally {
        $GLOBALS['db_current'] = $prev;
    }
}

/** Run $fn with every query sent to the current connection, platform tables included (schema setup). */
function without_routing(callable $fn)
{
    $prev = $GLOBALS['db_no_route'] ?? false;
    $GLOBALS['db_no_route'] = true;
    try {
        return $fn();
    } finally {
        $GLOBALS['db_no_route'] = $prev;
    }
}

/** Which connection a statement belongs to. */
function conn_for(string $sql): PDO
{
    $cur = db();
    if ($cur === pdb() || !empty($GLOBALS['db_no_route'])) {
        return $cur;
    }
    return preg_match('/\b(' . implode('|', PLATFORM_TABLES) . ')\b/i', $sql) ? pdb() : $cur;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = conn_for($sql)->prepare($sql);
    $st->execute($params);
    return $st;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(',', $cols),
        implode(',', array_fill(0, count($cols), '?'))
    );
    $pdo = conn_for($sql);
    $pdo->prepare($sql)->execute(array_values($data));
    return (int) $pdo->lastInsertId();
}

function update(string $table, array $data, string $where, array $whereParams): void
{
    $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
    q("UPDATE $table SET $set WHERE $where", array_merge(array_values($data), $whereParams));
}

/**
 * Prepare an empty dedicated database: create every table, upgrade it to the current
 * schema, and store a copy of the portal's row so the foreign keys hold.
 */
function tenant_db_install(array $t): void
{
    with_tenant_db($t, function () use ($t) {
        without_routing(function () use ($t) {
            $sql = (string) file_get_contents(ROOT_DIR . '/database/schema.sql');
            $sql = preg_replace('/--\s.*$/m', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                db()->exec($stmt);
            }
            migrate();
            $copy = row('SELECT * FROM tenants WHERE id = ?', [$t['id']]) ? null : $t;
            if ($copy) {
                $cols = array_intersect(array_keys($copy), array_column(rows('SHOW COLUMNS FROM tenants'), 'Field'));
                $data = array_intersect_key($copy, array_flip($cols));
                unset($data['db_pass']); // credentials stay in the platform database only
                $data = array_diff_key($data, array_flip(['db_name', 'db_host', 'db_port', 'db_user']));
                insert('tenants', $data);
            }
        });
    });
}

/** Keep a dedicated database's schema current (cheap version check on each request). */
function tenant_db_migrate(array $t): void
{
    if (tenant_db_config($t)) {
        with_tenant_db($t, fn() => without_routing('migrate'));
    }
}
