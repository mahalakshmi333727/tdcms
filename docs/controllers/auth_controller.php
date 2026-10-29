<?php
/**
 * Auth Controller
 * Handles: login submission and logout.
 * Expects $pdo to be available (from bootstrap.php).
 */

declare(strict_types=1);

function handle_login(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    $username = sanitize_input($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $userModel = new User($pdo);
    $loginLog  = new LoginLog($pdo);
    $activity  = new ActivityLog($pdo);

    // Rate limit by IP to slow down brute force / credential stuffing
    if (!rate_limit_check($pdo, 'login_ip_' . client_ip(), 15, 60)) {
        $loginLog->record(null, $username, 'failed');
        set_flash('error', 'Too many login attempts. Please wait a minute and try again.');
        redirect(APP_URL . '/index.php?page=login');
    }

    if ($username === '' || $password === '') {
        set_flash('error', 'Please enter both username and password.');
        redirect(APP_URL . '/index.php?page=login');
    }

    $user = $userModel->findByUsername($username);

    if (!$user) {
        $loginLog->record(null, $username, 'failed');
        set_flash('error', 'Invalid username or password.');
        redirect(APP_URL . '/index.php?page=login');
    }

    if ($userModel->isLocked($user)) {
        $loginLog->record((int) $user['id'], $username, 'locked');
        set_flash('error', 'This account is temporarily locked due to multiple failed attempts. Please try again later.');
        redirect(APP_URL . '/index.php?page=login');
    }

    if ($user['status'] !== 'active') {
        $loginLog->record((int) $user['id'], $username, 'failed');
        set_flash('error', 'This account has been disabled. Contact your administrator.');
        redirect(APP_URL . '/index.php?page=login');
    }

    if (!verify_password($password, $user['password_hash'])) {
        $userModel->recordFailedAttempt((int) $user['id']);
        $loginLog->record((int) $user['id'], $username, 'failed');
        set_flash('error', 'Invalid username or password.');
        redirect(APP_URL . '/index.php?page=login');
    }

    // Success
    $userModel->resetFailedAttempts((int) $user['id']);
    $loginLog->record((int) $user['id'], $username, 'success');
    $activity->record((int) $user['id'], 'login', 'User logged in successfully.');
    login_user($user);

    if ($user['role'] === 'admin') {
        redirect(APP_URL . '/index.php?page=admin_dashboard');
    }
    redirect(APP_URL . '/index.php?page=dashboard');
}

function handle_logout(PDO $pdo): void
{
    csrf_protect();
    if (is_logged_in()) {
        (new ActivityLog($pdo))->record(current_user_id(), 'logout', 'User logged out.');
    }
    logout_user();
    redirect(APP_URL . '/index.php?page=login');
}

