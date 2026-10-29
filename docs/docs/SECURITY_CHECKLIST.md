# TDCMS — Security Checklist

## Implemented in this codebase

- [x] **PDO only, prepared statements everywhere** — no query in
      `models/` or `controllers/` concatenates user input into SQL.
- [x] **CSRF protection** — every state-changing form/AJAX call carries a
      per-session token (`includes/csrf.php`), verified with
      `hash_equals()` before any write happens.
- [x] **XSS / output escaping** — all dynamic output in views goes through
      `e()` (`htmlspecialchars` with `ENT_QUOTES`); assignment text is
      rendered escaped inside a `<div>`, never as raw HTML.
- [x] **Input validation** — usernames, emails, URLs, password strength,
      and text length are validated server-side in the controllers before
      any DB write (client-side `required`/`pattern` attributes are UX
      only, not the security boundary).
- [x] **Password hashing** — bcrypt via PHP's `password_hash()` /
      `password_verify()` (`PASSWORD_DEFAULT`), never plaintext or
      reversible encryption.
- [x] **Secure sessions** — `session.cookie_httponly`, `cookie_samesite=Strict`,
      `cookie_secure` (auto-enabled under HTTPS), strict mode, and a
      custom session name (`config/config.php`, `includes/auth.php`).
- [x] **Session regeneration** — session ID rotates on login
      (`login_user()`) and periodically during a session
      (`SESSION_REGEN_INTERVAL`), mitigating session fixation.
- [x] **Session timeout** — idle sessions expire after
      `SESSION_TIMEOUT_SECONDS` (default 15 min); enforced server-side in
      `secure_session_start()`.
- [x] **Admin login attempt lockout** — admin accounts lock for
      `LOGIN_LOCKOUT_MINUTES` after `MAX_LOGIN_ATTEMPTS` consecutive
      failures (`User::recordFailedAttempt()`).
- [x] **Rate limiting** — IP-based throttle on login, per-user throttle on
      verification-code issuance, and verification attempts
      (`rate_limit_check()`, backed by the `rate_limits` table).
- [x] **Regular-user sign-in verification** — one-time email codes are
      password-hashed in the session, expire after 10 minutes, and are
      limited to five attempts per issued code outside direct development
      localhost requests. Username-only sign-in is restricted to
      `APP_ENV=development`, loopback clients/local hostnames, or an exact
      server-self private-IP request. Other network clients require explicit
      host and exact-client allowlisting; forwarded requests and configured
      trusted proxies are denied. Admins continue to use password verification.
- [x] **Role-based access control** — `middleware/require_admin.php`
      gates every admin route; regular users cannot reach admin
      controllers even by guessing URLs, and server-side ownership checks
      (`Assignment::markCompleted`) prevent a user from acting on another
      user's data.
- [x] **Server-side authorization on every write** — completion,
      deletion, and status changes are all re-checked against the
      database, never trusted from client state.
- [x] **Security headers** — `X-Content-Type-Options`, `X-Frame-Options`,
      `Referrer-Policy`, and a `Content-Security-Policy` are set in
      `includes/bootstrap.php` (and mirrored in `.htaccess` for
      `X-Content-Type-Options` / `X-Frame-Options` as defense in depth).
- [x] **Directory hardening** — `.htaccess` denies direct HTTP access to
      `config/`, `includes/`, `models/`, `controllers/`, `middleware/`,
      `layouts/`, `logs/`, `sql/`, `database/`; `uploads/.htaccess`
      additionally disables script execution in that folder.
- [x] **Error handling** — `display_errors` off in production; errors are
      logged to `logs/php_errors.log`, never shown to the browser.
- [x] **Least-privilege DB user** — documented in the Installation Guide
      (`SELECT, INSERT, UPDATE, DELETE` only — no `DROP`/`ALTER`/`GRANT`).

## Operational items (your responsibility at deploy time)

- [ ] Change the default admin password immediately after install.
- [ ] Configure PHP mail delivery and `MAIL_FROM_ADDRESS`; verify regular-user
      code delivery before enabling user sign-in.
- [ ] Serve over HTTPS only; never expose this app on plain HTTP in
      production.
- [ ] Keep PHP and MySQL patched to their latest stable minor versions.
- [ ] Restrict MySQL network access to the application server.
- [ ] Rotate the CSRF/session secrets implicitly by clearing sessions if
      you ever suspect compromise (`session.save_path` cleanup).
- [ ] Review `logs/php_errors.log` and the `login_logs` / `activity_logs`
      tables periodically for anomalies (repeated lockouts, unusual IPs).
- [ ] Set a real Content-Security-Policy `report-uri`/`report-to` if you
      want CSP violation telemetry.
- [ ] If you add file uploads beyond what's scaffolded, validate MIME
      type + extension allow-lists and keep `uploads/.htaccess` script
      execution disabled.
