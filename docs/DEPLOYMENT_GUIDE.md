# TDCMS — Deployment Guide

## Pre-deployment

1. Set the process environment variable `APP_ENV=production` (disables error
   display, still logs to `logs/php_errors.log`).
2. Confirm `APP_URL` matches your real domain exactly (scheme + host, no
   trailing slash).
3. Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` in the PHP
   process environment. Use a dedicated, least-privilege MySQL user (see
   Installation Guide). Production fails closed if credentials are missing;
   it does not use the local XAMPP defaults.
4. Serve exclusively over HTTPS. `session.cookie_secure` is automatically
   enabled in `config/config.php` when `$_SERVER['HTTPS']` is set, so
   cookies simply won't be sent over plain HTTP once you're on TLS —
   verify that behavior after cutover.
5. Run the CLI hash generator and set a strong administrator password
   before exposing the app publicly (see Installation Guide, step 4).

## Recommended production stack

- PHP-FPM behind Apache or Nginx (Apache with `mod_php` also works — the
  shipped `.htaccess` targets Apache)
- MySQL 8 with daily automated backups (`mysqldump` or a managed DB
  snapshot)
- A process for rotating `logs/php_errors.log` (e.g. `logrotate`)
- A firewall restricting direct MySQL port access to the app server only

## Zero-downtime-ish update process

1. Take and verify a database backup before applying migrations. On Windows,
   from Command Prompt:
   `C:\xampp\mysql\bin\mysqldump.exe -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME > C:\secure-backups\tdcms-before-update.sql`
   On Linux:
   `mysqldump -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME > /secure-backups/tdcms-before-update.sql`
   Replace the placeholders and enter the password at the prompt; do not put
   the password in the command.
2. Put the new code in a fresh directory alongside the old one.
3. Configure the production process environment with `APP_ENV=production`
   and the `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` values.
   Do not copy secrets into the code checkout.
4. **Before switching the new PHP code live**, apply outstanding migrations
   against the production database in order: category tags, nullable password
   hash, IP-address width, Excel assignments, completion prompt, and archive
   assignments, deadline/notifications, and finally business-card management.
   Apply only those not already installed. The deadline and notification
   migration requires the archive migration and adds a nullable
   `due_at` column, an overdue lookup index, and `admin_notifications`; it does
   not backfill deadlines for existing assignment rows. In Command Prompt on
   Windows, from the project directory, apply it only after confirming the
   prerequisite migrations:
   `C:\xampp\mysql\bin\mysql.exe -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < sql\migrations\2026_10_add_assignment_deadlines_and_admin_notifications.sql`
   On Linux:
   `mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < sql/migrations/2026_10_add_assignment_deadlines_and_admin_notifications.sql`
   Replace the connection placeholders and enter the password at the prompt;
   never put the password in the command or source files. Before running it,
   check `SHOW COLUMNS FROM assignments LIKE 'archived_at';`,
   `SHOW COLUMNS FROM assignments LIKE 'due_at';`, and
   `SHOW TABLES LIKE 'admin_notifications';`. Run only migrations whose
   resulting columns/tables are absent; these ALTER/CREATE statements are not
   idempotent. Verify `due_at` and `admin_notifications` after the migration.
   The business-card migration requires the existing user and assignment tables
   (including `assignments.tags`) and creates business/content/import/membership
   tables plus a nullable `assignments.business_content_id` relationship.
   After confirming none of the new tables/column exists, run:
   `C:\xampp\mysql\bin\mysql.exe -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < sql\migrations\2026_10_add_business_card_management.sql`
   from Command Prompt on Windows, or
   `mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < sql/migrations/2026_10_add_business_card_management.sql`
   on Linux. It preserves existing data and is not safe to run twice. See
   `docs/BUSINESS_CARD_MANAGEMENT.md` for backup and XML details.
   Never import `schema.sql` over a live database because it drops and
   recreates tables.
5. Swap the web server's document root / symlink to the new directory.
6. Smoke-test login, assignment creation, the employee task dashboard, and
   Admin → Assignments.

## Overdue notification scheduler (manual production setup)

The detector is CLI-only and exits with an error if its database configuration
is missing. Configure the CLI PHP process with the same `APP_ENV=production`,
`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` values as the
application. Do not put database passwords in task arguments, cron command
lines, or source control. The hosting/server administrator must configure and
verify this scheduler; it is not installed by a database migration or code
deployment.

### Windows Task Scheduler

1. Open **Task Scheduler** → **Create Task**. Use a service account with
   permission to run PHP and connect to the database; select **Run whether user
   is logged on or not**.
2. On **Triggers**, create a **Daily** trigger and set **Repeat task every:
   5 minutes** and **for a duration of: Indefinitely**.
3. On **Actions**, choose **Start a program**:
   - Program/script: `C:\xampp\php\php.exe`
   - Add arguments: `"C:\xampp\htdocs\tdcms\bin\check_overdue_assignments.php"`
   - Start in: `C:\xampp\htdocs\tdcms`
4. Ensure the task's account environment provides the production variables to
   CLI PHP. Apache/PHP-FPM variables are not necessarily inherited by Task
   Scheduler. Store secrets only in the host's protected environment/config.
5. Run the task once manually, confirm **Last Run Result** is `0x0`, and check
   the PHP error log and `admin_notifications` table. A successful run with no
   newly overdue work can produce no console output.

### Linux/hosting cron

Configure the cron account's protected environment with the production
variables, then add this entry (replace the project and PHP paths):

```cron
*/5 * * * * cd /path/to/tdcms && /usr/bin/php bin/check_overdue_assignments.php >> /path/to/tdcms/logs/overdue-check.log 2>&1
```

Run the command once as the cron account before relying on the schedule, and
verify its exit status and logs. Confirm that the account's CLI PHP process
actually receives the database environment; web-worker configuration alone
may not apply to cron.

## Environment checklist

- [ ] Process environment `APP_ENV=production`
- [ ] Real `DB_HOST` / `DB_PORT` / `DB_USER` / `DB_PASS` / `DB_NAME` set in PHP's process environment
- [ ] `APP_URL` correct
- [ ] HTTPS certificate valid and auto-renewing (e.g. Let's Encrypt + certbot)
- [ ] `mod_rewrite` and `mod_headers` enabled, `AllowOverride All` set
- [ ] `logs/` and `uploads/` writable by the web server user only
- [ ] Default admin password changed from the value you set during install
- [ ] Database backups scheduled
- [ ] `logs/php_errors.log` monitored or shipped to a log aggregator
