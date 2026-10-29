<?php
/**
 * One-time CLI helper used during installation to generate a bcrypt hash
 * for the initial administrator password.
 *
 * Usage:
 *   php database/generate_admin_hash.php "YourStrongPassword123"
 *
 * Copy the output hash into an UPDATE statement, e.g.:
 *   UPDATE users SET password_hash = '<hash>' WHERE username = 'admin';
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

$password = $argv[1] ?? null;

if (!$password) {
    fwrite(STDERR, "Usage: php generate_admin_hash.php \"YourStrongPassword123\"\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;
