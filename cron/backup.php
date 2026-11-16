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
if (PHP_VERSION_ID < 80100) {
    echo 'This cron job runs PHP ' . PHP_VERSION . ', but ClockInOut needs PHP 8.1 or newer. Use a newer PHP path in the cron job.' . PHP_EOL;
    exit(1);
}
require __DIR__ . '/../app/bootstrap.php';
ini_set('memory_limit', '512M');
set_time_limit(0);

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
// Customer logos (small), so a move to new hosting needs only this folder plus config.php and app.key
$logos = PUBLIC_DIR . '/uploads/logos';
if (is_dir($logos) && class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open("$dir/uploads_logos.zip", ZipArchive::CREATE) === true) {
        $n = 0;
        foreach (glob($logos . '/*') ?: [] as $f) {
            if (is_file($f)) {
                $zip->addFile($f, basename($f));
                $n++;
            }
        }
        $zip->close();
        $log[] = sprintf('%-40s %8d files', 'uploads_logos.zip', $n);
    }
}
// Check every file opens, and record its size and SHA-256 so a damaged copy can be spotted after download
$sums = [];
foreach (glob("$dir/*") as $f) {
    $ok = true;
    if (str_ends_with($f, '.gz')) {
        $gz = @gzopen($f, 'rb');
        $ok = $gz && gzread($gz, 64) !== false;
        if ($gz) {
            gzclose($gz);
        }
    }
    if (!$ok) {
        $failed++;
        $log[] = basename($f) . ': FAILED, the file cannot be opened';
    }
    $sums[] = hash_file('sha256', $f) . '  ' . str_pad((string) filesize($f), 10, ' ', STR_PAD_LEFT) . '  ' . basename($f);
}
// Which database belongs to which portal (needed when restoring on a new server)
file_put_contents("$dir/MANIFEST.txt", "ClockInOut backup " . gmdate('Y-m-d H:i') . " UTC\n\n" . implode("\n", $log)
    . "\n\nRestore one database:  gunzip -c FILE.sql.gz | mysql -h HOST -u USER -p DATABASE   (or phpMyAdmin → Import)\n"
    . "Check a downloaded copy: sha256sum -c SHA256SUMS   (Windows: certutil -hashfile FILE SHA256)\n"
    . "Not in this folder (keep them in the password manager): app/config.php and app/keys/app.key\n");
file_put_contents("$dir/SHA256SUMS", implode("\n", array_map(fn($l) => preg_replace('/  +\d+  /', '  ', $l), $sums)) . "\n");

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
