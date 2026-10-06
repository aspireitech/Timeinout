# TimeInOut — Master Plan

## 1. What we are building

A **simple, lightweight, colorful web app** (PHP + MySQL) that runs in any browser and on any ordinary web hosting. Schools, daycares and after-school programs **subscribe**, get **their own portal** (`xyz.abc.com`) in seconds, and use it to record:

| Who | What they do on the kiosk |
|---|---|
| **Student** (with a parent/guardian) | Pick the day → type a few letters of their name → pick their name → pick **only their own** father / mother / guardian → **Sign In** (drop-off) or **Sign Out** (pick-up) |
| **Teacher / Staff** | Pick the day → type a few letters → pick their name → **Sign In** / **Sign Out** |
| **Material pickup** | Pick the day → find the student → pick the guardian → tick the items (homework, books…) → **Confirm pickup** |

The subscriber's **owner/admin** logs in to upload students/parents and teachers from a **CSV (flat file)**, view who is on site, browse the log, and get **daily, weekly and monthly reports** by email. They can **customize** their name, logo, colors, home-page welcome text and use their **own domain**.

## 2. Goals & non-goals (for this first version)

**Goals**
- Works in a browser on a tablet, laptop or phone — no app install.
- Deploys to cheap shared hosting (cPanel, Hostinger, Bluehost, GoDaddy…) or any VPS. No Composer, no Node, no build step.
- Very user-friendly: big touch targets, 3–4 taps per check-in, auto-reset for the next person.
- Self-service sign-up with **automatic provisioning**.
- Modern, colorful look; each subscriber can rebrand.

**Not in v1** (kept out on purpose so it can be tested in a day): online card payments with webhooks, SMS notifications, photo capture, signatures, QR badges, multiple campuses per subscriber, guardians shared across siblings. All are listed in §7.

## 3. Roles

| Role | Where | Can do |
|---|---|---|
| **Platform owner** (you) | `abc.com/super` | See every subscriber, change plan, mark active / suspend / cancel |
| **Owner** (subscriber who signed up) | `xyz.abc.com/admin` | Everything inside their portal; cannot be deleted |
| **Admin** | same | Everything except removing the owner |
| **Staff** | same | Open the kiosk, see dashboard, attendance log and reports |
| **Kiosk user** (students, parents, teachers) | `xyz.abc.com/` | Use the kiosk on a device where staff have logged in (or without login if the admin turns on "public kiosk") |

## 4. Key design decision — how "provisioning" works

You described provisioning as *"copy all the files and the database into a new virtual directory for each customer."* That works, but it is fragile on shared hosting (most hosts don't let PHP create databases or subdomains, and every bug fix must be copied N times).

This build gets **the same result for the customer** in a simpler, safer way:

- **One copy of the code, one database.** Every table has a `tenant_id`.
- When someone signs up, the app creates a `tenants` row, the owner login, default pickup items and (optionally) sample people. **That is the provisioning — it takes < 1 second.**
- A **wildcard DNS record** `*.abc.com` + a **wildcard subdomain** in hosting send *every* `something.abc.com` to the same app. The app reads the hostname and loads that subscriber's data and branding.
- Until wildcard DNS is set up, every portal also works at `abc.com/s/xyz` — so you can test today.
- **Custom domains** (`checkin.xyzschool.org`) are a CNAME to `abc.com` plus a domain alias in hosting; the app looks the hostname up in `tenants.custom_domain`.

Benefits: instant sign-up, one place to update, one backup, data still isolated per subscriber (every query is filtered by `tenant_id`, and logins are tied to one tenant).

## 5. Feature list (all built)

1. **Marketing site** — landing page, features, pricing, sign-up.
2. **Sign-up & auto-provisioning** — organization name → suggested address (checked live) → owner account → portal ready, optional demo data, free trial.
3. **Kiosk** — day picker (Today / Yesterday / other day), three colorful role cards, type-ahead name search, guardian list limited to the chosen student, suggested action (shows "Signed in at 8:02 by Sarah — Sign Out?"), material pickup checklist, success screen with auto-reset, idle reset after 90 s, live clock in the subscriber's time zone.
4. **Admin dashboard** — on-site-now counts, students and teachers currently in, 14-day trend, latest activity.
5. **Students & guardians** — list/search/filter by grade, add/edit/deactivate/delete, add/remove guardians per student.
6. **Teachers & staff** — list/search, add/edit/deactivate/delete.
7. **Materials** — list of items that can be picked up; add/hide/delete.
8. **CSV import** — students with up to 4 guardians per row (or one guardian per row), teachers; Excel-friendly (BOM, `;` or `,`), safe to re-upload (updates instead of duplicating); sample files.
9. **Attendance log** — filter by dates, student/teacher, action, name; delete mistakes; CSV export.
10. **Reports** — daily / weekly / monthly views with totals, by-day chart, teacher hours, "signed in but never signed out", material pickups, CSV; **automatic emails** to the owner (cron job or web-cron URL); "Email this report" button.
11. **Branding & settings** — name, logo, two colors, welcome title/message (kiosk home page), time zone, past-day entries, public kiosk switch, custom domain.
12. **Users** — add admin/staff logins, remove, change password.
13. **Platform owner console** — all subscribers, usage, plan, status (trial/active/suspended/cancelled).

## 6. One-day test plan

| Time | Step |
|---|---|
| 0:00 | Upload files, create MySQL DB, import `database/schema.sql`, copy `app/config.sample.php` → `app/config.php` (see README) |
| 0:20 | Open `https://abc.com` → **Start free trial** → create "Test School" with sample data ticked |
| 0:25 | Log in → **Open kiosk** → try Student / Teacher / Material pickup flows on a tablet or phone |
| 0:45 | Admin → **Import CSV** → download the sample, edit in Excel, upload |
| 1:00 | **Branding** → logo, colors, welcome text → reload kiosk |
| 1:10 | **Reports** → daily / weekly / monthly → **Email this report** |
| 1:20 | Set up the cron job; next morning check the daily email arrives |
| 1:30 | `abc.com/super` → see the subscriber; try Suspend → portal shows "suspended" |
| later | Add wildcard DNS `*.abc.com` → switch `tenant_url_mode` to `subdomain` |

## 7. Roadmap (after the first test)

- **Phase 2 — Billing:** Stripe Checkout + webhook to set `status=active` automatically; plan limits enforced.
- **Phase 3 — Safety:** guardian photo / PIN, authorized-pickup-only flag, SMS/email to parents on sign-out, signature capture.
- **Phase 4 — Convenience:** QR-code badges, guardians shared across siblings, multiple sites per subscriber, late-pickup alerts, teacher PINs.
- **Phase 5 — Scale:** move to a VPS, Redis sessions, per-tenant database option for very large customers.
