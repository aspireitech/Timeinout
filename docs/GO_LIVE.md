# ClockInOut — Go-live guide (clockinoutapp.com)

This guide covers moving the code to the live site, Cloudflare and SSL, the provider
console (`/super`), creating a customer portal with its own database, and the
dev → live release routine.

---

## 1. How the pieces fit

| Piece | Where it lives |
|---|---|
| Code | One copy, in `public_html` (or a folder whose `public/` is the web root). **Every** customer is served by this one copy. |
| Main database | `clockinout` (customer list, settings, payments, leads). Set in `app/config.php` → `db`. |
| Customer data | **Its own database** per customer (e.g. `u123456789_utsjkumon`), created in hPanel and entered in `/super → New portal`. |
| Settings per server | `app/config.php` — never in git, so pushing code never changes the live domain, HTTPS or passwords. |
| Encryption key | `app/keys/app.key` (or `app_key` in config). **Back it up in a password manager.** |

You do **not** need a separate `public_html` per customer. Separation is done with
separate databases: one customer's portal can never read another customer's database,
because it only has that one database's login.

---

## 2. Cloudflare (free plan is enough)

1. Add `clockinoutapp.com` to Cloudflare (free plan) and change the nameservers at your
   registrar to the two Cloudflare gives you.
2. DNS records (all **Proxied**, orange cloud):
   | Type | Name | Content |
   |---|---|---|
   | A | `@` | your Hostinger server IP (hPanel → Websites → Dashboard → IP) |
   | A | `*` | same IP |
   | CNAME | `www` | `clockinoutapp.com` |
   | A | `dev` | the dev server IP (optional, see section 7) |
3. SSL/TLS → Overview → **Full (strict)**.
4. SSL/TLS → Edge Certificates → **Always Use HTTPS: on**.

The free Universal certificate covers `clockinoutapp.com` and `*.clockinoutapp.com`
(one level). No certificate needs to be bought. It does **not** cover two levels
(`a.b.clockinoutapp.com`) — that is why dev uses `/s/name` links.

Origin certificate (between Cloudflare and Hostinger): Hostinger's free SSL works for
the main domain and for each subdomain you create in hPanel. If Hostinger cannot issue
one for a subdomain behind Cloudflare, create a free **Cloudflare Origin Certificate**
(SSL/TLS → Origin Server → Create, hostnames `clockinoutapp.com, *.clockinoutapp.com`,
15 years) and install it in hPanel → Security → SSL → Custom SSL.

---

## 3. Hostinger: put the site live

1. **Upload the code** (zip or hPanel → Advanced → Git, branch `main`).
   - Best: put the project in `/home/USER/domains/clockinoutapp.com/` and point the
     document root at `public/`.
   - If the document root must stay `public_html`, upload everything into it; the root
     `.htaccess` sends requests to `public/` and blocks `app/`, `database/`, `cron/`.
2. **Main database (fresh start)**: hPanel → Websites → Databases → MySQL Databases → create
   `cio_main` + user (becomes `u123456789_cio_main`). phpMyAdmin → Import → `database/schema.sql`. That is the whole
   empty database; the newest tables and columns are added automatically on the first page load.
3. **Config**: copy `app/config.sample.php` to `app/config.php` and set:
   - `db` → the database from step 2
   - `base_domain` → `clockinoutapp.com`
   - `tenant_url_mode` → `subdomain`
   - `force_https` → `true`
   - `super_admin` → `you@yourcompany.com` and a strong password (use the
     hash: `php -r "echo password_hash('…', PASSWORD_DEFAULT), PHP_EOL;"`)
   - `mail` → SMTP host `smtp.hostinger.com`, port `465`, secure `ssl`
   - `mail_senders` → the passwords of the two mailboxes (section 3a)
   - `contact_email`, `lead_email`, `report_bcc` → `contact@clockinoutapp.com`
   - `cron_key` → a long random string
4. **Subdomains**: hPanel → Domains → Subdomains. If your plan allows a wildcard,
   create `*` pointing to the same folder as the main site. Otherwise create each
   customer's subdomain (e.g. `utsjkumon`) with the **same folder as the main site**
   (custom folder → the main site's folder / `public`).
5. **Cron jobs** (hPanel → Advanced → Cron Jobs):
   - every hour: `php /home/USER/domains/clockinoutapp.com/cron/send_reports.php`
   - every day (e.g. 02:00): `php /home/USER/domains/clockinoutapp.com/cron/backup.php`
6. **Test**: open `https://clockinoutapp.com`, then `/super/login`, sign in, and in
   `/super → Settings` use **Send a test email**.

### 3a. Email: which mailbox sends what

| Mailbox | Sends | Receives |
|---|---|---|
| `contact@clockinoutapp.com` | Sign-in codes (provider and customer admins), account notices, test emails | Leads, “Suspicious activity reported”, a BCC of every report, replies to reports |
| `reports@clockinoutapp.com` | Daily, weekly, monthly and on-demand reports (Reply-To: contact@) | — |

Each mailbox logs in to SMTP with its own password, set in `app/config.php`:

```php
'mail_senders' => [
    'contact' => ['from_email' => 'contact@clockinoutapp.com', 'from_name' => 'ClockInOut',
                  'user' => 'contact@clockinoutapp.com', 'pass' => 'CONTACT-MAILBOX-PASSWORD'],
    'reports' => ['from_email' => 'reports@clockinoutapp.com', 'from_name' => 'ClockInOut Reports',
                  'user' => 'reports@clockinoutapp.com', 'pass' => 'REPORTS-MAILBOX-PASSWORD'],
],
```

Check both in `/super → Settings → Send a test email` (choose “Send from”). To land in
inboxes rather than spam, make sure the domain's **SPF, DKIM and DMARC** records exist
(hPanel → Emails → shows the records; add them in Cloudflare DNS, **DNS only**, grey cloud).

Every sign-in code email ends with: *“Did not request this code? … report it to us”*.
The link opens a page with one button; pressing it emails contact@ with the subject
**Suspicious activity reported — <account>**, the portal, the IP and the time of the attempt.
(Opening the link alone does nothing, so email virus scanners cannot file false reports.)

### Email through Microsoft Graph (Microsoft 365, no SMTP)

Use this when Microsoft 365 refuses SMTP ("SmtpClientAuthentication is disabled for the Tenant"). Mail
still comes from contact@ and reports@, through Microsoft's servers, so SPF/DKIM/DMARC pass unchanged.

1. **Register the app**: https://entra.microsoft.com → Applications → **App registrations** → New registration →
   name `ClockInOut Mail`, "Accounts in this organizational directory only" → Register.
   Copy **Application (client) ID** and **Directory (tenant) ID** from the Overview page.
2. **Permission**: API permissions → Add a permission → Microsoft Graph → **Application permissions** →
   `Mail.Send` → Add → **Grant admin consent** (status turns green).
3. **Secret**: Certificates & secrets → New client secret (24 months) → copy the **Value** (shown once).
   Put a reminder in your calendar to renew it before it expires.
4. **Limit it to the two mailboxes** (important: otherwise the app could send as any mailbox in your
   company). Exchange Online PowerShell (`Connect-ExchangeOnline`):
   ```powershell
   New-DistributionGroup -Name "ClockInOut Senders" -Type Security -Members contact@clockinoutapp.com,reports@clockinoutapp.com
   New-ApplicationAccessPolicy -AppId <client-id> -PolicyScopeGroupId "ClockInOut Senders" -AccessRight RestrictAccess -Description "ClockInOut may send only as contact@ and reports@"
   Test-ApplicationAccessPolicy -AppId <client-id> -Identity contact@clockinoutapp.com   # AccessCheckResult: Granted
   Test-ApplicationAccessPolicy -AppId <client-id> -Identity someone.else@clockinoutapp.com  # Denied
   ```
   (Microsoft's newer equivalent is "RBAC for Applications" in Exchange Online; either works.)
5. **app/config.php**: set `'driver' => 'graph'` in `mail`, and fill in
   `'graph' => ['tenant_id' => '…', 'client_id' => '…', 'client_secret' => '…']`.
   The mailbox passwords in `mail_senders` are no longer needed (leave them empty).
6. `/super → Settings → Send test email` from contact@ and from reports@. Changes in Entra can take a
   few minutes to apply.

### 3b. Reports

- Each portal sends **daily** (the previous day), **weekly** (last Monday–Sunday, sent on
  Monday) and **monthly** (last month, sent on the 1st) reports at **7:00 the customer's
  local time** by default. The customer can change the hour, weekday and recipients in
  **Admin → Reports**. Set the portal's time zone when creating it.
- **On demand**: Admin → Reports → **Custom range**, any dates up to **6 months**:
  view, PDF, CSV, or email it.
- All reports come from reports@ with a hidden copy to contact@ (`report_bcc`; set it to
  `false` to stop the copies — with many customers that is a lot of mail).

---

## 4. Provider console: `/super`

`https://clockinoutapp.com/super/login`

Sign-in options, in this order:

1. **Sign in with Google** (first choice): one click, protected by your Google account's
   2-Step Verification. Appears once `google_oauth` is filled in (below). To also require the
   emailed code after Google, add `'super_google_code' => true` to `app/config.php`.
2. **Email code** when Google is down: enter the provider email, get a 6-digit code.
3. **Break-glass** when Google and email are both down: the break-glass password **plus** one
   one-time recovery code. Every use is logged and emailed to you.
4. **Server last resort** (SSH): `php cron/super_password.php --recovery` sets a new break-glass
   password in the database and prints new recovery codes.

### Set up break-glass (do this once)

1. Sign in, open **`/super` → Sign-in**, set the **break-glass password** (14+ characters). It is
   stored hashed in the database; then delete the `'password' => '…'` line from `super_admin` in
   `app/config.php` (keep the email).
2. On the same page click **Create recovery codes** and save the 10 codes with the password in
   your password manager.

### Turn on Sign in with Google

1. https://console.cloud.google.com → create a project “ClockInOut”.
2. APIs & Services → OAuth consent screen → External → app name ClockInOut, your email.
   Add yourself as a test user (or publish the app).
3. Credentials → Create credentials → OAuth client ID → **Web application**.
   Authorized redirect URIs:
   - `https://clockinoutapp.com/super/google/callback`
   - `https://dev.clockinoutapp.com/super/google/callback` (if you use dev)
4. Paste the Client ID and Client secret in **`/super` → Sign-in → 1. Sign in with Google** and
   save (the secret is stored encrypted). The `google_oauth` block in `app/config.php` still works
   if you prefer it there.

### Sections

| Section | What it does |
|---|---|
| Subscribers | All portals, their database (Own DB / Shared DB), plan and status, **Reset owner password** |
| New portal | Create a customer portal by hand (section 5) |
| Leads | Every demo request from `/demo`: status, notes, CSV export |
| Payments | Stripe / Wave / manual payments |
| Audit log | Every provider sign-in and change |
| Settings | Email (SMTP), Stripe, Wave, admin sign-in codes |

### Plans, limits and yearly billing

- Plans live in `app/lib/billing.php` (`PLAN_CATALOG`): Starter $39 (75 people, 3 staff logins, 90 days of reports), Business $79 (500 people, unlimited logins, 180 days), Enterprise $149 (unlimited). The old `'plans'` block in `config.php` is no longer read; to change a plan without editing code, add `'plan_overrides' => ['starter' => ['amount' => 45]]` to `config.php`.
- **Enforced:** people (active students + staff, visitors don't count), staff logins and report history (reports start no earlier than the plan allows; Enterprise can run 12-month reports). Adding one more, or importing past the limit, shows "Upgrade in Admin → Billing". Nothing is ever removed.
- **Yearly billing:** customers pay for 10 months and get 12 (change with `'annual_months' => 10` in config). For card payments, create a **yearly** recurring price for each plan in Stripe (Products → your plan → Add price → Yearly) and paste its ID under `/super → Settings → Yearly price ID`. Wave invoices need nothing extra: yearly invoices use the same product at the yearly amount.

---

### Search engines (Google)

- `robots.txt` and `sitemap.xml` are generated automatically; customer portals and `/super` are
  kept out of search results.
- **Google Search Console**: add the property `https://clockinoutapp.com/`, choose **HTML tag**,
  paste it in **`/super` → Settings → Google Search Console**, click Verify, then submit
  `https://clockinoutapp.com/sitemap.xml`.
- **Google Analytics 4** (optional): paste the `G-…` ID in the same place. It runs on the public
  website only.
- Share links that open the home page tagged by channel: `/linkedin`, `/facebook`, `/email`,
  `/web`, `/friend` (sign-ups and demo requests show the source in `/super`).

## 5. Create a customer portal (example: utsjkumon.clockinoutapp.com)

1. **hPanel → Databases → MySQL Databases** → create a new database and user. Type
   `cio_utsjkumon` for both (hPanel adds your prefix: `u123456789_cio_utsjkumon`) and a strong
   password. Leave it empty (do not import anything). User names can be at most 32 characters.
2. **hPanel → Domains → Subdomains** → create `utsjkumon` pointing to the main site's
   folder (skip if you have a wildcard `*`).
3. **`/super → New portal`**:
   - Business name: *South Jordan Kumon* · Web address: `utsjkumon`
   - Industry: Tutoring · State: Utah · Time zone: America/Denver
   - Owner name + owner email (their login)
   - Plan, **Start as: Active** (you bill them yourself for now)
   - Leave **Add sample people** unticked
   - Database: **Own database**. The name and user fill in by themselves as
     `u123456789_cio_<address>` while you type the address (the prefix comes from your main
     database name); check they match hPanel, then enter the password. Host `localhost`.
4. Click **Create portal**. The next screen shows the portal link and a **temporary
   password — shown only once**. Send the link and the password separately.
5. Optional: import their people in **Admin → Import** (Excel `.xlsx` or CSV, first row =
   column headers; the sample files show the columns).
6. The owner signs in at `https://utsjkumon.clockinoutapp.com/login`, enters the code
   emailed to them, then changes the password under **Admin → Users → Change my password**.

**Forgot password?** `/super → Subscribers → Reset owner password` gives the owner a
new temporary password (shown once). Owners can reset their staff's passwords
themselves in **Admin → Users**.

**Delete a portal**: `/super → Subscribers → Delete portal`.
1. A pop-up asks “Are you going to delete …?”.
2. The next page asks you to **type the portal's address** (e.g. `utsjkumon`), enter **your
   super admin password** and tick “I understand”.
3. A last pop-up asks once more, then the portal is deleted.

A deleted portal **closes immediately** (its address shows “This portal has been closed”) but
its data is **kept for 30 days** under *Deleted portals*, where you can **Restore** it. After
30 days the hourly job removes it for good, or use **Remove permanently now** (same checks).
Permanent removal erases the portal's people, check-ins, logins and logs; for a portal with its
own database a last copy is saved to `backups/deleted/` first, and you then delete the empty
database in hPanel. Payments stay in your records. Every step is in the audit log.

What “Own database” does: the portal's people, attendance, logins, reports log and
audit log are stored only in its database. The main database keeps only the portal's
name, address, plan and the encrypted database login.

---

## 6. Release routine: dev → live

```
your change ─▶ dev.clockinoutapp.com (test) ─▶ merge into main ─▶ live: pull/upload ─▶ done
```

1. Changes are made on a branch and tested on the dev site.
2. When it looks good, merge into `main`.
3. Live site: hPanel → Git → **Deploy** (or upload the new zip over the old files).
   `app/config.php`, `app/keys/` and `public/uploads/` are not in git, so they are kept.
4. Database changes run by themselves on the first page load after the update (each
   customer's own database is upgraded the first time its portal is opened, and by the
   hourly cron).

---

## 7. Dev site (optional, recommended)

A second Hostinger website (or subdomain with its own folder) `dev.clockinoutapp.com`
with **its own database** and its own `app/config.php`:

| Setting | dev | live |
|---|---|---|
| `base_domain` | `dev.clockinoutapp.com` | `clockinoutapp.com` |
| `tenant_url_mode` | `path` (`dev.clockinoutapp.com/s/name`) | `subdomain` |
| `force_https` | `true` | `true` |
| Stripe keys | test keys | live keys |
| Database | its own | its own |

`dev`, `staging` and `test` are reserved, so no customer can take those addresses.

---

## 8. Backups and restoring

### What must be backed up

| Item | Why | How |
|---|---|---|
| **Main database** | Customer list, which database each customer uses, settings, payments, leads | `cron/backup.php` (daily) |
| **Each customer's database** | All their people, check-ins and check-outs, history, logins | `cron/backup.php` (daily) |
| `app/config.php` | Domain, database logins, mailbox passwords | Password manager (copy the file's contents) |
| **`app/keys/app.key`** | Unlocks every encrypted value: **the customers' database passwords**, the mailbox/SMTP and Stripe keys, guardians' and staff phone numbers and emails | Password manager |
| `public/uploads/` | Customer logos | Download occasionally |
| Code | Same for every customer | GitHub (`main`) |

`cron/backup.php` writes `backups/<date>/main_….sql.gz`, one `<portal>_<database>.sql.gz`
per customer and `MANIFEST.txt`, and keeps 14 days. **Copy that folder off the server**
(e.g. download it weekly, or Hostinger's own daily backups on Business/Cloud plans).

### Restore on the same or a new host

1. Upload the code (GitHub `main` or the zip) and put back `app/config.php` and `app/keys/app.key`.
2. Create the main database and each customer database **with the same names, users and
   passwords** as before, and import each `.sql.gz` from the backup (phpMyAdmin → Import
   accepts `.gz`). If a name has to change (new host prefix), update that customer's row
   in `tenants` (`db_name`, `db_user`) — and use the same password, because it is stored
   encrypted with `app.key`.
3. Point Cloudflare's `@` and `*` records at the new server IP.
4. Every portal reconnects to its own database by itself: the main database says which
   database belongs to which address, and `app.key` unlocks its password.

Restoring **only the code** brings the site back with no customers. Restoring **only a
database** without `app.key` leaves portals unable to open their databases.

---

## 9. Before the first customer goes live

- [ ] Cloudflare: nameservers switched, `@` `*` `www` records proxied, SSL **Full (strict)**
- [ ] `app/config.php`: `base_domain`, `tenant_url_mode = subdomain`, `force_https = true`, `super_admin`, `mail`, `mail_senders` (both passwords), `cron_key`
- [ ] `/super → Settings → Send a test email` from **both** contact@ and reports@ arrives (check spam); SPF/DKIM/DMARC added
- [ ] Both cron jobs added (reports hourly, backup daily); run the backup once by hand
- [ ] `app/keys/app.key` copied into your password manager
- [ ] Hourly cron job added
- [ ] Throw-away test portal created, kiosk check-in tried, owner sign-in with code tried — then suspend it
- [ ] `/demo` form sent once, email arrived at contact@, lead visible in `/super → Leads`
- [ ] Customer's people imported (Admin → Import, Excel .xlsx or CSV) and checked
- [ ] One test check-in and check-out on the kiosk tablet, then deleted from Admin → Logs
