<?php
/**
 * Session bootstrap + authentication helpers.
 * Must be included after config/config.php.
 */

declare(strict_types=1);

/**
 * Start a secure session, enforce idle timeout, and periodically regenerate
 * the session ID to mitigate session fixation attacks.
 */
function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('TDCMS_SESSID');
    session_start();

    $now = time();

    // Idle timeout
    if (!empty($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['flash']['error'][] = 'Your session expired due to inactivity. Please log in again.';
    }
    $_SESSION['last_activity'] = $now;

    // Periodic session ID regeneration
    if (empty($_SESSION['created_at'])) {
        $_SESSION['created_at'] = $now;
    } elseif (($now - $_SESSION['created_at']) > SESSION_REGEN_INTERVAL) {
        session_regenerate_id(true);
        $_SESSION['created_at'] = $now;
    }
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['user_role'] ?? '') === 'admin';
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function is_localhost_development_request(): bool
{
    if (
        APP_ENV !== 'development'
        || !empty(TRUSTED_PROXIES)
        || !empty($_SERVER['HTTP_FORWARDED'])
        || !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
        || !empty($_SERVER['HTTP_X_FORWARDED_HOST'])
        || !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        || !empty($_SERVER['HTTP_X_FORWARDED_PORT'])
        || !empty($_SERVER['HTTP_X_REAL_IP'])
    ) {
        return false;
    }

    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($remoteAddress) || filter_var($remoteAddress, FILTER_VALIDATE_IP) === false) {
        return false;
    }

    $requestHost = parse_url('http://' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if (!is_string($requestHost)) {
        return false;
    }
    $requestHost = strtolower(trim($requestHost, '[]'));
    $remoteAddress = strtolower($remoteAddress);

    $loopbackClients = ['127.0.0.1', '::1'];
    if (
        in_array($requestHost, DEV_USERNAME_LOGIN_HOSTS, true)
        && (in_array($remoteAddress, $loopbackClients, true)
            || ip_matches_any($remoteAddress, DEV_USERNAME_LOGIN_CLIENTS))
    ) {
        return true;
    }

    $serverAddress = $_SERVER['SERVER_ADDR'] ?? '';
    if (
        !is_string($serverAddress)
        || filter_var($serverAddress, FILTER_VALIDATE_IP) === false
    ) {
        return false;
    }
    $serverAddress = strtolower($serverAddress);

    $isPrivateServerAddress = ip_matches_any($serverAddress, [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        'fc00::/7',
    ]);
    $isDirectLocalServerRequest = $isPrivateServerAddress
        && $requestHost === $serverAddress
        && $remoteAddress === $serverAddress;
    $isConfiguredLocalClient = in_array($requestHost, DEV_USERNAME_LOGIN_HOSTS, true)
        && ip_matches_any($remoteAddress, DEV_USERNAME_LOGIN_CLIENTS);

    return $isDirectLocalServerRequest || $isConfiguredLocalClient;
}

/**
 * Regenerate session ID and store the authenticated user's identity.
 * Call immediately after successful authentication.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_name']  = $user['full_name'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['created_at'] = time();
    $_SESSION['last_activity'] = time();
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Hash a plaintext password using bcrypt (PASSWORD_DEFAULT).
 */
function hash_password(string $plain): string
{
    return password_hash($plain, PASSWORD_DEFAULT);
}

function verify_password(string $plain, string $hash): bool
{
    return password_verify($plain, $hash);
}

/**
 * Validate administrator passwords before creating or resetting credentials.
 */
function validate_password_strength(string $password): ?string
{
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if (mb_strlen($password) > 255) {
        return 'Password must be 255 characters or fewer.';
    }
    return null;
}

/**
 * Simple database-backed rate limiter. Returns true if the request should
 * be ALLOWED, false if the caller has exceeded the configured rate.
 */
function rate_limit_check(PDO $pdo, string $bucketKey, int $maxHits = RATE_LIMIT_MAX_HITS, int $windowSeconds = RATE_LIMIT_WINDOW_SECONDS): bool
{
    $now = new DateTime();
    $stmt = $pdo->prepare('SELECT hits, window_start FROM rate_limits WHERE bucket_key = :k');
    $stmt->execute([':k' => $bucketKey]);
    $row = $stmt->fetch();

    if (!$row) {
        $ins = $pdo->prepare('INSERT INTO rate_limits (bucket_key, hits, window_start) VALUES (:k, 1, :w)');
        $ins->execute([':k' => $bucketKey, ':w' => $now->format('Y-m-d H:i:s')]);
        return true;
    }

    $windowStart = new DateTime($row['window_start']);
    if (($now->getTimestamp() - $windowStart->getTimestamp()) > $windowSeconds) {
        $upd = $pdo->prepare('UPDATE rate_limits SET hits = 1, window_start = :w WHERE bucket_key = :k');
        $upd->execute([':w' => $now->format('Y-m-d H:i:s'), ':k' => $bucketKey]);
        return true;
    }

    if ((int) $row['hits'] >= $maxHits) {
        return false;
    }

    $upd = $pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE bucket_key = :k');
    $upd->execute([':k' => $bucketKey]);
    return true;
}
