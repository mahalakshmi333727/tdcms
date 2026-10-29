# TDCMS — Deployment Guide

## Pre-deployment

1. Set `APP_ENV` to `'production'` in `config/config.php` (disables error
   display, still logs to `logs/php_errors.log`).
2. Confirm `APP_URL` matches your real domain exactly (scheme + host, no
   trailing slash).
3. Use a dedicated, least-privilege MySQL user (see Installation Guide).
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

1. Take a database backup: `mysqldump tdcms > backup_$(date +%F).sql`
2. Put the new code in a fresh directory alongside the old one.
3. Copy `config/database.php`'s real credentials into the new checkout
   (don't overwrite it with a template).
4. Run any new SQL migrations (this project ships a single `schema.sql`;
   for incremental changes, add versioned `.sql` files under `sql/` and
   apply the delta).
5. Swap the web server's document root / symlink to the new directory.
6. Smoke-test login, assignment creation, and the copy workflow.

## Environment checklist

- [ ] `APP_ENV = 'production'`
- [ ] Real `DB_HOST` / `DB_USER` / `DB_PASS` / `DB_NAME` set
- [ ] `APP_URL` correct
- [ ] HTTPS certificate valid and auto-renewing (e.g. Let's Encrypt + certbot)
- [ ] `mod_rewrite` and `mod_headers` enabled, `AllowOverride All` set
- [ ] `logs/` and `uploads/` writable by the web server user only
- [ ] Default admin password changed from the value you set during install
- [ ] Database backups scheduled
- [ ] `logs/php_errors.log` monitored or shipped to a log aggregator
