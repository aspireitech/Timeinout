<?php
// "What's new" content: recent releases and the roadmap. Edit these lists to publish news;
// they appear on the public page (/whats-new) and in every admin portal.

/** Shipped features, newest first: [date (Y-m-d), title, description, icon, tag]. */
const WHATS_NEW = [
    ['2026-10-30', '"Authorized relative" at the kiosk', 'Besides the parents and guardians on file, the kiosk always offers "Authorized relative": the person types their name and relationship, and it is saved with the entry. Turn it off in Branding & settings.', 'users', 'Kiosk'],
    ['2026-10-28', 'Request a feature from your portal', 'Send us your ideas from Admin → What\'s new, with the details that matter to you, and follow each request\'s status and our reply.', 'spark', 'Product'],
    ['2026-10-28', 'Email through Microsoft 365', 'Sign-in codes, reports and notices are delivered through Microsoft 365 for reliable, on-brand email from our own domain.', 'mail', 'Reliability'],
    ['2026-10-26', '"Self" check-in for adults', 'Adult learners and members with no guardian on file can sign themselves in and out. Children still need a listed guardian.', 'users', 'Kiosk'],
    ['2026-10-26', 'Import any spreadsheet', 'Upload Excel or CSV in your own layout. Match each column in one screen; extra columns are kept as encrypted extra info on each person.', 'upload', 'Import'],
    ['2026-10-23', 'Staff sign in with a user ID', 'Front-desk staff can sign in with a simple user ID like "frontdesk". New logins can get emailed details and must choose their own password.', 'shield', 'Security'],
    ['2026-10-21', 'Your own private database', 'Each organization\'s people, attendance and logs are stored in a separate database, apart from every other customer.', 'shield', 'Security'],
    ['2026-10-21', 'Reports for any date range', 'Pick any range up to six months: view on screen, download PDF or CSV, or email it to several people at once.', 'chart', 'Reports'],
    ['2026-10-21', 'Sign-in codes and "report it" link', 'Every admin sign-in is confirmed with an emailed code. If you did not ask for one, one click tells our team.', 'mail', 'Security'],
    ['2026-10-21', 'Daily backups', 'All data is backed up every day and kept for two weeks, so it can be restored quickly if ever needed.', 'clock', 'Reliability'],
];

/** Roadmap: [title, description, icon, stage]. Stage: 'building', 'next' or 'exploring'. */
const ROADMAP = [
    ['Missed check-out reminders', 'Forgot to sign out yesterday? An email from your organization the next morning reminds staff, employees and guardians who have an email on file, so signing out becomes a habit and your hours stay accurate.', 'mail', 'building'],
    ['Support center in your portal', 'Open a support case, follow the conversation, reply and escalate, all inside your portal, with every update emailed to you.', 'help', 'building'],
    ['Online plans and billing', 'Choose a plan, add support or device care, and pay by card, all inside your portal.', 'card', 'building'],
    ['Admin roles and permissions', 'Give each admin the right access: Super admin (everything), Billing admin, Report admin (view and create reports) and Helpdesk (view only and raise support cases).', 'shield', 'next'],
    ['Sign in with Google or Microsoft', 'Admins sign in with their Google Workspace or Microsoft Entra account, still confirmed with a second step.', 'users', 'next'],
    ['Text message alerts', 'Optional SMS to parents or managers when someone checks in or out, or is picked up late.', 'mail', 'next'],
    ['QR code and badge check-in', 'Scan a personal QR code or badge at the kiosk instead of typing a name.', 'badge', 'next'],
    ['Late pickup and no-show alerts', 'Automatic warnings when a child has not been picked up, or someone has not arrived, by a time you choose.', 'clock', 'next'],
    ['Hours export for payroll', 'Staff hours by pay period, ready for your payroll or accounting software.', 'chart', 'next'],
    ['Push approval sign-in', 'Approve an admin sign-in with one tap on your phone instead of typing an emailed code.', 'shield', 'exploring'],
    ['Single sign-on (SSO)', 'Enterprise single sign-on with your identity provider (SAML or OpenID Connect), with automatic user setup.', 'globe', 'exploring'],
    ['Spanish kiosk', 'Let each family or employee use the kiosk in English or Spanish.', 'globe', 'exploring'],
    ['Photo at check-in', 'Optionally take a photo at pickup for an extra layer of safety.', 'users', 'exploring'],
    ['Multiple locations', 'Run several sites from one account, with reports per site and for the whole organization.', 'grid', 'exploring'],
];

const ROADMAP_STAGES = ['building' => 'In progress', 'next' => 'Coming next', 'exploring' => 'Exploring'];

const FEATURE_CATEGORIES = ['kiosk' => 'Kiosk & check-in', 'reports' => 'Reports & exports', 'people' => 'People & import',
    'notifications' => 'Email & notifications', 'security' => 'Security & logins', 'billing' => 'Plans & billing', 'other' => 'Something else'];

const FEATURE_PRIORITIES = ['nice' => 'Nice to have', 'important' => 'Important for us', 'critical' => 'We cannot work without it'];

const FEATURE_STATUSES = ['new' => 'Received', 'reviewing' => 'Under review', 'planned' => 'Planned', 'building' => 'In progress',
    'shipped' => 'Released', 'declined' => 'Not planned'];
