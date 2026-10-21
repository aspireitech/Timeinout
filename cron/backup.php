<?php
// Backs up the main database and every customer's own database to backups/<date-time>/,
// one .sql.gz file per database (import it with phpMyAdmin or: gunzip -c file | mysql db).
// Hostinger → Cron Jobs, once a day (or every few hours):
//   php /home/USER/domains/clockinoutapp.com/cron/backup.php
// Keeps the last 'backup_keep_days' days (config, default 14). Download the folder to
// another place regularly: a backup on the same server does not survive losing the server.
if (PHP_SAPI !== 'cli') {
    exit('Run from the command line.');
}
require __DIR__ . '/../app/bootstrap.php';
ini_set('memory_limit', '512M');
set_time_limit(0);

/** Writes one database as SQL (structure + data) into a gzip file. */
function dump_database(PDO $pdo, string $file): int
{
    $gz = gzopen($file, 'wb6');
    gzwrite($gz, "-- ClockInOut backup " . gmdate('Y-m-d H:i:s') . " UTC\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);
    $count = 0;
    foreach ($tables as [$table]) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
        gzwrite($gz, "DROP TABLE IF EXISTS `$table`;\n$create;\n");
        $st = $pdo->query("SELECT * FROM `$table`", PDO::FETCH_NUM);
        $batch = [];
        while ($r = $st->fetch(PDO::FETCH_NUM)) {
            $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)) . ')';
            if (count($batch) >= 200) {
                gzwrite($gz, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
                $count += count($batch);
                $batch = [];
            }
        }
        if ($batch) {
            gzwrite($gz, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
            $count += count($batch);
        }
        gzwrite($gz, "\n");
    }
    gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
    gzclose($gz);
    return $count;
}

pdb(); // stop here, before making a folder, when the main database is not reachable
$root = ROOT_DIR . '/backups';
if (!is_dir($root)) {
    mkdir($root, 0700, true);
}
file_put_contents($root . '/.htaccess', "Require all denied\n");
$dir = $root . '/' . gmdate('Y-m-d_Hi');
mkdir($dir, 0700, true);

$log = [];
$failed = 0;
$main = cfg('db')['name'];
$log[] = sprintf('%-40s %8d rows', "$main (main)", dump_database(pdb(), "$dir/main_$main.sql.gz"));
foreach (rows('SELECT * FROM tenants WHERE db_name IS NOT NULL ORDER BY slug') as $t) {
    try {
        $log[] = sprintf('%-40s %8d rows', "{$t['db_name']} ({$t['slug']})", dump_database(tenant_pdo($t), "$dir/{$t['slug']}_{$t['db_name']}.sql.gz"));
    } catch (Throwable $e) {
        $failed++;
        $log[] = "{$t['slug']}: FAILED " . $e->getMessage();
    }
}
// Which database belongs to which portal (needed when restoring on a new server)
file_put_contents("$dir/MANIFEST.txt", "ClockInOut backup " . gmdate('Y-m-d H:i') . " UTC\n\n" . implode("\n", $log) . "\n");

// Remove old backups
$keep = (int) cfg('backup_keep_days', 14);
foreach (glob($root . '/*', GLOB_ONLYDIR) as $old) {
    if (filemtime($old) < time() - $keep * 86400) {
        array_map('unlink', glob("$old/*"));
        rmdir($old);
    }
}
$GLOBALS['audit_system'] = true;
audit('Database backup', basename($dir), $failed === 0, count($log) . ' database(s)' . ($failed ? ", $failed failed" : ''));
echo implode(PHP_EOL, $log) . PHP_EOL . "Saved to $dir" . PHP_EOL;
exit($failed ? 1 : 0);
