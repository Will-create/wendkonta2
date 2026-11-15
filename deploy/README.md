# Wendkonta deployment

Run from this checkout on the target host with root access to Docker, Nginx,
`/www`, systemd, and `/etc/letsencrypt`. Python 3, Docker Compose v2 (including
`up --wait`), Nginx, Certbot, and curl must already be installed.

```sh
cd /root/wendkonta/repo
python3 deploy/deploy.py --check
python3 deploy/deploy.py
```

An optional `--email you@example.com` sets the Let's Encrypt account email.
Without it, Certbot uses its existing account or registers without an email.
The command builds PHP 8.3 FPM, runs the PHP production-reset and sale-selection
regressions in a disposable checkout with SQLite, and stages the application under
`/www/www/logicielyaar_site-wendkonta`, imports `db.sql` into a new `wendkonta`
database on `mysql-container`, and enables `https://wendkonta.logicielyaar.site`.
FPM binds only to `127.0.0.1:19083`; Nginx serves static files directly and
sends only the front controller to FPM. The relative public storage symlink
works in both the host and container filesystems.

The application key and database password are generated once in the deployed
`.env` (root:www-data, mode 0640). Keep a protected backup of this file with
database and upload backups. Root MySQL credentials stay inside the existing
container; the application and SQL import use a user restricted to its own database.
The original SQL dump is never edited or copied into the deployed application.

The importer removes only the known dump's three database directives. It
refuses a preexisting `wendkonta` database, an occupied FPM port, a conflicting
vhost/container, or unexpected SQL. It never runs migrations or seeders.
All 282 application/module migrations are already represented in this dump.

Deployment state and the original vhost backup are retained in ignored
`deploy/state/`. After a completed import, retry an interrupted deployment with:

```sh
python3 deploy/deploy.py --resume
python3 deploy/deploy.py --verify
```

Resume preserves the database and application key. Public vendor libraries are
included in checkout copies; only the root Composer
`vendor/` directory is excluded. Resume also restores missing `public/vendor`
files from the source checkout while retaining existing assets, uploads, and `.env`.

If an import was interrupted before it was marked complete, the script stops for inspection rather than
overwriting or dropping the partially imported database. Staging interruptions
also require inspection; do not delete the state directory or `.env` to force
a retry. This is a first-deployment tool, not an automatic application updater.

Activation failures restore the original vhost and stop the new PHP service.
The database, uploaded files, key, and issued certificate are retained. To do
the same explicitly:

```sh
python3 deploy/deploy.py --rollback
```

The deployment verifies local HTTPS with hostname/certificate validation,
the login page and static assets, session cookie flags, protected routes,
HTTP redirects, and a Certbot renewal dry run. The public ACME challenge must
reach this host before certificate issuance. Certbot's existing timer is enabled,
and a domain-specific deployment hook validates/reloads Nginx after renewals.
Because [Nginx reloads start new workers asynchronously](https://nginx.org/en/docs/control.html),
the deployment waits up to 30 seconds per readiness check. It checks the exact
ACME token locally with the domain's Host header, then through public DNS,
and waits for HTTPS after the final reload. These probes bypass environment
HTTP proxies. A persistent failure identifies the local/public stage, URL,
and last HTTP status before rollback.

The destructive demo reset is blocked and unscheduled in production. No scheduler
cron job is installed. Sessions/cache use files and queues run synchronously.
Configuration caching is deliberately omitted because application code reads
environment variables outside configuration files. Mail defaults to the log
driver until delivery is configured. Login accounts and password hashes are
preserved from the dump; no administrator password is generated or reset.

## Validation

```sh
python3 -m unittest discover -s tests/deploy -v
vendor/bin/phpunit tests/Feature/ProductionDatabaseResetTest.php
vendor/bin/phpunit tests/Feature/SaleProductSelectionTest.php
npm run test:sale-selection
npm run test:sale-selection:browser
```

Run PHP tests with development dependencies and SQLite in an isolated checkout,
never against the deployed production database. The deployment uses Composer's
locked installation and validates it before application startup; the lock's
[content-hash calculation](https://github.com/composer/composer/blob/main/src/Composer/Package/Locker.php)
is unchanged by the syntax repair.

Useful server diagnostics:

```sh
docker compose -f /www/www/logicielyaar_site-wendkonta/deploy/compose.yaml logs --tail 100 php
tail -n 100 /www/logs/wendkonta.error.log
systemctl status certbot.timer
```

## Mobile/PWA update verification and release

The existing installation must be updated using the gated release command:

```sh
python3 deploy/release_mobile.py --test-and-release
```

When explicitly choosing to deploy without regression verification:

```sh
python3 deploy/release_mobile.py --skip-tests
```

This mode builds a new PHP image from the current checkout and does not require
or rerun test results. It retains server preflight, protected backups, the scoped
additive migration, dependency/platform checks, HTTPS checks and rollback handling.
Release metadata records regression verification as `skipped_by_request`; existing
test reports are not changed or presented as passing. The default gated mode
remains available when `--skip-tests` is omitted.

It runs PHP and real-browser tests in a disposable synthetic Docker stack before
changing the installation. The source fingerprint must match the passing result.
Production credentials, uploads and runtime files are retained; protected application
and database backups precede code changes. Only the additive operation-receipts
migration is applied. No production dump import or reset is performed.

To diagnose tests without updating production:

```sh
python3 deploy/test_mobile.py --keep
```

Results are written under ignored `test-results/mobile/`. A failed stack is retained
with `--keep`; the runner prints its temporary directory and Compose project.
The initial `deploy.py --resume` does not deploy mobile feature changes.
See [the implementation/testing report](../docs/mobile-pwa-implementation-report.md)
for actual validation status and remaining acceptance gaps.

A shorter diagnostic pass can target one browser or matching test titles:

```sh
python3 deploy/test_mobile.py --keep --project android-chromium
python3 deploy/test_mobile.py --keep --project ios-webkit --grep PWA
```

To rerun only the browser cases that failed in the latest run:

```sh
python3 deploy/test_mobile.py --keep --last-failed
```

This preserves the previous artifacts and restores Playwright's native
`.last-run.json` before using [its `--last-failed` option](https://playwright.dev/docs/test-cli).
Selection uses exact test IDs across browser projects, so a passing case in another
browser is skipped even if it has the same title. Previously passing Python, PHP,
and JavaScript suites are skipped; an isolated application/database is still set
up for the selected browser cases. A missing or empty failure list stops without
falling back to a full run. This mode cannot be combined with `--project`/`--grep`
and cannot authorize release; its report marks the skipped suites as `not_run`.

Filtered runs cannot authorize release. Successful runs clean up their isolated
stack even with `--keep`; failures retain it for diagnosis. The report records its
temporary directory and Compose project. Previous result directories are moved to
`test-results/mobile-history/` before another run, retaining their evidence.
The release also verifies the tested PHP image ID and restores the previous image
tag if its application rollback is needed.
