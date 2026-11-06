<?php
$kiosk = tenant_url($t, '/');
$login = tenant_url($t, '/login');
$a1 = strtolower(term('a1')); $a2 = strtolower(term('a2')); $b2 = strtolower(term('b2'));
$link = fn(string $path, string $label) => '<a href="' . e(url($path)) . '">' . e($label) . '</a>';
// [section, icon, [[question, answer HTML], …]]
$faq = [
    ['Getting started', 'home', [
        ['How do I sign in?',
         '<p>Go to <a href="' . e($login) . '">' . e(preg_replace('#^https?://#', '', $login)) . '</a> and enter your <b>email or user ID</b> and password.</p>
          <p>Admins then receive a <b>6-digit code by email</b>. Enter it to finish signing in. The code expires after 10 minutes; use <b>Send a new code</b> if it does not arrive (check your spam folder too).</p>
          <p>For your safety you are signed out after 30 minutes without activity in the admin area.</p>'],
        ['I received a sign-in code I did not ask for. What should I do?',
         '<p>Do not use the code and <b>change your password</b> right away (' . $link('/admin/users', 'Users') . ' → Change my password). The email has a <b>"report it to us"</b> link: press the button on that page and our team is told immediately. Without the code nobody can finish signing in.</p>'],
        ['I forgot my password.',
         '<p>Ask another admin in your organization to set a new one (' . $link('/admin/users', 'Users') . ' → New password). If you are the owner, contact us and we will send you a temporary password.</p>'],
        ['What is the difference between the admin area and the kiosk?',
         '<p>The <b>admin area</b> (where you are now) is for managing people, settings and reports. The <b>kiosk</b> is the simple screen at your front door where ' . e($a2) . ', families and ' . e($b2) . ' sign in and out.</p>'],
    ]],
    ['The kiosk', 'kiosk', [
        ['Where is my kiosk?',
         '<p>Your kiosk address is <a href="' . e($kiosk) . '" target="_blank">' . e(preg_replace('#^https?://#', '', $kiosk)) . '</a>. You can also click <b>Open kiosk</b> in the menu on the left.</p>'],
        ['How do I set up a tablet at the front door?',
         '<ol><li>On the tablet, open the kiosk address in Chrome or Safari.</li>
          <li>Sign in with a <b>staff login</b> (see "How do I create a staff login?"). Staff logins can only open the kiosk, never the admin area, and stay signed in through the school or work day.</li>
          <li>Add the page to the home screen (Share → Add to Home Screen) so it opens full screen.</li>
          <li>Keep the tablet charging and turn on guided access / screen pinning so it stays on the kiosk.</li></ol>'],
        ['How does a parent check a ' . e($a1) . ' in or out?',
         '<ol><li>Tap the ' . e(term('a2')) . ' button, then type the first few letters of the name.</li>
          <li>Tap the ' . e($a1) . ', then tap <b>who is with them</b>: only the parents, guardians and authorized relatives on file are shown.</li>
          <li>Tap <b>Sign in</b> or <b>Sign out</b>. The time is recorded automatically.</li></ol>
          <p>A person who is already signed in can only be signed out, and the other way round, so double entries are not possible.</p>'],
        ['What is the "Self" option?',
         '<p>When a ' . e($a1) . ' has <b>nobody on file</b> (for example an adult learner), the kiosk offers <b>Self</b> instead of a guardian, and the log shows "Self". As soon as a guardian is added, Self disappears and only the listed people can be chosen. Check that children always have at least one guardian on file.</p>'],
        ['Can someone record a check-in for an earlier day?',
         '<p>Yes, with <b>Other day</b> at the top of the kiosk, within the number of days set under ' . $link('/admin/settings', 'Branding & settings') . ' → <b>Allow entries for past days</b>. Set it to 0 to allow today only.</p>'],
        ['How do I change the kiosk buttons?',
         '<p>Go to ' . $link('/admin/tiles', 'Kiosk tiles') . '. You can rename buttons, change their color and icon, hide them, reorder them and choose what each one does (for example ' . e($a2) . ' with a guardian, staff clock-in, visitors or item pickup).</p>'],
    ]],
    ['Users and logins', 'shield', [
        ['What kinds of logins are there?',
         '<ul><li><b>Owner</b>: the person who manages the account. Cannot be removed.</li>
          <li><b>Admin</b>: full access to the admin area. Signs in with an email address and an emailed code.</li>
          <li><b>Staff</b>: can only open the kiosk. Can sign in with a simple <b>user ID</b>; email is optional.</li></ul>'],
        ['How do I create a staff login?',
         '<ol><li>Go to ' . $link('/admin/users', 'Users') . ' → <b>Add a user</b>.</li>
          <li>Enter the name, choose <b>Staff</b>, and type a <b>user ID</b> such as <code>frontdesk</code> or <code>maria.k</code> (3–40 letters, numbers, dots or dashes).</li>
          <li>Add an email if you want the sign-in details sent to them.</li>
          <li>Type a password, or leave it empty and a secure one is generated.</li>
          <li>Click <b>Add user</b>.</li></ol>'],
        ['What does "Must change password at first sign-in" do?',
         '<p><b>Ticked:</b> the password you set is a <b>temporary password</b>. The first time the person signs in, they must choose their own password before they can use the kiosk or admin area, and the email says "Temporary password".</p>
          <p><b>Not ticked:</b> the password you set is their real password and they can keep using it. The email says "Password" and asks them to keep it private.</p>'],
        ['What does "Email the sign-in details" do?',
         '<p>It sends the person a professional welcome email with the sign-in page link, their user ID or email, and the password. If there is no email address on the login, or the email cannot be sent, the password is <b>shown to you once</b> on screen so you can hand it over yourself.</p>'],
        ['How do I reset someone\'s password?',
         '<p>' . $link('/admin/users', 'Users') . ' → <b>New password</b> next to the person. The same two options apply: require a change at next sign-in, and email it to them.</p>'],
        ['How do staff change their own password?',
         '<p>At the bottom of the kiosk, tap <b>Change password</b>. They can also add or update their email address there.</p>'],
    ]],
    ['Importing your people', 'upload', [
        ['How do I upload my ' . e($a2) . ' and families?',
         '<ol><li>Go to ' . $link('/admin/import', 'Import') . ' and choose your Excel (<b>.xlsx</b>) or <b>CSV</b> file under ' . e(term('a2')) . '.</li>
          <li>On <b>Match your columns</b>, check what each column is used for. We pre-select what we recognise and remember your choices for next time.</li>
          <li>Click <b>Import</b>. The message tells you how many were added or updated.</li></ol>
          <p>Download a ready-made example: ' . $link('/admin/import/sample-students.csv', 'students sample file') . ' · ' . $link('/admin/import/sample-teachers.csv', 'staff sample file') . '.</p>'],
        ['Which columns should my file have?',
         '<p>The first row must be the column headers. A typical file:</p>
          <div class="table-wrap"><table class="table small"><tr><th>Student ID</th><th>First Name</th><th>Last Name</th><th>Grade</th><th>Guardian (Father)</th><th>Guardian (Mother)</th><th>Authorized Relative</th></tr>
          <tr><td>S101</td><td>Olivia</td><td>Martin</td><td>Grade 1</td><td>Paul Martin</td><td>Anna Martin</td><td>Rose Martin (Grandmother)</td></tr>
          <tr><td>S102</td><td>Lucas</td><td>Nguyen</td><td>Grade 3</td><td>Minh Nguyen</td><td>N/A</td><td>Lan Tran (Aunt); Bao Tran (Uncle)</td></tr></table></div>
          <ul><li>Only a <b>first name</b> (or a full name) is required. A <b>Student ID</b> is strongly recommended so re-uploads update instead of duplicating.</li>
          <li>Several relatives fit in one cell, separated by <code>;</code>. Put the relationship in brackets to show it on the kiosk.</li>
          <li><b>N/A</b>, "none" or an empty cell means "nobody".</li>
          <li>Any other column (allergies, bus route, department…) can be kept as <b>extra info</b>, shown on the person\'s page and stored encrypted.</li></ul>'],
        ['Which columns work for staff?',
         '<p><b>First Name</b> and <b>Last Name</b> (or one full-name column) are enough. <b>Employee ID</b> is recommended; <b>Email</b>, <b>Phone</b> and any other columns are optional. Staff imported here are the people who clock in and out, not logins.</p>'],
        ['If I upload the same file again, will it overwrite or duplicate?',
         '<p>Neither. People are matched by ID (or by name) and updated, new guardians are added, and <b>nobody is removed</b>. If a parent\'s name is spelled differently, both names will appear; remove the old one on the ' . e($a1) . '\'s page.</p>'],
        ['Does deleting a ' . e($a1) . ' delete their history?',
         '<p>No. Past check-ins and check-outs stay in the activity log and reports. To hide someone without deleting them, open them and set the status to <b>Inactive</b>.</p>'],
    ]],
    ['Reports', 'chart', [
        ['Which reports are emailed automatically?',
         '<p>By default you receive a <b>daily report</b> for the previous day, a <b>weekly report</b> every Monday for the previous week, and a <b>monthly report</b> on the 1st, at <b>7:00 your local time</b>, with PDF and CSV attachments.</p>'],
        ['How do I send reports to several people?',
         '<p>' . $link('/admin/reports', 'Reports') . ' → <b>Send to</b>: enter the email addresses separated by commas, for example <code>principal@school.org, office@school.org</code>, then save. You can also change the send time, the weekly day, which reports are sent and the attachments.</p>'],
        ['How do I create a report for any date range?',
         '<ol><li>Go to ' . $link('/admin/reports', 'Reports') . ' and click <b>Custom range</b>.</li>
          <li>Choose <b>From</b> and <b>To</b> (up to 6 months) and click <b>Show</b>.</li>
          <li>Download it as <b>PDF</b> or <b>CSV</b>, or use <b>Email now</b> to send it to your recipients or only to yourself.</li></ol>'],
        ['Where do I see who is here right now?',
         '<p>' . $link('/admin/attendance', "Today's attendance") . ' shows everyone present, absent or already gone home, and the ' . $link('/admin/logs', 'Activity log') . ' lists every check-in and check-out, which you can filter and export.</p>'],
    ]],
    ['Settings and security', 'settings', [
        ['How do I add our logo and colors?',
         '<p>' . $link('/admin/settings', 'Branding & settings') . ': upload your logo (PNG, JPG, WEBP or GIF, up to 2 MB), choose the main and accent colors, and set the kiosk welcome title and message.</p>'],
        ['How do I set our time zone?',
         '<p>' . $link('/admin/settings', 'Branding & settings') . ' → <b>Time zone</b>. All times and the 7:00 report schedule use it. "Automatic" uses each device\'s own time.</p>'],
        ['Can we use our own web address?',
         '<p>Yes: enter it under <b>Custom domain</b> in ' . $link('/admin/settings', 'Branding & settings') . ' and contact us; we will tell you the one DNS record to add.</p>'],
        ['Who changed what?',
         '<p>The ' . $link('/admin/audit', 'Audit log') . ' records every sign-in, change, import and report: who, when, from where and whether it worked.</p>'],
        ['How is our data protected?',
         '<p>Your data is stored in a <b>separate database</b> used only by your organization, contact details are <b>encrypted</b>, admins confirm sign-ins with an emailed code, connections are encrypted (https) and everything is <b>backed up daily</b>.</p>'],
    ]],
];
?>
<div class="topbar"><div><h1><?= icon('help') ?> Help &amp; FAQ</h1><div class="muted">Answers for running <?= e($t['name']) ?> day to day.</div></div>
  <a class="btn btn-ghost" href="<?= e(url('/admin/whats-new')) ?>"><?= icon('spark', 16) ?> What's new</a></div>
<div class="card faq-search"><input type="search" id="faq-q" placeholder="Search the help, e.g. password, import, kiosk, report…" aria-label="Search the help"></div>
<div class="faq-toc">
  <?php foreach ($faq as $i => [$section, $ico]): ?><a href="#faq-<?= $i ?>"><?= icon($ico, 16) ?> <?= e($section) ?></a><?php endforeach; ?>
</div>
<?php foreach ($faq as $i => [$section, $ico, $items]): ?>
  <section class="card faq-section" id="faq-<?= $i ?>">
    <h2><?= icon($ico) ?> <?= e($section) ?></h2>
    <?php foreach ($items as [$q, $a]): ?>
      <details class="faq-item"><summary><?= e(html_entity_decode($q, ENT_QUOTES)) ?></summary><div class="faq-a"><?= $a ?></div></details>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>
<p class="muted small faq-none" id="faq-none" hidden>No answers match your search. Try another word, or email <a href="mailto:<?= e(contact_email()) ?>"><?= e(contact_email()) ?></a>.</p>
<div class="card" style="text-align:center">
  <h3>Still need help?</h3>
  <p class="muted">Email <a href="mailto:<?= e(contact_email()) ?>"><?= e(contact_email()) ?></a>, or <a href="<?= e(url('/admin/whats-new#request')) ?>">request a feature</a>.</p>
</div>
