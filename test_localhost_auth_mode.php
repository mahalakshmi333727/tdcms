<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

function assert_localhost_auth_mode(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$productionMode = APP_ENV === 'production';

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
assert_localhost_auth_mode(
    is_localhost_development_request() === !$productionMode,
    'Localhost username login did not match the configured environment.'
);

$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
$_SERVER['SERVER_ADDR'] = '192.0.2.1';
assert_localhost_auth_mode(
    !is_localhost_development_request(),
    'Username-only login was allowed for a non-local client.'
);

$_SERVER['REMOTE_ADDR'] = '::1';
$_SERVER['SERVER_ADDR'] = '::1';
$_SERVER['HTTP_HOST'] = 'example.com';
assert_localhost_auth_mode(
    !is_localhost_development_request(),
    'Username-only login was allowed for a non-local hostname.'
);

$_SERVER['HTTP_HOST'] = 'localhost';
foreach ([
    'HTTP_FORWARDED',
    'HTTP_X_FORWARDED_FOR',
    'HTTP_X_FORWARDED_HOST',
    'HTTP_X_FORWARDED_PROTO',
    'HTTP_X_FORWARDED_PORT',
    'HTTP_X_REAL_IP',
] as $forwardedHeader) {
    $_SERVER[$forwardedHeader] = '198.51.100.10';
    assert_localhost_auth_mode(
        !is_localhost_development_request(),
        'Username-only login was allowed with ' . $forwardedHeader . '.'
    );
    unset($_SERVER[$forwardedHeader]);
}

$_SERVER['REMOTE_ADDR'] = '::1';
$_SERVER['SERVER_ADDR'] = '::1';
$_SERVER['HTTP_HOST'] = 'localhost';
echo 'PASS: localhost username login is '
    . ($productionMode ? 'disabled' : 'enabled')
    . ' for APP_ENV=' . APP_ENV
    . ", and external/forwarded requests are denied.\n";

$_SERVER['SERVER_ADDR'] = '192.168.29.157';
$_SERVER['REMOTE_ADDR'] = '192.168.29.157';
$_SERVER['HTTP_HOST'] = '192.168.29.157';
assert_localhost_auth_mode(
    is_localhost_development_request() === !$productionMode,
    'Direct access from the development server to its own private LAN address was not handled correctly.'
);

$_SERVER['REMOTE_ADDR'] = '192.168.29.43';
$_SERVER['HTTP_HOST'] = 'localhost';
assert_localhost_auth_mode(
    !is_localhost_development_request(),
    'A different LAN client was trusted without being explicitly allowlisted.'
);

if (in_array('192.168.29.157', DEV_USERNAME_LOGIN_HOSTS, true)
    && ip_matches_any('192.168.29.42', DEV_USERNAME_LOGIN_CLIENTS)) {
    $_SERVER['REMOTE_ADDR'] = '192.168.29.42';
    $_SERVER['HTTP_HOST'] = '192.168.29.157';
    assert_localhost_auth_mode(
        is_localhost_development_request() === !$productionMode,
        'An explicitly allowlisted LAN client was not handled correctly.'
    );
}

echo "PASS: direct server-self LAN access works only in development; other LAN clients require explicit allowlisting.\n";
