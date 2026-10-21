<?php
// Copy this file to app/config.php and fill in your values.
// app/config.php is never committed to git, so every server (dev, live) keeps its own copy:
// pushing new code never changes base_domain, tenant_url_mode, force_https, keys or passwords.
return [
    'app_name' => 'ClockInOut',
    'debug'    => false,              // true shows PHP errors — never on a live site

    // Main (platform) database: the customer list, settings, payments and leads.
    // Customers created in /super → New portal can each get their own database as well.
    'db' => [
        'host' => 'localhost',
        'name' => 'u123456789_clockinout',
        'user' => 'u123456789_clockinout',
        'pass' => 'db_password',
        'port' => 3306,
    ],

    // Your main product domain, without www. Customers get <name>.<base_domain>
    //   live: 'clockinoutapp.com'      dev: 'dev.clockinoutapp.com'
    'base_domain' => 'clockinoutapp.com',

    // How links to a customer portal are built:
    //   'subdomain' → https://utsjkumon.clockinoutapp.com  (needs DNS *.clockinoutapp.com; use on the live site)
    //   'path'      → https://dev.clockinoutapp.com/s/utsjkumon (works anywhere; use on dev)
    // Both styles are always accepted on incoming requests.
    'tenant_url_mode' => 'subdomain',

    // Always use https (the site must have SSL, e.g. Cloudflare "Full (strict)")
    'force_https' => true,

    // Platform owner (you). Log in at https://clockinoutapp.com/super
    // Password may be plain text or a password_hash() value (starts with $2y$) — prefer the hash:
    //   php -r "echo password_hash('your-long-password', PASSWORD_DEFAULT), PHP_EOL;"
    'super_admin' => [
        'email'    => 'aspireitechsolutions@gmail.com',
        'password' => 'change-me-now',
    ],

    // Every /super sign-in (password or Google) is confirmed by a code emailed to super_admin.
    // Set to false ONLY while email is broken, then set it back.
    'super_otp' => true,

    // Sign in with Google for /super (optional; the password login always stays available).
    // Google Cloud Console → APIs & Services → Credentials → OAuth client ID (Web application).
    // Authorized redirect URI: https://clockinoutapp.com/super/google/callback
    'google_oauth' => [
        'client_id'      => '',
        'client_secret'  => '',
        'allowed_emails' => [],          // extra Google accounts allowed besides super_admin email
    ],

    // Demo requests from /demo are emailed here and saved under /super → Leads
    'lead_email' => 'contact@clockinoutapp.com',

    // Field encryption key for phone numbers, emails and saved passwords. Leave empty to
    // generate app/keys/app.key automatically. BACK IT UP (password manager): without it
    // encrypted data cannot be read after moving or restoring the site.
    'app_key' => '',

    // New self-service sign-ups start on a free trial of this many days
    'trial_days' => 7,

    // Stripe and Wave keys are entered in the provider console: /super → Settings.

    // Plans shown on the pricing page
    'plans' => [
        'starter' => ['name' => 'Starter',    'price' => '$19/mo', 'amount' => 19, 'limit' => 'Up to 50 people'],
        'school'  => ['name' => 'Business',   'price' => '$49/mo', 'amount' => 49, 'limit' => 'Up to 500 people'],
        'campus'  => ['name' => 'Enterprise', 'price' => '$99/mo', 'amount' => 99, 'limit' => 'Unlimited people'],
    ],

    // Email server. 'smtp' is recommended (Hostinger: smtp.hostinger.com, port 465, secure 'ssl').
    // Server settings can also be changed in /super → Settings, which wins over this file.
    'mail' => [
        'driver'     => 'smtp',          // 'mail' or 'smtp'
        'host'       => 'smtp.hostinger.com',
        'port'       => 465,
        'secure'     => 'ssl',           // 'tls', 'ssl' or ''
        'from_name'  => 'ClockInOut',
    ],

    // The two mailboxes mail is sent from. Each signs in to SMTP with its own password.
    //   contact  sign-in codes, account notices, leads, suspicious-activity reports
    //   reports  daily / weekly / monthly / on-demand reports (replies go to contact)
    'mail_senders' => [
        'contact' => ['from_email' => 'contact@clockinoutapp.com', 'from_name' => 'ClockInOut',
                      'user' => 'contact@clockinoutapp.com', 'pass' => ''],
        'reports' => ['from_email' => 'reports@clockinoutapp.com', 'from_name' => 'ClockInOut Reports',
                      'user' => 'reports@clockinoutapp.com', 'pass' => ''],
    ],

    // Receives leads, suspicious-activity reports and a hidden copy (BCC) of every report.
    'contact_email' => 'contact@clockinoutapp.com',
    // BCC on every emailed report; set to false to stop the copies.
    'report_bcc' => 'contact@clockinoutapp.com',

    // Secret for the web cron URL https://clockinoutapp.com/cron?key=... (for hosts without cron jobs)
    'cron_key' => 'replace-with-a-long-random-string',
];
