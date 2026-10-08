<?php
// Builds (or rebuilds) the demo portal "Bright Future Academy" at /s/demo.
// Usage: php database/seed_demo.php      (or use "Load demo portal" in /super)
if (PHP_SAPI !== 'cli') {
    exit('Run from the command line.');
}
require __DIR__ . '/../app/bootstrap.php';
$r = seed_demo_tenant();
echo "Demo portal ready: {$r['students']} students, {$r['teachers']} teachers, {$r['events']} check-ins.\n";
foreach (DEMO_LOGINS as [$role, , $email, $pass]) {
    echo str_pad(ucfirst($role), 7) . " $email / $pass\n";
}
