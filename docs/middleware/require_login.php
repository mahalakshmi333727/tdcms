<?php
/**
 * Middleware: blocks access unless the user is authenticated.
 * Include at the top of any protected view/controller.
 */

declare(strict_types=1);

if (!is_logged_in()) {
    if (function_exists('is_ajax_request') && is_ajax_request()) {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }
    set_flash('error', 'Please log in to continue.');
    redirect(APP_URL . '/index.php?page=login');
}
