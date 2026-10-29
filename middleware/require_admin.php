<?php
/**
 * Middleware: blocks access unless the authenticated user has the 'admin'
 * role. Must be included AFTER middleware/require_login.php.
 */

declare(strict_types=1);

if (!is_admin()) {
    if (function_exists('is_ajax_request') && is_ajax_request()) {
        json_response(['success' => false, 'message' => 'Administrator access required.'], 403);
    }
    http_response_code(403);
    set_flash('error', 'You do not have permission to access that page.');
    redirect(APP_URL . '/index.php?page=dashboard');
}
