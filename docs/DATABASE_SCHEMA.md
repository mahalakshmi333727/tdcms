# TDCMS — Database Schema

Full DDL lives in `sql/schema.sql`. Summary below.

## `users`

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED, PK | |
| full_name | VARCHAR(120) | |
| username | VARCHAR(60) | unique, lowercase alnum/._- |
| email | VARCHAR(150) | unique |
| password_hash | VARCHAR(255) | bcrypt via `password_hash()` |
| role | ENUM('admin','user') | RBAC gate |
| status | ENUM('active','disabled') | disabled users cannot log in |
| failed_attempts | TINYINT UNSIGNED | resets on successful login |
| locked_until | DATETIME NULL | set after `MAX_LOGIN_ATTEMPTS` failures |
| last_login_at | DATETIME NULL | |
| password_changed_at | DATETIME NULL | |
| created_at / updated_at | DATETIME | |

## `assignments`

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED, PK | |
| user_id | INT UNSIGNED, FK → users.id | recipient |
| title | VARCHAR(150) NULL | optional label |
| text_content | TEXT | the distributed text |
| reference_link | VARCHAR(500) NULL | optional, http(s) only |
| text_copied / link_clicked | TINYINT(1) | task progress flags |
| status | ENUM('pending','completed') | |
| completion_prompt_pending | TINYINT(1) | unresolved completion decision, synchronized through the database |
| archived_at | DATETIME NULL | assignment is hidden from active lists while preserving completion history |
| assigned_by | INT UNSIGNED, FK → users.id | admin who created it |
| assigned_at | DATETIME | |
| completed_at | DATETIME NULL | set atomically on explicit completion |
| ip_on_completion | VARCHAR(45) NULL | audit trail |

## `activity_logs`

General admin/system audit trail: `user_id`, `action`, `description`,
`ip_address`, `user_agent`, `created_at`.

## `login_logs`

Every login attempt: `user_id` (nullable — unknown usernames still log),
`username`, `status` (`success`/`failed`/`locked`), `ip_address`,
`user_agent`, `created_at`.

## `rate_limits`

Generic throttle bucket used by `rate_limit_check()`: `bucket_key` (unique),
`hits`, `window_start`. Used for both login-by-IP and per-user AJAX
throttling.

## Business assignees

`business_users` stores the current assignee for each business. The unique
`business_id` key allows at most one current user per business.
`business_user_history` retains replaced memberships, including their
assignment period and the administrator who made the change.

Replacing a business assignee transfers pending, non-archived business
assignments to the new user and resets their progress. Completed assignments
remain with their original user as history. The migration
`sql/migrations/2026_10_enforce_single_business_assignee.sql` archives legacy
extra memberships before retaining one current assignee.

## Relationships

```
users (1) ───< (many) assignments        [user_id]
users (1) ───< (many) assignments        [assigned_by]
users (1) ───< (many) activity_logs      [user_id, nullable]
users (1) ───< (many) login_logs         [user_id, nullable]
businesses (1) ─── (0..1) users           [business_users]
```

`assignments.user_id` cascades on delete (deleting a user removes their
assignment history); `assignments.assigned_by` is `RESTRICT` (an admin
account can't be deleted while it still has assignments on record — admin
accounts are protected from deletion anyway, see `models/User.php::delete()`).

Each assignment row is also its single completion record. Completion is
performed with a row lock and a conditional pending-state update, so concurrent
confirmations cannot create a second completion record. The completion popup flag remains set when the employee chooses "No" and is
cleared only when the assignment is completed. Dashboard and status queries
restore this flag from the database for the authenticated employee.

Assignments are archived by setting `archived_at`, not permanently deleted.
Their completion state and activity logs remain available for historical
integrity; active dashboards, lists, exports, and task counts omit archived
rows. Editing a pending assignment's employee, text, or reference link resets
its progress so completion of the previous assignment version cannot carry
over. Completed assignments cannot be reassigned.
