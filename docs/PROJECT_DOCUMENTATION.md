# TDCMS — Project Documentation

## What this application does

TDCMS is a secure internal tool for an administrator to distribute
reusable text (templates, standard responses, notes) to individual users,
optionally paired with a reference link. Users log in, see their pending items, request their task text from the
server when copying, and explicitly confirm completion in a popup. Task
state and unresolved popup state are stored on the assignment row, so they
follow the employee across browsers and devices.

## Architecture

Core PHP, no framework, organized in a light MVC-style layout:

```
config/        Environment + database configuration
includes/      Bootstrap, session/auth helpers, CSRF, generic utilities
helpers/       Pure validation helpers
middleware/    require_login.php / require_admin.php — included at the
               top of any protected route
models/        User, Assignment, ActivityLog, LoginLog — all DB access
               goes through these via PDO prepared statements
controllers/   Request handlers for state-changing actions (POST)
views/         Presentation templates, split into auth/ user/ admin/
layouts/       Shared header/footer chrome
assets/        CSS, JS, images
ajax/          Thin AJAX-only entry points (currently: copy_text.php)
sql/           schema.sql — full DDL + seed data
database/      generate_admin_hash.php — CLI install helper
uploads/       Reserved for future file-attachment support (currently unused,
               locked down against script execution)
logs/          PHP error log destination
index.php      Front controller — all requests route through here
```

### Request flow

1. `.htaccess` routes every request that isn't a real file/directory to
   `index.php`.
2. `index.php` loads `includes/bootstrap.php` (config, DB connection,
   session, security headers).
3. `?action=X` requests are state-changing: routed to a controller function,
   which validates CSRF, checks auth/role, does the work, and redirects or
   returns JSON.
4. `?page=X` requests are read-only views: middleware checks are applied
   per-page (`require_login`, `require_admin` where relevant), then the
   matching file in `views/` is rendered inside `layouts/header.php` and
   `layouts/footer.php`.

### The copy → reference → completion workflow

1. User clicks **Copy Text** on a pending assignment card.
2. The server locks the assignment row and returns its text only when the
   assignment belongs to the logged-in user and remains pending. The browser
   then copies that response; task text is not embedded in the dashboard HTML.
3. Opening a reference link is similarly authorized by the server. Once the
   required steps are reached, the server stores an unresolved completion
   prompt on the assignment row.
4. The **Yes, Task Completed** response atomically changes that same row to
   completed; the **No, Task Not Completed** response leaves both the task and
   its unresolved prompt pending. The follow-up **Are you sure?** dialog's
   **Yes** dismisses the completion prompt for the current page session and
   returns focus to the same review content; its **No** closes only the
   follow-up dialog and leaves the original prompt open. Neither choice in
   that follow-up dialog changes assignment state. A single assignment row
   and conditional update prevent duplicate completion records, including
   concurrent confirmations.
5. Dashboard state is refreshed from the server and unresolved prompts are
   restored after login or page refresh on another device.

The server can block further copies through TDCMS after completion. It cannot
control copying from an external reference website after the employee has
opened that site; external-site access and clipboard behavior are outside the
application's control.

## Roles

- **admin** — full access: user management, assignment management (single +
  bulk), activity/login log viewing, CSV export.
- **user** — sees only their own pending/completed assignments; cannot
  edit, delete, or change status of anything; can change their own
  password.

## Data model summary

See `docs/DATABASE_SCHEMA.md` for full column-level detail. Tables:
`users`, `assignments`, `activity_logs`, `login_logs`, `rate_limits`.

## Bulk assign: manual entry vs. file import

The Bulk Assign screen (`views/admin/bulk_assign.php`) has two tabs that
share the same user-selection checklist:

- **Manual Entry** — one task (title, category, text, link, tags) assigned
  to every selected user. Backed by `Assignment::bulkCreate()`.
- **Import File** — upload a `.json`, `.xlsx`, or `.csv` file describing
  **multiple** tasks; every task in the file is assigned to every selected
  user (tasks × users). Backed by `Assignment::bulkCreateFromTasks()`.

Accepted row/column keys (case-insensitive): `title`, `category`,
`content` (required — also accepts `text_content` or `text`),
`reference_link` (also accepts `link` or `url`), `tags` (comma/pipe
separated, or a JSON array).

File parsing lives in `helpers/task_import.php` (JSON + CSV, both using
PHP's built-in `json_decode`/`fgetcsv`) and `helpers/xlsx_reader.php` — a
**native** `.xlsx` reader built on `ZipArchive` + `SimpleXML` only, since
this project intentionally avoids Composer/PhpSpreadsheet. It reads the
first worksheet only and expects a header row. Requires the PHP `zip` and
`simplexml` extensions (both enabled by default in XAMPP and most
distro PHP packages).

Every (user, task) pair still goes through the same pending-duplicate
check described above, so re-importing the same file twice won't create
repeats. Imports are capped at 500 rows and 3 MB per file
(`TASK_IMPORT_MAX_ROWS` in `helpers/task_import.php`).

Sample templates for admins: `assets/samples/sample_tasks.json`,
`sample_tasks.xlsx`, `sample_tasks.csv` (linked from the Import File tab).

## Extending the app

- **Email notifications on assignment**: hook into
  `Assignment::create()` / `bulkCreate()` in `controllers/admin_controller.php`.
- **File attachments per assignment**: the `uploads/` folder and DB schema
  can be extended with an `attachments` table; keep the existing
  script-execution lockdown in `uploads/.htaccess`.
- **API access**: wrap the model layer with a small REST controller under
  `/api` — the models already return plain arrays, so this is
  straightforward.
