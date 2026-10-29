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

    $rawUsername = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? '';
    $verificationCode = $_POST['verification_code'] ?? '';
    if (!is_string($rawUsername) || !is_string($password) || !is_string($verificationCode)) {
        set_flash('error', 'Enter valid login credentials.');
        redirect(APP_URL . '/index.php?page=login');
    }
    $username = strtolower(sanitize_input($rawUsername));

    $userModel = new User($pdo);
    $loginLog  = new LoginLog($pdo);
    $activity  = new ActivityLog($pdo);

    // Rate limit by IP to slow down brute force / credential stuffing
    if (!rate_limit_check($pdo, 'login_ip_' . client_ip(), 15, 60)) {
        $loginLog->record(null, $username, 'failed');
        set_flash('error', 'Too many login attempts. Please wait a minute and try again.');
        redirect(APP_URL . '/index.php?page=login');
    }

    if (!validate_username_format($username)) {
        set_flash('error', 'Username must be 3-60 characters: letters, numbers, dot, underscore, or hyphen.');
        redirect(APP_URL . '/index.php?page=login');
    }

    $user = $userModel->findByUsername($username);

    if (!$user) {
        $loginLog->record(null, $username, 'failed');
        set_flash('error', 'The username or sign-in credential is invalid.');
        redirect(APP_URL . '/index.php?page=login');
    }

    if ($userModel->isLocked($user)) {
        $loginLog->record((int) $user['id'], $username, 'locked');
        set_flash('error', 'This account is temporarily locked due to multiple failed attempts. Please try again later.');
        redirect(APP_URL . '/index.php?page=login');
    }

    if ($user['status'] !== 'active') {
        $loginLog->record((int) $user['id'], $username, 'failed');
        set_flash('error', 'Your account is disabled. Please contact the administrator.');
        redirect(APP_URL . '/index.php?page=login');
    }

    $authenticated = false;
    $failureMessage = 'The username or sign-in credential is invalid.';
    if ($user['role'] === 'admin') {
        $passwordHash = $user['password_hash'] ?? null;
        $authenticated = $password !== ''
            && is_string($passwordHash)
            && $passwordHash !== ''
            && verify_password($password, $passwordHash);
        if ($password === '') {
            $failureMessage = 'Please enter your password.';
        }
    } elseif ($user['role'] === 'user') {
        if (is_localhost_development_request()) {
            $authenticated = true;
        } else {
            $challenge = $_SESSION['login_email_challenge'] ?? null;
            $codeRateAllowed = rate_limit_check($pdo, 'login_code_verify_ip_' . client_ip(), 10, 900);
            $hasValidChallenge = is_array($challenge)
                && ($challenge['user_id'] ?? null) === (int) $user['id']
                && is_string($challenge['salt'] ?? null)
                && is_string($challenge['code_hash'] ?? null)
                && is_int($challenge['expires_at'] ?? null)
                && $challenge['expires_at'] >= time()
                && is_int($challenge['attempts'] ?? null)
                && $challenge['attempts'] < 5;

            if ($codeRateAllowed && $hasValidChallenge) {
                $authenticated = verify_login_email_challenge(
                    $challenge,
                    (int) $user['id'],
                    $verificationCode
                );
                $_SESSION['login_email_challenge'] = $challenge;
            }

            if (!$codeRateAllowed) {
                $failureMessage = 'Too many verification attempts. Please wait before trying again.';
            } elseif (!$hasValidChallenge) {
                $failureMessage = 'Your verification code expired or is no longer valid. Request a new code.';
            } elseif (!$authenticated) {
                $failureMessage = 'The username or sign-in credential is invalid.';
            }
        }
    } else {
        $failureMessage = 'This account cannot log in.';
    }

    if (!$authenticated) {
        if ($user['role'] === 'admin') {
            $userModel->recordFailedAttempt((int) $user['id']);
        }
        $loginLog->record((int) $user['id'], $username, 'failed');
        set_flash('error', $failureMessage);
        redirect(APP_URL . '/index.php?page=login');
    }

    unset($_SESSION['login_email_challenge']);
    $userModel->resetFailedAttempts((int) $user['id']);
    $loginLog->record((int) $user['id'], $username, 'success');
    $activity->record((int) $user['id'], 'login', 'User logged in successfully.');
    login_user($user);

    if ($user['role'] === 'admin') {
        redirect(APP_URL . '/index.php?page=admin_dashboard');
    }
    redirect(APP_URL . '/index.php?page=dashboard');
}

function handle_login_check(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    if (!rate_limit_check($pdo, 'login_check_ip_' . client_ip(), 30, 60)) {
        json_response(['success' => false, 'message' => 'Too many attempts. Please wait a minute and try again.'], 429);
    }

    $rawUsername = $_POST['username'] ?? null;
    if (!is_string($rawUsername)) {
        json_response(['success' => false, 'message' => 'Enter a valid username.'], 422);
    }
    $username = strtolower(sanitize_input($rawUsername));

    if (!validate_username_format($username)) {
        json_response(['success' => false, 'message' => 'Username must be 3-60 characters: letters, numbers, dot, underscore, or hyphen.'], 422);
    }

    $userModel = new User($pdo);
    $user = $userModel->findByUsername($username);
    if (!$user || $user['status'] !== 'active') {
        json_response(['success' => false, 'message' => 'The username is invalid or unavailable.'], 422);
    }
    if ($userModel->isLocked($user)) {
        json_response(['success' => false, 'message' => 'This account is temporarily locked. Please try again later.'], 423);
    }

    if ($user['role'] === 'admin') {
        unset($_SESSION['login_email_challenge']);
        json_response(['success' => true, 'role' => 'admin', 'auth_method' => 'password', 'requires_password' => true]);
    }
    if ($user['role'] === 'user') {
        if (is_localhost_development_request()) {
            unset($_SESSION['login_email_challenge']);
            json_response(['success' => true, 'role' => 'user', 'auth_method' => 'username']);
        }

        if (!rate_limit_check($pdo, 'login_code_issue_user_' . (int) $user['id'], 3, 900)) {
            json_response(['success' => false, 'message' => 'Too many verification codes requested. Please try again later.'], 429);
        }
        if (!rate_limit_check($pdo, 'login_code_issue_ip_' . client_ip(), 10, 900)) {
            json_response(['success' => false, 'message' => 'Too many verification codes requested. Please try again later.'], 429);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        if (!send_login_verification_code($user['email'], $code)) {
            unset($_SESSION['login_email_challenge']);
            error_log('TDCMS could not send a user login verification email.');
            json_response(['success' => false, 'message' => 'The verification email could not be sent. Please contact the administrator.'], 503);
        }

        $_SESSION['login_email_challenge'] = create_login_email_challenge((int) $user['id'], $code);
        json_response([
            'success' => true,
            'role' => 'user',
            'auth_method' => 'email_code',
            'message' => 'A six-digit sign-in code was sent to the email address on your account. It expires in 10 minutes.',
        ]);
    }

    json_response(['success' => false, 'message' => 'This account cannot log in.'], 422);
}

function create_login_email_challenge(int $userId, string $code, ?int $issuedAt = null): array
{
    $salt = bin2hex(random_bytes(16));
    return [
        'user_id' => $userId,
        'salt' => $salt,
        'code_hash' => password_hash($code . $salt, PASSWORD_DEFAULT),
        'expires_at' => ($issuedAt ?? time()) + 600,
        'attempts' => 0,
    ];
}

function verify_login_email_challenge(?array &$challenge, int $userId, string $code, ?int $now = null): bool
{
    if (
        !is_array($challenge)
        || ($challenge['user_id'] ?? null) !== $userId
        || !is_string($challenge['salt'] ?? null)
        || !is_string($challenge['code_hash'] ?? null)
        || !is_int($challenge['expires_at'] ?? null)
        || $challenge['expires_at'] < ($now ?? time())
        || !is_int($challenge['attempts'] ?? null)
        || $challenge['attempts'] >= 5
    ) {
        return false;
    }

    $challenge['attempts']++;
    if (!preg_match('/^[0-9]{6}$/', $code)) {
        return false;
    }

    return password_verify($code . $challenge['salt'], $challenge['code_hash']);
}

function send_login_verification_code(string $recipient, string $code): bool
{
    $sender = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : '';
    if (!is_valid_email($recipient) || !is_valid_email($sender)) {
        return false;
    }

    $subject = 'Your TDCMS sign-in code';
    $body = "Your TDCMS sign-in code is: {$code}\n\n"
        . "This code expires in 10 minutes. If you did not request it, you can ignore this email.";
    $headers = [
        'From: TDCMS <' . $sender . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    return mail($recipient, $subject, $body, implode("\r\n", $headers));
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
