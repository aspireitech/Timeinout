<?php
// Copy this file to app/config.php and fill in your values.
return [
    'app_name' => 'TimeInOut',
    'debug'    => false,              // true shows PHP errors — never on a live site

    // Database (create it in cPanel → MySQL Databases, then import database/schema.sql)
    'db' => [
        'host' => 'localhost',
        'name' => 'timeinout',
        'user' => 'db_user',
        'pass' => 'db_password',
        'port' => 3306,
    ],

    // Your main product domain, without www. Subscribers get <name>.<base_domain>
    'base_domain' => 'abc.com',

    // How links to a subscriber portal are built:
    //   'subdomain' → https://xyz.abc.com   (needs a wildcard DNS record *.abc.com + wildcard subdomain in hosting)
    //   'path'      → https://abc.com/s/xyz (works on any hosting, good for first tests)
    // Both styles are always accepted on incoming requests.
    'tenant_url_mode' => 'path',

    // Force https in generated links (recommended on a live site with SSL)
    'force_https' => false,

    // Platform owner (you). Log in at https://abc.com/super
    // Password may be plain text or a password_hash() value (starts with $2y$).
    'super_admin' => [
        'email'    => 'owner@abc.com',
        'password' => 'change-me-now',
    ],

    // New subscribers start on a free trial of this many days
    'trial_days' => 30,

    // Stripe and Wave keys are entered in the provider console: /super → Settings.

    // Plans shown on the pricing page (display only)
    'plans' => [
        'starter' => ['name' => 'Starter',    'price' => '$19/mo', 'amount' => 19, 'limit' => 'Up to 50 people'],
        'school'  => ['name' => 'Business',   'price' => '$49/mo', 'amount' => 49, 'limit' => 'Up to 500 people'],
        'campus'  => ['name' => 'Enterprise', 'price' => '$99/mo', 'amount' => 99, 'limit' => 'Unlimited people'],
    ],

    // Email for reports. 'mail' uses PHP mail(); 'smtp' talks to your mail server directly.
    'mail' => [
        'driver'     => 'mail',          // 'mail' or 'smtp'
        'from_email' => 'no-reply@abc.com',
        'from_name'  => 'TimeInOut Reports',
        'host'       => 'mail.abc.com',
        'port'       => 587,
        'secure'     => 'tls',           // 'tls', 'ssl' or ''
        'user'       => '',
        'pass'       => '',
    ],

    // Reports are emailed when the cron job runs at or after this hour (subscriber's timezone).
    'report_hour' => 6,

    // Secret for the web cron URL https://abc.com/cron?key=... (for hosts without cron jobs)
    'cron_key' => 'replace-with-a-long-random-string',
];
