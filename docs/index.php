<?php
/**
 * TDCMS — Front Controller
 * Every request is routed through this file via .htaccess.
 * Query params:
 *   ?page=<view>    → renders a view (GET)
 *   ?action=<name>  → runs a controller action (usually POST)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/controllers/auth_controller.php';
require_once __DIR__ . '/controllers/user_controller.php';
require_once __DIR__ . '/controllers/admin_controller.php';

$action = $_GET['action'] ?? null;
$page   = $_GET['page'] ?? null;

// ---------------------------------------------------------------------
// ACTION ROUTES (state-changing — controllers handle their own auth/CSRF)
// ---------------------------------------------------------------------
if ($action !== null) {
    switch ($action) {
        case 'login':                 handle_login($pdo); break;
        case 'logout':                handle_logout($pdo); break;

        case 'create_user':
        case 'update_user':
        case 'toggle_user_status':
        case 'delete_user':
        case 'create_assignment':
        case 'bulk_assign':
        case 'export_csv':
        case 'get_assignment':
        case 'update_assignment':
        case 'delete_assignment':
            require __DIR__ . '/middleware/require_login.php';
            require __DIR__ . '/middleware/require_admin.php';
            match ($action) {
                'create_user'         => handle_create_user($pdo),
                'update_user'         => handle_update_user($pdo),
                'toggle_user_status'  => handle_toggle_user_status($pdo),
                'delete_user'         => handle_delete_user($pdo),
                'create_assignment'   => handle_create_assignment($pdo),
                'bulk_assign'         => handle_bulk_assign($pdo),
                'export_csv'          => handle_export_csv($pdo),
                'get_assignment'      => handle_get_assignment($pdo),
                'update_assignment'   => handle_update_assignment($pdo),
                'delete_assignment'   => handle_delete_assignment($pdo),
            };
            break;

        default:
            http_response_code(404);
            echo 'Unknown action.';
    }
    exit;
}

// ---------------------------------------------------------------------
// PAGE ROUTES (GET — render a view)
// ---------------------------------------------------------------------
$guestPages = ['login'];
$adminPages = [
    'admin_dashboard', 'admin_users', 'admin_user_form', 'admin_assignments',
    'admin_assign_form', 'admin_bulk_assign', 'admin_activity_logs', 'admin_login_logs',
];
$userPages = ['dashboard', 'history'];

$page = $page ?: (is_logged_in() ? (is_admin() ? 'admin_dashboard' : 'dashboard') : 'login');

// Guests hitting a protected page → redirect to login (handled by middleware);
// Logged-in users hitting the login page → send to their dashboard.
if (in_array($page, $guestPages, true) && is_logged_in()) {
    redirect(APP_URL . '/index.php?page=' . (is_admin() ? 'admin_dashboard' : 'dashboard'));
}

$viewMap = [
    'login'                => __DIR__ . '/views/auth/login.php',
    'dashboard'             => __DIR__ . '/views/user/dashboard.php',
    'history'               => __DIR__ . '/views/user/history.php',
    'admin_dashboard'       => __DIR__ . '/views/admin/dashboard.php',
    'admin_users'           => __DIR__ . '/views/admin/users.php',
    'admin_user_form'       => __DIR__ . '/views/admin/user_form.php',
    'admin_assignments'     => __DIR__ . '/views/admin/assignments.php',
    'admin_assign_form'     => __DIR__ . '/views/admin/assign_form.php',
    'admin_bulk_assign'     => __DIR__ . '/views/admin/bulk_assign.php',
    'admin_activity_logs'   => __DIR__ . '/views/admin/activity_logs.php',
    'admin_login_logs'      => __DIR__ . '/views/admin/login_logs.php',
];

if (!isset($viewMap[$page])) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require __DIR__ . '/layouts/header.php';
    echo '<div class="text-center py-5"><h1 class="display-1">404</h1><p class="text-muted">Page not found.</p>
          <a href="' . e(APP_URL) . '/index.php" class="btn btn-primary">Go Home</a></div>';
    require __DIR__ . '/layouts/footer.php';
    exit;
}

if (in_array($page, $userPages, true) || in_array($page, $adminPages, true)) {
    require __DIR__ . '/middleware/require_login.php';
}
if (in_array($page, $adminPages, true)) {
    require __DIR__ . '/middleware/require_admin.php';
}

require $viewMap[$page];
