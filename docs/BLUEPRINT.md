# TimeInOut — Functional & Technical Blueprint

Companion to [MASTER_PLAN.md](MASTER_PLAN.md) (the why) and [ARCHITECTURE.md](ARCHITECTURE.md) (the diagrams).

---

## 1. Technology

| Layer | Choice | Why |
|---|---|---|
| Language | PHP 8.1+ (plain, no framework) | Runs on every shared host; nothing to install |
| Database | MySQL 5.7+ / MariaDB 10.3+ (InnoDB, utf8mb4) | Standard on every host; phpMyAdmin import |
| Front end | Server-rendered HTML + one CSS file + ~250 lines of vanilla JS | Fast on cheap tablets; no build step |
| Font | Plus Jakarta Sans (Google Fonts), system font fallback | Modern look |
| Email | PHP `mail()` or built-in SMTP client (STARTTLS / SSL, AUTH LOGIN) | Works without Composer |
| Web server | Apache (`.htaccess` included) or Nginx (`try_files` to `index.php`) | |

## 2. Folder layout

```
public/              ← web root (point the domain here)
  index.php          ← front controller: works out tenant + route
  assets/css/app.css ← design system (colors, cards, kiosk, admin)
  assets/js/kiosk.js ← kiosk step-by-step flow
  assets/js/app.js   ← sign-up address checker, confirm dialogs
  uploads/logos/     ← subscriber logos (scripts blocked)
app/                 ← not web-accessible
  config.sample.php  ← copy to config.php
  bootstrap.php, routes.php
  lib/   db, helpers, tenant, auth, mailer, reports, csv_import
  controllers/  platform, kiosk, api, admin
  views/  layouts, platform, auth, kiosk, admin, partials
database/schema.sql  ← import once
cron/send_reports.php← run hourly
docs/                ← this blueprint, plan, diagrams, mockups
```

## 3. Data model

| Table | Purpose | Key columns |
|---|---|---|
| `tenants` | One row per subscriber | `slug` (subdomain, unique), `custom_domain` (unique), `plan`, `status` (trial/active/suspended/cancelled), `trial_ends_at`, branding (`logo_path`, `primary_color`, `accent_color`, `welcome_title`, `welcome_text`), `timezone`, `kiosk_public`, `allow_past_days`, report settings |
| `users` | Logins for a tenant | `tenant_id`, `email` (unique per tenant), `password_hash` (bcrypt), `role` owner/admin/staff |
| `students` | | `tenant_id`, `student_code` (unique per tenant, optional), `first_name`, `last_name`, `grade`, `active` |
| `guardians` | Parents/guardians **of one student** | `tenant_id`, `student_id` → students (cascade delete), `name`, `relationship`, `phone`, `email` |
| `teachers` | Teachers & staff | `tenant_id`, `employee_code`, names, contact, `active` |
| `materials` | Items that can be picked up | `tenant_id`, `name`, `active`, `sort_order` |
| `attendance` | **Every kiosk action** | `tenant_id`, `person_type` student/teacher, `person_id`, `person_name` (snapshot), `guardian_id`, `guardian_name` (snapshot), `kind` sign_in/sign_out/material_pickup, `event_date`, `event_time`, `materials`, `recorded_by`, `ip` |
| `report_log` | Stops duplicate report emails | unique (`tenant_id`, `period`, `period_start`) |

Names are *snapshotted* into `attendance` so history stays readable after a student/guardian is edited or deleted.

## 4. Request routing & multi-tenancy

`public/index.php` → `resolve_tenant()` (app/lib/tenant.php):

1. Path `/s/<slug>/…` → that tenant (works on any hosting, used for testing).
2. Host `<slug>.<base_domain>` → that tenant (needs wildcard DNS).
3. Host equals a `tenants.custom_domain` → that tenant.
4. `base_domain`, `www.base_domain`, `localhost` → platform (marketing) site.
5. Unknown slug → 404 "no portal at this address". Suspended/cancelled → 403 page.

Isolation rules: every query includes `tenant_id = tid()`; the session user stores `tenant_id` and `current_user()` returns nothing if it doesn't match the current tenant (so a login on `/s/a` never opens `/s/b`).

## 5. Screens & functions

### 5.1 Platform site (`abc.com`)
| Route | Function | Details |
|---|---|---|
| `GET /` | `platform_home` | Hero, feature cards, pricing from `config.plans` |
| `GET/POST /signup` | `platform_signup` | Fields: organization, address (slug), your name, email, password (8+), plan, time zone, "add sample data". Validates; calls `provision_tenant()`; shows Welcome page with portal link |
| `GET /check-address?slug=` | `platform_check_slug` | Live availability check (format, reserved words, taken) |
| `GET/POST /super/login`, `GET /super`, `POST /super/tenants/{id}` | super console | Owner email/password from config; list subscribers with owner email, people counts, 30-day activity; change plan & status |
| `GET /cron?key=` | `web_cron` | Same as the CLI cron, for hosts without cron |

### 5.2 Provisioning (`provision_tenant`)
In one DB transaction: insert tenant (status `trial`, `trial_ends_at` = now + `trial_days`, welcome text, report email = owner email) → insert owner user (bcrypt) → insert 5 default materials → optional demo: 6 students with 1–3 guardians each, 3 teachers. Roll back on any error.

### 5.3 Kiosk (`xyz.abc.com/`)
| Step | Screen | Behaviour |
|---|---|---|
| 0 | Header + day bar | Logo, name, live clock in tenant time zone. Day chips **Today / Yesterday / Other day** (limited by `allow_past_days`; future days blocked). |
| 1 | Who are you? | 3 large gradient cards: **Student** (purple), **Teacher / Staff** (teal), **Material Pickup** (orange). Title/message from Branding. |
| 2 | Find your name | Big search box; after each keystroke (180 ms debounce) `GET /api/search` returns up to 12 active people whose first name, last name or full name **starts with** the typed letters. |
| 3 | Who is dropping off / picking up? (students & pickup) | `GET /api/guardians?student_id=` returns **only that student's guardians**. If none: "please see the front desk". |
| 4a | Sign In / Sign Out | `GET /api/status` checks the last in/out for that person on that day; the expected button is highlighted and a note shows "Signed in at 8:02 AM by Sarah Johnson." For a past day a **time** field appears and is required. |
| 4b | Material pickup | Checkboxes from the Materials list + "Something else?" text. At least one item required. |
| 5 | Success | Big animated check, "Ava Johnson is signed in — By Sarah Johnson · 8:02 AM · Tue, Oct 6". Returns to step 1 after 6 s (or **Done**). |
| — | Safety | Idle 90 s → back to start. Kiosk reloads after midnight so "Today" is right. All POSTs carry a CSRF token. |

`POST /api/record` validates: tenant owns the person and the guardian; the guardian belongs to that student; date within the allowed window; time format; kind allowed. Stores names as snapshots plus `recorded_by` and IP.

Access: the kiosk needs a logged-in user of the tenant (any role) unless **Open kiosk without login** is ticked in Settings.

### 5.4 Admin portal (`xyz.abc.com/admin`)
| Page | Who | Functions |
|---|---|---|
| Dashboard | all | 4 KPI tiles (students on site, student sign-outs today, teachers on site, pickups today); lists of students (with drop-off guardian & time) and teachers currently in; 14-day sign-in chart; last 12 actions |
| Students & guardians | admin | Search by name/ID, filter by grade; add student with up to 2 guardians in one form; edit page with guardians list (add / remove), status active/inactive (inactive = hidden from kiosk), recent activity; delete |
| Teachers & staff | admin | Same pattern |
| Materials | admin | Add, show/hide, delete |
| Import CSV | admin | Two uploaders + sample downloads (see §6) |
| Attendance log | all (delete: admin) | Filters: from/to, who, action, name; 100 per page; CSV export (Excel-safe) |
| Reports | all (email settings: admin) | Daily/Weekly/Monthly + date + prev/next; KPI tiles; sign-ins by day; teacher hours (paired in→out per day); never-signed-out list; pickups; CSV; **Email this report**; recipients & which reports to auto-send; recently sent list |
| Branding & settings | admin | Name, welcome title & message, logo (PNG/JPG/WEBP/GIF ≤ 2 MB, type checked), main & accent colors, time zone, past-day window, public kiosk, custom domain |
| Users | admin (others: password only) | List with last login; add admin/staff; remove (not owner/self); change own password |

### 5.5 Scheduled reports
`cron/send_reports.php` (hourly) or `/cron?key=…`. For each trial/active tenant with report emails, once local time ≥ `report_hour` (default 6 AM):
- **Daily** → yesterday · **Weekly** (Mondays) → last Mon–Sun · **Monthly** (1st) → last month.
Each sent report is written to `report_log`, so running the job many times never sends twice. Email is a self-contained colorful HTML summary with a link to the full report.

## 6. CSV formats

**Students** — one row per student (columns matched case/space-insensitively):

`student_id, first_name, last_name, grade, guardian1_name, guardian1_relationship, guardian1_phone, guardian1_email, guardian2_name, …` (up to guardian4)

Also accepted: `student_name` (full name, or "Last, First"), and one-guardian-per-row files using `guardian_name, relationship, phone, email` (repeat the student on several rows). Matching: by `student_id` if given, otherwise by first+last name; existing guardians matched by name.

**Teachers** — `employee_id, first_name, last_name, email, phone` (or `name`).

Excel BOM, Windows-1252 text and `;` delimiters are handled. Exports prefix cells starting with `= + - @` to block spreadsheet formula injection.

## 7. Security checklist (implemented)

- Prepared statements everywhere (PDO, emulation off).
- Passwords: `password_hash` (bcrypt); login throttling (8 failures / 15 min per session).
- Session cookie HttpOnly, SameSite=Lax, Secure on HTTPS; session ID regenerated at login.
- CSRF token on every POST (checked centrally in the router) and on kiosk API calls (header).
- Output escaping via `e()` in every view; JSON responses for the kiosk; JS builds HTML with an escaper.
- Upload checks: size, real MIME type (`finfo`) and `getimagesize`; random filenames; scripts denied in `uploads/`.
- `app/`, `database/`, `cron/` blocked from the web.
- Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`.
- Tenant isolation as in §4.

## 8. Configuration reference (`app/config.php`)

| Key | Meaning |
|---|---|
| `db` | host, name, user, pass, port |
| `base_domain` | e.g. `abc.com` |
| `tenant_url_mode` | `path` (abc.com/s/xyz — default, works anywhere) or `subdomain` (xyz.abc.com) |
| `force_https` | build https links |
| `super_admin` | platform owner email + password (plain or bcrypt hash) |
| `trial_days`, `plans`, `payment_link` | sign-up & pricing |
| `mail` | `driver` mail/smtp, from address/name, SMTP host/port/secure/user/pass |
| `report_hour` | local hour after which reports are sent |
| `cron_key` | secret for `/cron?key=` |
| `debug` | show PHP errors (off in production) |
