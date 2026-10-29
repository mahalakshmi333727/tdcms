# TDCMS — Production Checklist

## Before go-live

- [ ] `config/config.php`: `APP_ENV = 'production'`, correct `APP_URL`
- [ ] `config/database.php`: real host/user/password/db name, least-privilege user
- [ ] `sql/schema.sql` applied; admin password hash regenerated and set
      (see Installation Guide step 4) — **do not ship the placeholder hash**
- [ ] PHP mail transport configured and `MAIL_FROM_ADDRESS` set; test delivery
      of regular-user sign-in codes
- [ ] HTTPS certificate installed and forced (redirect HTTP → HTTPS at the
      web server / load balancer level)
- [ ] `.htaccess` active (`AllowOverride All` on the vhost) — verify by
      confirming `curl -I https://your-domain/config/database.php`
      returns `403`, not `200`
- [ ] `logs/` and `uploads/` writable by the web server user, not
      world-writable
- [ ] Database backups scheduled and tested (restore a backup on a
      throwaway DB to confirm it actually works)
- [ ] Error display off, confirmed by triggering a deliberate error and
      checking the browser shows a generic message, not a stack trace

## Functional smoke test

- [ ] Admin can log in
- [ ] Admin can create a user
- [ ] Admin can assign text (single) to that user
- [ ] Admin can bulk-assign text to multiple users
- [ ] Admin can search/filter users and assignments
- [ ] Admin can export assignments to CSV and the file opens correctly
- [ ] Admin can view Activity Logs and Login Logs
- [ ] Admin can disable, re-enable, and delete a non-admin user
- [ ] Disabled user cannot log in
- [ ] Regular user sees only their own pending assignments
- [ ] Task text is returned only for an owned, pending task; a completed task
      rejects later copy requests through the server
- [ ] Completion prompt state survives refresh/login on another device; No
      leaves the task pending and Yes completes it once
- [ ] External reference URLs require server authorization; TDCMS cannot
      control copying from the external website itself
- [ ] Completed item appears in the user's Completed History and nowhere
      else
- [ ] User cannot edit or delete their own assignment (no UI path exists,
      and the AJAX endpoint rejects assignments not owned by them)
- [ ] Regular user can sign in only with a valid, unexpired email code
- [ ] 5 consecutive failed logins locks the account for the configured
      lockout window
- [ ] Session expires after the configured idle timeout

## Cross-browser / responsive check

- [ ] Chrome, Firefox, Safari, Edge — desktop
- [ ] Mobile Safari (iOS) and Chrome (Android) — dashboard, copy flow,
      and admin tables all usable at 375px width
- [ ] Tablet width (768–1024px) — sidebar/nav collapses correctly, tables
      scroll horizontally instead of breaking layout
- [ ] Dark mode toggle persists across page loads and looks correct on
      every screen (forms, tables, modals)

## Performance

- [ ] Pages using `admin_users` / `admin_assignments` / logs lists remain
      fast with a few thousand rows (pagination is in place; confirm
      indexes in `sql/schema.sql` match your actual query patterns after
      real-world usage)
- [ ] Static assets (CSS/JS) served with reasonable cache headers by your
      web server config (not covered by this app's PHP layer)
