# TDCMS — Installation Guide

## Requirements

- PHP 8.1+ with extensions: `pdo_mysql`, `mbstring`, `openssl`, `json`,
  `zip`, `simplexml` (the last two are needed for the .xlsx bulk-assign
  import feature — both are enabled by default in XAMPP)
- MySQL 8.0+ (or MariaDB 10.6+)
- Apache 2.4+ with `mod_rewrite` and `mod_headers` enabled
- HTTPS certificate for production (required for secure cookies)

## 1. Get the files onto your server

Copy the entire project folder to your web root, e.g. `/var/www/tdcms`, or a
subfolder if you're hosting multiple apps. The **document root should point
at the project folder itself** (the one containing `index.php`), not a
`public/` subfolder — this project keeps `index.php` at the root by design.

## 2. Create the database

```bash
mysql -u root -p < sql/schema.sql
```

This creates the `tdcms` database, all tables, indexes, and foreign keys,
and seeds one placeholder administrator row (you'll set its real password in
step 4).

> **Already installed TDCMS before?** Fresh installs get every column from
> `sql/schema.sql` automatically — skip this. If you're upgrading an
> existing database, run the migration instead:
> `mysql -u root -p tdcms < sql/migrations/2026_07_add_category_tags_to_assignments.sql`

Create a dedicated, least-privilege MySQL user for the app instead of using
root:

```sql
CREATE USER 'tdcms_user'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON tdcms.* TO 'tdcms_user'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Configure the application

Edit `config/database.php` and set:
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`

Edit `config/config.php` and set:
- `APP_ENV` → `'production'` for live deployments (disables error display)
- `APP_URL` → your full site URL, no trailing slash (e.g. `https://crm.example.com`)
- Time zone, session timeout, and rate-limit values if you want different defaults

## 4. Set the administrator password

The seed row in `sql/schema.sql` ships with a placeholder hash that will
**not** match any real password. Generate a real one from the command line:

```bash
php database/generate_admin_hash.php "YourStrongPassword123"
```

Copy the printed hash and apply it:

```sql
UPDATE users SET password_hash = '<paste-hash-here>' WHERE username = 'admin';
```

Log in with username `admin` and your chosen password, then immediately
change the email address on the account to a real one you control.

New Admin accounts and Admin password resets require a password of at least
12 characters. Direct localhost requests in development allow regular users
to sign in using only their username; non-local sign-in continues to require
an email verification code. The same-host private IP exception requires the
request host and direct client address to match the server address. Other
network clients must be individually configured; do not allowlist a subnet.

## Configure regular-user sign-in email

For non-local sign-in, configure a working PHP `mail()` transport and set
`MAIL_FROM_ADDRESS` in the web server's environment to a valid sender
address. Do not put mail credentials in source files. XAMPP does not deliver
email automatically; configure its PHP mail/sendmail settings before testing.
The application fails closed when the sender is not configured or mail
cannot be queued. Codes expire after 10 minutes and allow five attempts.
Username-only sign-in requires `APP_ENV=development`, a loopback client, a
local request/configured host, and no forwarded-proxy headers or trusted
proxy configuration.

## 5. File permissions

```bash
chmod -R 750 config includes controllers models middleware helpers
chmod -R 770 logs uploads
chown -R www-data:www-data /var/www/tdcms
```

## 6. Apache virtual host

```apache
<VirtualHost *:443>
    ServerName crm.example.com
    DocumentRoot /var/www/tdcms

    <Directory /var/www/tdcms>
        AllowOverride All
        Require all granted
    </Directory>

    SSLEngine on
    SSLCertificateFile      /etc/ssl/certs/crm.example.com.crt
    SSLCertificateKeyFile   /etc/ssl/private/crm.example.com.key
</VirtualHost>
```

Make sure `AllowOverride All` is set so the shipped `.htaccess` (routing +
security headers) actually takes effect.

## 7. Verify the install

1. Visit `https://crm.example.com/` — you should land on the login page.
2. Log in as `admin`.
3. Go to **Users → New User** and create a test user.
4. Go to **Assign Text** and assign a snippet to that user.
5. Log out, log in as the test user with the email verification code, click
   **Copy Text**, confirm the popup appears and the item disappears from
   Pending and shows in History.

If anything fails, check `logs/php_errors.log` first.

## 6. Optional: cron cleanup for rate_limits table

The `rate_limits` table grows slowly over time. A simple weekly cleanup:

```sql
DELETE FROM rate_limits WHERE window_start < NOW() - INTERVAL 7 DAY;
```
