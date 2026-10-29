<?php
/**
 * Common helper / utility functions used across the application.
 */

declare(strict_types=1);

/**
 * Escape output for safe HTML rendering (XSS protection).
 */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value): int
    {
        return strlen($value);
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null): string
    {
        if ($length === null) {
            return substr($value, $start);
        }
        return substr($value, $start, $length);
    }
}

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value): string
    {
        return strtolower($value);
    }
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Return a compact page-number sequence with first/last pages and ellipses.
 *
 * @return array<int, int|string>
 */
function pagination_page_items(int $currentPage, int $totalPages): array
{
    if ($totalPages <= 0) {
        return [];
    }

    $currentPage = min(max(1, $currentPage), $totalPages);
    if ($totalPages <= 7) {
        return range(1, $totalPages);
    }

    $start = max(2, min($currentPage - 1, $totalPages - 2));
    $end = min($totalPages - 1, max($currentPage + 1, 3));
    $items = [1];

    if ($start > 2) {
        $items[] = '…';
    }
    for ($page = $start; $page <= $end; $page++) {
        $items[] = $page;
    }
    if ($end < $totalPages - 1) {
        $items[] = '…';
    }
    $items[] = $totalPages;

    return $items;
}

/**
 * Trim + strip null bytes from raw input. Does NOT replace escaping on output.
 */
function sanitize_input(?string $value): string
{
    if ($value === null) {
        return '';
    }
    $value = str_replace("\0", '', $value);
    return trim($value);
}

/**
 * Validate an email address.
 */
function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate a URL (used for reference links). Only http/https allowed.
 */
function is_valid_url(string $url): bool
{
    if ($url === '') {
        return true; // optional field
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return in_array(strtolower((string) $scheme), ['http', 'https'], true);
}

/**
 * Redirect helper. Always exits after sending the Location header.
 */
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/**
 * Set a one-time flash message stored in session.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

/**
 * Pop all flash messages (they are removed after being read once).
 */
function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}
/**
 * Get the real client IP address — full IPv4 or full IPv6, never truncated,
 * never masked, never hardcoded.
 *
 * Why you might see "::1" locally:
 *  "::1" is the IPv6 loopback address (same as IPv4's 127.0.0.1). On
 *  Windows/XAMPP, "localhost" often resolves to IPv6 loopback first, so
 *  opening the site from the SAME machine running Apache/PHP genuinely
 *  gives "::1" — that is correct, not a bug. A real public IPv6 like
 *  2405:204:a10c:1234::1 will only appear when the request actually
 *  arrives from that address over the network (another device, or a real
 *  deployment).
 */
function client_ip(): string
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $directIp = filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false ? $remoteAddr : '0.0.0.0';

    // On localhost/XAMPP, REMOTE_ADDR is genuinely the loopback address
    // ("::1" or "127.0.0.1") — that's correct, not a bug. In that one case,
    // fall back to the public IP the browser reported via ajax/set_client_ip.php
    // (see assets/js/app.js), so login logs show a real address during local
    // testing instead of the loopback IP. This never overrides a real
    // non-loopback REMOTE_ADDR, so it can't be used to spoof the IP over
    // an actual network request.
    if (in_array($directIp, ['::1', '127.0.0.1'], true)) {
        $sessionIp = $_SESSION['client_public_ip'] ?? '';
        if (is_string($sessionIp) && $sessionIp !== '' && filter_var($sessionIp, FILTER_VALIDATE_IP) !== false) {
            return $sessionIp;
        }
    }

    $trustedProxies = defined('TRUSTED_PROXIES') ? TRUSTED_PROXIES : [];

    // Not behind a trusted proxy (default) -> REMOTE_ADDR IS the real IP.
    // Never trust client-controlled headers unless a trusted proxy sent them.
    if (empty($trustedProxies) || !ip_matches_any($directIp, $trustedProxies)) {
        return $directIp;
    }

    $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwardedFor !== '') {
        $parts = array_map('trim', explode(',', $forwardedFor));
        $candidate = $parts[0] ?? '';
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
            return $candidate;
        }
    }

    $realIp = $_SERVER['HTTP_X_REAL_IP'] ?? '';
    if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP) !== false) {
        return $realIp;
    }

    return $directIp;
}

/**
 * Check whether $ip equals or falls inside any entry of $list.
 * Entries may be a plain IP ("127.0.0.1", "::1") or CIDR ("10.0.0.0/8").
 */
function ip_matches_any(string $ip, array $list): bool
{
    foreach ($list as $entry) {
        if ($entry === $ip) {
            return true;
        }
        if (str_contains($entry, '/') && ip_in_cidr($ip, $entry)) {
            return true;
        }
    }
    return false;
}

/**
 * IPv4/IPv6-aware CIDR membership check.
 */
function ip_in_cidr(string $ip, string $cidr): bool
{
    [$subnet, $maskBits] = array_pad(explode('/', $cidr, 2), 2, null);
    if ($maskBits === null || $subnet === null || !is_numeric($maskBits)) {
        return false;
    }

    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }

    $maskBits = (int) $maskBits;
    $totalBits = strlen($ipBin) * 8;
    if ($maskBits < 0 || $maskBits > $totalBits) {
        return false;
    }

    $bytes = intdiv($maskBits, 8);
    $remainderBits = $maskBits % 8;

    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
        return false;
    }
    if ($remainderBits > 0) {
        $mask = chr((0xFF << (8 - $remainderBits)) & 0xFF);
        if ((substr($ipBin, $bytes, 1) & $mask) !== (substr($subnetBin, $bytes, 1) & $mask)) {
            return false;
        }
    }
    return true;
}

 
     

/**
 * Return a truncated preview of text content for list views.
 */
function text_preview(string $text, int $length = 80): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . '…';
}

/**
 * Format a datetime string for display.
 */
function format_datetime(?string $datetime, string $format = 'd M Y, h:i A'): string
{
    if (empty($datetime)) {
        return '—';
    }
    try {
        $dt = new DateTime($datetime);
        return $dt->format($format);
    } catch (Exception $e) {
        return '—';
    }
}

/**
 * JSON response helper for AJAX endpoints.
 */
function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Ensure the current request method matches; otherwise return 405.
 */
function require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        json_response(['success' => false, 'message' => 'Method not allowed'], 405);
    }
}

/**
 * Generate a cryptographically secure random token (hex string).
 */
function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}