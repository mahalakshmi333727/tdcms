<?php
/**
 * CSRF Protection
 * Generates and validates per-session CSRF tokens.
 */

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = random_token(32);
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Output a hidden input field carrying the CSRF token, for use inside <form>.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . e(csrf_token()) . '">';
}

/**
 * Validate a submitted token against the session token using a timing-safe
 * comparison. Returns true if valid.
 */
function csrf_verify(?string $submittedToken): bool
{
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($submittedToken)) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $submittedToken);
}

/**
 * Validate CSRF for the current POST request; halts execution with an error
 * if the token is missing or invalid. Call at the top of every state-changing
 * request handler (form POST or AJAX POST).
 */
function csrf_protect(): void
{
    $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!csrf_verify($token)) {
        if (is_ajax_request()) {
            json_response(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page.'], 419);
        }
        set_flash('error', 'Your session has expired or the request was invalid. Please try again.');
        redirect(APP_URL . '/index.php');
    }
}

function is_ajax_request(): bool
{
    return (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) || (
        !empty($_SERVER['HTTP_ACCEPT'])
        && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
    );
}
