<?php
declare(strict_types=1);

putenv('MAIL_FROM_ADDRESS=');
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/controllers/auth_controller.php';

function assert_login_challenge(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$validCode = '004271';
$challenge = create_login_email_challenge(17, $validCode, 1000);
assert_login_challenge($challenge['code_hash'] !== $validCode, 'The verification code was stored in plaintext.');
assert_login_challenge(
    verify_login_email_challenge($challenge, 17, $validCode, 1599),
    'A valid code was rejected before its expiry.'
);
assert_login_challenge($challenge['attempts'] === 1, 'A verified attempt was not counted.');

$wrongUserChallenge = create_login_email_challenge(17, $validCode, 1000);
assert_login_challenge(
    !verify_login_email_challenge($wrongUserChallenge, 18, $validCode, 1001),
    'A code was accepted for a different user.'
);
assert_login_challenge($wrongUserChallenge['attempts'] === 0, 'A mismatched user consumed a code attempt.');

$expiredChallenge = create_login_email_challenge(17, $validCode, 1000);
assert_login_challenge(
    !verify_login_email_challenge($expiredChallenge, 17, $validCode, 1601),
    'An expired code was accepted.'
);

$limitedChallenge = create_login_email_challenge(17, $validCode, 1000);
for ($attempt = 0; $attempt < 5; $attempt++) {
    assert_login_challenge(
        !verify_login_email_challenge($limitedChallenge, 17, '999999', 1001),
        'An incorrect verification code was accepted.'
    );
}
assert_login_challenge($limitedChallenge['attempts'] === 5, 'The attempt limit was not enforced.');
assert_login_challenge(
    !verify_login_email_challenge($limitedChallenge, 17, $validCode, 1001),
    'A code was accepted after the attempt limit.'
);
assert_login_challenge(
    !send_login_verification_code('user@example.com', $validCode),
    'Email verification was allowed without a configured sender address.'
);
assert_login_challenge(
    validate_password_strength('ValidPass12!') === null,
    'A 12-character administrator password was rejected.'
);
assert_login_challenge(
    validate_password_strength('ShortPass1!') !== null,
    'An administrator password shorter than the minimum was accepted.'
);

echo "PASS: email-code and administrator-password validation checks.\n";
