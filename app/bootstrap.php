<?php
declare(strict_types=1);

define('APP_DIR', __DIR__);
define('ROOT_DIR', dirname(__DIR__));
define('PUBLIC_DIR', ROOT_DIR . '/public');

if (!is_file(APP_DIR . '/config.php')) {
    http_response_code(500);
    echo '<h1>Almost there</h1><p>Copy <code>app/config.sample.php</code> to <code>app/config.php</code>, '
       . 'enter your database details, and import <code>database/schema.sql</code>.</p>';
    exit;
}

$GLOBALS['config'] = require APP_DIR . '/config.php';

require APP_DIR . '/lib/helpers.php';
require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/tenant.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/mailer.php';
require APP_DIR . '/lib/reports.php';
require APP_DIR . '/lib/csv_import.php';
require APP_DIR . '/lib/demo.php';
require APP_DIR . '/lib/crypto.php';
require APP_DIR . '/lib/audit.php';
require APP_DIR . '/lib/migrate.php';
require APP_DIR . '/lib/pdf.php';

error_reporting(E_ALL);
ini_set('display_errors', cfg('debug') ? '1' : '0');
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
