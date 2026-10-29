# TDCMS — Text Distribution & Copy Management System

A Core PHP 8 + MySQL 8 web application for distributing reusable text
snippets (and optional reference links) from an administrator to authorized
users. Employees explicitly confirm task completion, which is synchronized
through the database across sessions and devices.

Built with HTML5, CSS3, Bootstrap 5, vanilla JavaScript (ES6), and Core
PHP — no PHP or JS framework.

## Quick start

See **[docs/INSTALLATION_GUIDE.md](docs/INSTALLATION_GUIDE.md)** for full
setup steps. Short version:

```bash
mysql -u root -p < sql/schema.sql
php database/generate_admin_hash.php "YourStrongPassword123"
# paste the resulting hash into: UPDATE users SET password_hash = '...' WHERE username = 'admin';
```

Then edit `config/database.php` and `config/config.php` with your real DB
credentials and site URL, point your web server's document root at this
folder, and visit the site.

Admins sign in with their username and password. In development, regular
users on direct localhost requests or direct requests from the server to its
own private LAN address can sign in with their username only. Other LAN
clients must be individually allowlisted; email-code verification remains
required elsewhere. New or reset Admin passwords must be at least 12
characters.

## Documentation

| Document | Purpose |
|---|---|
| [Installation Guide](docs/INSTALLATION_GUIDE.md) | Step-by-step setup |
| [Deployment Guide](docs/DEPLOYMENT_GUIDE.md) | Going to production, updates |
| [Project Documentation](docs/PROJECT_DOCUMENTATION.md) | Architecture, folder structure, request flow |
| [Database Schema](docs/DATABASE_SCHEMA.md) | Table-by-table reference |
| [Security Checklist](docs/SECURITY_CHECKLIST.md) | What's implemented + operational responsibilities |
| [Production Checklist](docs/PRODUCTION_CHECKLIST.md) | Pre-launch verification steps |

## Default login

After running the install steps above: username `admin`, password is
whatever you passed to `generate_admin_hash.php`. Change the account email
after first login.

## Folder structure

```
config/  database/  controllers/  models/  views/  layouts/
includes/  helpers/  middleware/  assets/  uploads/  logs/  sql/  ajax/
index.php  .htaccess  docs/
```

See `docs/PROJECT_DOCUMENTATION.md` for what lives where.
