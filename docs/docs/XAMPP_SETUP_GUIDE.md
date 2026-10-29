# TDCMS — Running on XAMPP (Local Development)

This guide gets the app running smoothly on a stock XAMPP install
(Windows, macOS, or Linux). The app already ships configured with XAMPP's
default MySQL credentials (`root` / no password), so in most cases you
only need steps 1–5 below.

## 1. Copy the project into `htdocs`

| OS | Path |
|---|---|
| Windows | `C:\xampp\htdocs\tdcms` |
| macOS | `/Applications/XAMPP/htdocs/tdcms` |
| Linux | `/opt/lampp/htdocs/tdcms` |

The folder containing `index.php` should sit directly inside `htdocs` as
`tdcms` — that gives you `http://localhost/tdcms/`. In local development,
leave `APP_URL` unset so links use the host from the current request. If you
rename the folder, set `APP_URL` to match.

## 2. Start Apache and MySQL

Open the **XAMPP Control Panel** and click **Start** next to both
**Apache** and **MySQL**. Both rows should turn green.

If Apache won't start, something else is likely using port 80 (Skype, IIS,
or another web server). Either stop that process or change Apache's port
in XAMPP Control Panel → **Config → Apache (httpd.conf)** — if you change
the port, also update `APP_URL` accordingly (e.g. `http://localhost:8080/tdcms`).

## 3. Enable `.htaccess` support (`AllowOverride All`)

This is the #1 XAMPP gotcha: by default, XAMPP's Apache config ignores
`.htaccess` files, which means this app's security rules (blocking direct
access to `config/`, `models/`, etc.) and CSP/security headers silently
won't apply — the app will still *run*, but without that protection layer.

Open `C:\xampp\apache\conf\httpd.conf` (or the equivalent path on
macOS/Linux) and find the `<Directory "C:/xampp/htdocs">` block. Change:

```apache
AllowOverride None
```

to:

```apache
AllowOverride All
```

Save the file and restart Apache from the Control Panel.

## 4. Create the database

Easiest path — via phpMyAdmin:

1. Open `http://localhost/phpmyadmin`
2. Click **Import**
3. Choose the file `sql/schema.sql` from the project folder
4. Click **Go**

This creates the `tdcms` database, all tables, and a placeholder admin row.

Alternatively, from a terminal (Windows: use the "Shell" button in the
Control Panel, or run from `C:\xampp\mysql\bin`):

```bash
mysql -u root < sql/schema.sql
```

## 5. Set the real administrator password

The seeded admin row has a placeholder password hash that won't match
anything. Generate a real one using XAMPP's bundled PHP:

**Windows:**
```bat
C:\xampp\php\php.exe database\generate_admin_hash.php "YourStrongPassword123"
```

**macOS/Linux:**
```bash
/Applications/XAMPP/bin/php database/generate_admin_hash.php "YourStrongPassword123"
```

Copy the hash it prints, then in phpMyAdmin → `tdcms` database → `users`
table → SQL tab, run:

```sql
UPDATE users SET password_hash = '<paste-hash-here>' WHERE username = 'admin';
```

## 6. Visit the app

Go to `http://localhost/tdcms/` and log in with username `admin` and the
password you just set.

## Configuration notes for XAMPP

- Regular users can sign in with only a username from `localhost` and
  `127.0.0.1` in development. Accessing the app on its own private LAN
  address also works when the request originates from that same server.
  Other LAN devices are not trusted automatically.
- To allow another specific development device, configure
  `TDCMS_DEV_USERNAME_LOGIN_HOSTS` with the app host(s) and
  `TDCMS_DEV_USERNAME_LOGIN_CLIENTS` with that device's exact client IP.
  Do not allowlist an entire private subnet. These settings are ignored
  outside `APP_ENV=development`; forwarded/proxied requests are denied.
- In development, leave `APP_URL` unset so the app uses the current local
  hostname in links and login requests. If using a fixed `APP_URL`, it must
  match the hostname in use.
- Non-local user sign-in uses a six-digit email code. XAMPP's PHP `mail()`
  is not configured for delivery by default. Configure a mail transport in
  `php.ini` and set `MAIL_FROM_ADDRESS` in the Apache process environment
  before testing that flow.
- `config/database.php` already uses XAMPP's defaults: host `127.0.0.1`,
  user `root`, empty password, database `tdcms`. If you've set a MySQL
  root password in XAMPP's Security console, update `DB_PASS` to match.
- `config/config.php` defaults `APP_ENV` to `'development'` so PHP errors
  are visible while you're setting things up locally. **Switch it to
  `'production'`** before deploying anywhere real — see
  `docs/DEPLOYMENT_GUIDE.md`.
- No HTTPS is required locally — the app detects `$_SERVER['HTTPS']` and
  only marks cookies `Secure` when it's actually present, so plain
  `http://localhost` works fine for local testing.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Blank white page | `AllowOverride All` not set (step 3), or a PHP fatal error — check `logs/php_errors.log` |
| "403 Forbidden" on every page | `.htaccess` not being read (`AllowOverride None`) or the site isn't under `htdocs` |
| "SQLSTATE[HY000] [1045] Access denied" | MySQL root password doesn't match `DB_PASS` in `config/database.php` |
| "SQLSTATE[HY000] [2002]" / can't connect | MySQL isn't running in the Control Panel, or a custom port is set in `my.ini` and doesn't match `DB_PORT` |
| Login always says "Invalid username or password" | Admin password hash wasn't updated (step 5) — you're still on the placeholder hash |
| Assets (CSS/JS) look broken but page loads | Double-check `APP_URL` in `config/config.php` matches the actual folder name under `htdocs` |
