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
| status | ENUM('pending','completed') | |
| assigned_by | INT UNSIGNED, FK → users.id | admin who created it |
| assigned_at | DATETIME | |
| completed_at | DATETIME NULL | set atomically on copy |
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

## Relationships

```
users (1) ───< (many) assignments        [user_id]
users (1) ───< (many) assignments        [assigned_by]
users (1) ───< (many) activity_logs      [user_id, nullable]
users (1) ───< (many) login_logs         [user_id, nullable]
```

`assignments.user_id` cascades on delete (deleting a user removes their
assignment history); `assignments.assigned_by` is `RESTRICT` (an admin
account can't be deleted while it still has assignments on record — admin
accounts are protected from deletion anyway, see `models/User.php::delete()`).
