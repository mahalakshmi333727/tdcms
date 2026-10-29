<?php
/**
 * Application Configuration
 * TDCMS - Text Distribution & Copy Management System
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Environment
// ---------------------------------------------------------------------
$appEnvironment = getenv('APP_ENV');
define('APP_ENV', $appEnvironment !== false && trim($appEnvironment) !== ''
    ? strtolower(trim($appEnvironment))
    : 'development');
define('APP_NAME', 'TDCMS');

$developmentLoginHosts = getenv('TDCMS_DEV_USERNAME_LOGIN_HOSTS');
define(
    'DEV_USERNAME_LOGIN_HOSTS',
    $developmentLoginHosts === false || trim($developmentLoginHosts) === ''
        ? ['localhost', '127.0.0.1', '::1']
        : array_values(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host, " \t\n\r\0\x0B[]")),
            explode(',', $developmentLoginHosts)
        )))
);
$developmentLoginClients = getenv('TDCMS_DEV_USERNAME_LOGIN_CLIENTS');
define(
    'DEV_USERNAME_LOGIN_CLIENTS',
    $developmentLoginClients === false || trim($developmentLoginClients) === ''
        ? []
        : array_values(array_filter(array_map('trim', explode(',', $developmentLoginClients))))
);

if (!defined('APP_URL')) {
    $configuredAppUrl = getenv('APP_URL');
    $requestHost = parse_url('http://' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    $requestHost = is_string($requestHost) ? strtolower(trim($requestHost, '[]')) : '';
    $remoteAddress = strtolower((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $serverAddress = strtolower((string) ($_SERVER['SERVER_ADDR'] ?? ''));
    $localRequestForBaseUrl = APP_ENV === 'development'
        && empty($_SERVER['HTTP_FORWARDED'])
        && empty($_SERVER['HTTP_X_FORWARDED_FOR'])
        && empty($_SERVER['HTTP_X_FORWARDED_HOST'])
        && empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && empty($_SERVER['HTTP_X_FORWARDED_PORT'])
        && empty($_SERVER['HTTP_X_REAL_IP'])
        && empty($_SERVER['HTTPS'])
        && (
            (in_array($requestHost, DEV_USERNAME_LOGIN_HOSTS, true)
                && (
                    in_array($remoteAddress, ['127.0.0.1', '::1'], true)
                    || in_array($remoteAddress, DEV_USERNAME_LOGIN_CLIENTS, true)
                ))
            || (
                $requestHost !== ''
                && $requestHost === $serverAddress
                && $remoteAddress === $serverAddress
                && filter_var($serverAddress, FILTER_VALIDATE_IP) !== false
                && filter_var($serverAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            )
        );
    if ($localRequestForBaseUrl && isset($_SERVER['HTTP_HOST'])) {
        $host = (string) $_SERVER['HTTP_HOST'];
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($scriptDir === '.' || $scriptDir === '/') {
            $scriptDir = '';
        }
        define('APP_URL', 'http://' . $host . $scriptDir);
    } elseif ($configuredAppUrl !== false && trim($configuredAppUrl) !== '') {
        define('APP_URL', rtrim(trim($configuredAppUrl), '/'));
    } elseif (PHP_SAPI === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
        define('APP_URL', 'http://localhost/tdcms');
    } else {
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
        $scheme = $isHttps ? 'https' : 'http';
        $host = (string) $_SERVER['HTTP_HOST'];
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($scriptDir === '.' || $scriptDir === '/') {
            $scriptDir = '';
        }
        define('APP_URL', $scheme . '://' . $host . $scriptDir);
    }
}

define('APP_ROOT', dirname(__DIR__));
define('UPLOADS_DIR', APP_ROOT . '/uploads');

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
define('PASSWORD_MIN_LENGTH', 12);
$mailFromAddress = getenv('MAIL_FROM_ADDRESS');
define('MAIL_FROM_ADDRESS', $mailFromAddress !== false ? trim($mailFromAddress) : '');

// ---------------------------------------------------------------------
// Trusted reverse proxies (for accurate client IP logging)
// ---------------------------------------------------------------------
// Leave empty for a normal setup where PHP talks directly to visitors
// (e.g. XAMPP/local dev, or Apache/PHP with no proxy in front). In that
// case REMOTE_ADDR is already the real client IP and is used as-is.
//
// If TDCMS sits behind a reverse proxy or load balancer (Nginx, Apache
// mod_proxy, Cloudflare, etc.), add the proxy's IP address(es) here.
// Only then will X-Forwarded-For / X-Real-IP headers be trusted for
// client IP detection — this prevents visitors from spoofing their own
// IP by sending a fake X-Forwarded-For header directly.
// Example: define('TRUSTED_PROXIES', ['10.0.0.5', '127.0.0.1']);
define('TRUSTED_PROXIES', []);

// ---------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------
define('DEFAULT_PAGE_SIZE', 20);

// ---------------------------------------------------------------------
// Timezone
// ---------------------------------------------------------------------
date_default_timezone_set('Asia/Kolkata');