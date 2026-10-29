<?php
/**
 * AJAX endpoint — POST /ajax/copy_text.php
 * Marks an assignment completed after the client copies its text.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../controllers/user_controller.php';

handle_copy_assignment($pdo);
