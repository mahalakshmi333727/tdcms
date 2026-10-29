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
> `mysql -u root -p tdcms < sql/migrations/2026_08_allow_null_password_hash.sql`
> `mysql -u root -p tdcms < sql/migrations/2026_08_widen_ip_address_columns.sql`
> `mysql -u root -p tdcms < sql/migrations/2026_08_add_excel_assignments.sql`
> `mysql -u root -p tdcms < sql/migrations/2026_10_sync_assignment_completion_prompt.sql`
> `mysql -u root -p tdcms < sql/migrations/2026_10_archive_assignments.sql`
> `mysql -u root -p tdcms < sql/migrations/2026_10_add_assignment_deadlines_and_admin_notifications.sql`
> `mysql -u root -p tdcms < sql/migrations/2026_10_add_business_card_management.sql`
> `mysql -u root -p tdcms < sql/migrations/2026_10_add_excel_assignment_review_fields.sql`
>
> For a live/hosted database, select the correct production database first
> (for example, in phpMyAdmin), back it up, and run only outstanding
> migrations in this order. The deadline/notification migration requires
> `assignments.archived_at`; it adds `assignments.due_at`, an index, and the
> `admin_notifications` table without changing existing assignment rows. It is
> additive but not safe to run twice: check for the column and table before
> applying it. Never import `schema.sql` into an existing production database;
> it recreates the tables.

The business-card migration requires the existing user and assignment tables,
including the `assignments.tags` column, and should be applied after the
deadline/notification migration. It creates business/content/user/import
tables and adds a nullable link to `assignments`; it preserves existing rows
and does not map historical assignments to businesses. Back up the database,
confirm each required table/column is absent, and run it only once. See
`docs/BUSINESS_CARD_MANAGEMENT.md` for the backup command, exact XML format,
count definitions, and import behavior.

The Excel-assignment review-field migration must be applied after the
`excel_assignments` table exists. Back up the database, confirm that
`excel_assignments.review_count` and `excel_assignments.reference_link` do not
already exist, and run the migration once. Existing records keep their file,
user, and status; their review count defaults to zero and their reference link
to `NULL`. New installs receive these columns from `sql/schema.sql`.

For a Windows XAMPP upgrade, first back up the target database, then apply
the migration from Command Prompt after the previously listed outstanding
migrations:

```bat
C:\xampp\mysql\bin\mysqldump.exe -u root -p tdcms > C:\secure-backups\tdcms-before-excel-review-fields.sql
C:\xampp\mysql\bin\mysql.exe -u root -p tdcms < sql\migrations\2026_10_add_excel_assignment_review_fields.sql
```

Enter the password at each prompt and verify the backup before proceeding.
Do not run the migration again if both columns already exist. Excel assignment
uploads now accept genuine `.xlsx` workbooks only; older stored `.xls`
assignments remain downloadable.
On the Admin Assignments page, **Create Assignment** opens the Excel assignment
form, and **Create Business** retains the business-card creation workflow.
Excel assignment records store an editable review count and optional HTTP(S)
reference link; use the Excel assignment edit form to update these values.

Create a dedicated, least-privilege MySQL user for the app instead of using
root:

```sql
CREATE USER 'tdcms_user'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON tdcms.* TO 'tdcms_user'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Configure the application

The application reads database settings from process environment variables.
No `.env` file is loaded automatically. Local XAMPP development keeps the
existing defaults (`127.0.0.1:3306`, database `tdcms`, user `root`, empty
password) when `APP_ENV` is unset or `development`.

For production, set `APP_ENV=production` and provide `DB_HOST`, `DB_PORT`,
`DB_NAME`, `DB_USER`, and a non-empty `DB_PASS` in the PHP process
environment. Production does not fall back to local XAMPP credentials.
Configure secrets in the hosting environment (for example, the PHP-FPM pool
or hosting control panel), not in source control or a web-accessible `.env`
file. If a hosting provider supplies a `.env` file, load it outside the
application using the host's supported mechanism; this project does not parse
`.env` files.

Set `APP_URL` to your full site URL with no trailing slash (for example,
`https://crm.example.com`) using the existing application configuration.
`APP_ENV=production` disables PHP error display while retaining server-side
error logging.

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

Direct localhost requests in development allow regular users to sign in
using only their username. Non-local sign-in continues to require a six-digit
email code. Username-only sign-in requires `APP_ENV=development`, a loopback
client, a local request host, and no forwarded-proxy headers or trusted
proxy configuration. A request to the server's own private IP is allowed
only when both the request host and direct client address match that server.
Other network clients must be individually configured using
`TDCMS_DEV_USERNAME_LOGIN_HOSTS` and `TDCMS_DEV_USERNAME_LOGIN_CLIENTS`.
Do not allowlist an entire private subnet.

When using the server's own LAN-IP URL, leave `APP_URL` unset in development
so generated links keep the hostname from the current request. For access
from another development device, configure its exact IP in
`TDCMS_DEV_USERNAME_LOGIN_CLIENTS` and add the app hostname to
`TDCMS_DEV_USERNAME_LOGIN_HOSTS`.

For non-local sign-in, configure a working PHP `mail()` transport and set
`MAIL_FROM_ADDRESS` to a valid sender address in the web server's environment.
Do not put mail credentials in source files. The application fails closed if
the sender is missing or PHP cannot queue the message. Codes expire after 10
minutes and can be attempted no more than five times per issued code.

New Admin accounts and Admin password resets require at least 12 characters.

## 5. File permissions

### Bulk task-source uploads

The Bulk Assign import form accepts up to 100 task-source files in one request.
The project `.htaccess` applies these values when XAMPP is using Apache PHP.
If the server uses CGI/FastCGI and `.htaccess` PHP settings are unavailable,
edit the active `php.ini` and restart Apache:

```ini
max_file_uploads = 100
upload_max_filesize = 3M
post_max_size = 512M
```

The application still enforces the maximum of 100 files and processes each
source file as task data; source files are not stored as employee attachments.

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
5. Log out, log in as the test user, use **Copy Text** and open the reference
   link (if present). Choose **No** in the completion popup and refresh to
   confirm the task remains pending. Reopen the task, choose **Yes**, refresh,
   and verify that it appears in Completed History and cannot be copied again.

If anything fails, check `logs/php_errors.log` first.

## 6. Optional: cron cleanup for rate_limits table

The `rate_limits` table grows slowly over time. A simple weekly cleanup:

```sql
DELETE FROM rate_limits WHERE window_start < NOW() - INTERVAL 7 DAY;
```
