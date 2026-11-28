# Working on ClockInOut

- **docs/MASTER_GUIDE.md is the single reference.** Any change that adds or alters a feature, setting,
  integration, cron job, database table/column, route or deployment step must update the matching
  section of that guide **in the same commit**, add a dated line to its change log (§18), and bump the
  "Schema version" in its header when `SCHEMA_VERSION` changes. Re-render `docs/img/architecture.png`
  if the architecture changes.
- The repository is public: never commit secrets, real customer data, hosting account IDs or
  `app/config.php` / `app/keys/`.
- Keep `main` deployable: plain PHP 8, no build step; schema changes go in `app/lib/migrate.php` as a new
  `migration_N()` and must be additive.
- Documentation is also published to Confluence (space `AIPROD`, "Aspire iTECH - Internal - Product") from `docs/confluence/` (see `pages.json` and
  MASTER_GUIDE §17 step 5). When the Atlassian connector is available, republish the pages a change affects and
  rebuild the offline copy. The private credentials register lives only in Confluence: it lists where secrets are
  stored, never their values.
- **Every zip the owner uploads must pass the security gate.** Build it only with
  `php cron/release_check.php --zip OUT.zip` (syntax, secrets, forbidden files, headers, CSRF and webhook
  verification, injection patterns, unescaped output, additive migrations). If it fails, fix the cause; never hand
  over a zip that failed. Before that, review the changed code for security the way an attacker would (input,
  output, auth, redirects, headers, data exposure) and tell the owner the result together with the zip.
  New unescaped template output is added to `cron/release_check_known.txt` only after checking it is safe.
- After a restore or a move to new hosting, `php cron/verify_restore.php` (or /super → Settings → Run the restore
  check) proves every credential works; keep its checks in step with new integrations.
