<?php
/**
 * Common helper / utility functions used across the application.
 */

declare(strict_types=1);

/**
 * Escape output for safe HTML rendering (XSS protection).
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
 * Get the real client IP address (best effort, respects common proxy headers
 * only if you trust your reverse proxy — otherwise falls back to REMOTE_ADDR).
 */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
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
