<?php
/**
 * AJAX endpoint — POST /ajax/copy_text.php
 * Validates assignment access and records copy/reference progress.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../controllers/user_controller.php';

handle_copy_assignment($pdo);
