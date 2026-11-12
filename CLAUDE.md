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
- Documentation is also published to Confluence (space `CIO`) from `docs/confluence/` (see `pages.json` and
  MASTER_GUIDE §17 step 5). When the Atlassian connector is available, republish the pages a change affects and
  rebuild the offline copy. The private credentials register lives only in Confluence: it lists where secrets are
  stored, never their values.
