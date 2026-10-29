<?php
/**
 * Reusable field-level validators used by controllers.
 * Kept separate from includes/functions.php to isolate pure validation
 * logic from general-purpose utilities.
 */

declare(strict_types=1);

function validate_username_format(string $username): bool
{
    return (bool) preg_match('/^[a-z0-9._-]{3,60}$/', $username);
}

function validate_full_name(string $name): bool
{
    return $name !== '' && mb_strlen($name) <= 120;
}

function validate_text_content(string $text): bool
{
    return $text !== '' && mb_strlen($text) <= 20000;
}

function validate_title(string $title): bool
{
    return mb_strlen($title) <= 150;
}
