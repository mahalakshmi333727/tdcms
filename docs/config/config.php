<?php
/**
 * Application Configuration
 * TDCMS - Text Distribution & Copy Management System
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Environment
// ---------------------------------------------------------------------
define('APP_ENV', 'development');          // 'development' locally (e.g. XAMPP) | 'production' when deployed for real
define('APP_NAME', 'TDCMS');
define('APP_URL', 'http://localhost/tdcms'); // change to your deployment URL
define('APP_ROOT', dirname(__DIR__));

// ---------------------------------------------------------------------
// Error reporting (never expose errors to users in production)
// ---------------------------------------------------------------------
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/logs/php_errors.log');

// ---------------------------------------------------------------------
// Session security (must run before any session_start())
// ---------------------------------------------------------------------
define('SESSION_TIMEOUT_SECONDS', 900);     // 15 minutes idle timeout
define('SESSION_REGEN_INTERVAL', 300);      // regenerate session id every 5 min

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
ini_set('session.gc_maxlifetime', (string) SESSION_TIMEOUT_SECONDS);

// ---------------------------------------------------------------------
// Security / Auth tuning
// ---------------------------------------------------------------------
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('RATE_LIMIT_WINDOW_SECONDS', 60);
define('RATE_LIMIT_MAX_HITS', 30); // generic AJAX endpoint throttle
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);

//   define('TRUSTED_PROXIES', ['127.0.0.1', '::1']);
define('TRUSTED_PROXIES', []);
// ---------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------
define('DEFAULT_PAGE_SIZE', 20);

// ---------------------------------------------------------------------
// Timezone
// ---------------------------------------------------------------------
date_default_timezone_set('Asia/Kolkata');
