# TimeInOut — student, teacher & material-pickup check-in (SaaS)

A simple, colorful, multi-tenant web app in plain **PHP + MySQL**. Schools, daycares and after-school programs sign up, get their own portal (`theirname.abc.com`) right away, and use it on any tablet or phone to:

- 🎒 **Student sign in / sign out**: pick the day, type a few letters, pick the name, then pick *that student's* father, mother or guardian.
- 🍎 **Teacher / staff sign in / sign out**, with hours totalled.
- 📦 **Material pickup**: the parent picks up homework, books, uniforms and so on.
- 📈 **Daily, weekly and monthly reports**, on screen, as CSV, and emailed automatically.
- 📤 **CSV upload** of students with their parents, and of teachers.
- 🎨 **Branding**: logo, colors, welcome text and a custom domain.

| Document | What's inside |
|---|---|
| [docs/MASTER_PLAN.md](docs/MASTER_PLAN.md) | Vision, roles, key decisions, one-day test plan, roadmap |
| [docs/BLUEPRINT.md](docs/BLUEPRINT.md) | Every screen, function, rule, table and setting |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Architecture, provisioning, kiosk, admin and report flow charts, plus the ER diagram |
| [docs/mockup/index.html](docs/mockup/index.html) | Visual blueprint with diagrams and real screenshots of every screen |

---

## Requirements

- PHP **8.1+** with `pdo_mysql`, `mbstring` and `fileinfo` (all standard on hosting)
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` (`.htaccess` files are included) or Nginx

No Composer, no Node and no build step: you upload the files and it runs.

---

## Try it on your computer (5 minutes)

```bash
# 1. Database
mysql -u root -e "CREATE DATABASE timeinout CHARACTER SET utf8mb4"
mysql -u root timeinout < database/schema.sql

# 2. Config
cp app/config.sample.php app/config.php     # then edit the 'db' section

# 3. Run
php -S localhost:8000 -t public public/index.php
```

Open http://localhost:8000, click **Start free trial**, tick "Add sample students" and create your portal. You'll then be at `http://localhost:8000/s/<your-address>/`.

---

## Put it on hosting (cPanel or similar)

1. **Upload** the whole project to a folder *outside* `public_html`, for example `/home/USER/timeinout/`.
2. **Point the domain at `public/`.**
   - For the main domain: cPanel → *Domains* → set the document root of `abc.com` to `/home/USER/timeinout/public`.
   - If your host won't let you change it, upload everything **into** `public_html`. The root `.htaccess` sends requests to `public/` and blocks `app/`, `database/` and `cron/`.
3. **Create the database.** cPanel → *MySQL Databases*: create the database and a user, then add the user to the database with ALL PRIVILEGES. Then go to *phpMyAdmin* → choose the DB → *Import* → `database/schema.sql`.
4. **Configure.** Copy `app/config.sample.php` to `app/config.php` and set:
   - `db`: the host, name, user and password from step 3
   - `base_domain`: `abc.com`
   - `super_admin`: **your** email and a strong password (this is the `/super` login)
   - `mail`: your from-address. SMTP is recommended so mail doesn't land in spam: cPanel → *Email Accounts* → *Connect Devices* shows the host and port.
   - `cron_key`: a long random string
   - `force_https`: `true` once SSL is on
5. **Writable folder.** `public/uploads/logos` must be writable by PHP (normally it already is; otherwise set 755 or 775).
6. **Test.** Open `https://abc.com` → *Start free trial*. Portals work straight away at `https://abc.com/s/<name>`.

### Turn on `name.abc.com` subdomains

1. **DNS**: add an `A` record `*` (that is, `*.abc.com`) pointing to the same IP as `abc.com`.
2. **Hosting**: cPanel → *Domains* → *Create a New Domain*: enter `*.abc.com` with document root `/home/USER/timeinout/public`.
3. **SSL**: issue a wildcard certificate for `*.abc.com`. cPanel AutoSSL does this when the DNS is on the host; otherwise use Let's Encrypt DNS validation or Cloudflare.
4. In `app/config.php` set `'tenant_url_mode' => 'subdomain'`.

You don't copy any files for a new customer. Every subdomain is served by the same code, which reads the hostname and loads that customer's data and branding.

### Custom domains (`checkin.theirschool.org`)

1. The customer enters the domain in **Admin → Branding & settings**.
2. They add a DNS `CNAME` record `checkin` → `abc.com`.
3. You add the domain as an **Alias / Parked domain** in cPanel (document root `public/`) and issue SSL for it.

### Scheduled report emails

cPanel → *Cron Jobs*. Add a job that runs **every hour**:

```
php /home/USER/timeinout/cron/send_reports.php
```

Or, if your host has no cron, have a free web-cron service call `https://abc.com/cron?key=YOUR_CRON_KEY` every hour.

Daily reports go out after 6 AM local time (set by `report_hour`) and cover yesterday. Weekly reports go out on Mondays and monthly reports on the 1st. A report is never sent twice. To test right away, run `php cron/send_reports.php --force` or use **Reports → Email this report**.

### Nginx (VPS)

```nginx
server {
  server_name abc.com *.abc.com;
  root /var/www/timeinout/public;
  index index.php;
  location / { try_files $uri /index.php$is_args$args; }
  location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
  location ^~ /uploads/ { location ~ \.php$ { deny all; } }
}
```

---

## Demo portal (for showing the product)

After importing `schema.sql`, load a ready-made school, **Bright Future Academy**. It has 32 students with their parents, 8 teachers, 7 pickup items and six weeks of check-ins. There are two ways to load it:

- **In the browser:** log in at `abc.com/super` → **Load demo portal**. The same button resets it later.
- **From the command line:** `php database/seed_demo.php`

The demo lives at `abc.com/s/demo` (or `demo.abc.com` once subdomains are on). The homepage then shows a **Try the live demo** section with these logins:

| Role | Email | Password |
|---|---|---|
| Admin (owner) | `admin@demo.com` | `Demo@1234` |
| Front desk (staff) | `staff@demo.com` | `Staff@1234` |

The demo login page also has tap-to-fill buttons for both accounts. Visitors can change demo data, so reset it from `/super` before an important presentation.

## Who logs in where

| Who | URL | Login |
|---|---|---|
| You (platform owner) | `abc.com/super` | `super_admin` in `app/config.php` |
| Subscriber owner/admin | `name.abc.com/login` (or `abc.com/s/name/login`) | the email and password chosen at sign-up |
| Staff | same | added under **Admin → Users** |
| Kiosk | `name.abc.com/` | a staff member logs in once on the tablet. To use it without a login, turn on **Settings → Open kiosk without login**. |

Tip: on an iPad or Android tablet, open the kiosk and use **Add to Home Screen** so it runs full-screen.

---

## Payments

Sign-up starts a free trial (`trial_days`). To take payments today, create a **Stripe Payment Link** and put it in `payment_link`. It then shows on the welcome page and in the trial banner, with the portal name as `client_reference_id`. When someone pays, set them to **active** in `/super`. Automatic Stripe webhooks are Phase 2 in the master plan.

## Project layout

```
public/      web root: index.php, assets, uploads
app/         config, libraries, controllers, views (not web-accessible)
database/    schema.sql
cron/        send_reports.php
docs/        master plan, blueprint, architecture, mockups
```
