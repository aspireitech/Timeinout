<?php
// Last-resort reset of the provider (/super) password, for someone with server access.
//   php cron/super_password.php                → asks for a new password, stores it hashed in the database
//   php cron/super_password.php --recovery     → also prints 10 new recovery codes
if (PHP_SAPI !== 'cli') {
    exit('Run from the command line.');
}
require __DIR__ . '/../app/bootstrap.php';
fwrite(STDOUT, 'New provider password (14+ characters): ');
system('stty -echo 2>/dev/null');
$pass = trim((string) fgets(STDIN));
system('stty echo 2>/dev/null');
fwrite(STDOUT, PHP_EOL);
if ($p = super_password_problem($pass)) {
    fwrite(STDERR, $p . PHP_EOL);
    exit(1);
}
super_password_set($pass);
$GLOBALS['audit_system'] = true;
audit('Provider password reset from the server', cfg('super_admin')['email'] ?? null, true);
echo 'Password saved (hashed in the database).' . PHP_EOL;
if (in_array('--recovery', $argv, true)) {
    echo 'New recovery codes (each works once; old codes no longer work):' . PHP_EOL;
    foreach (super_recovery_generate() as $c) {
        echo '  ' . $c . PHP_EOL;
    }
}
