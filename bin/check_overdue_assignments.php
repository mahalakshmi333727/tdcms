<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/AdminNotification.php';
require_once __DIR__ . '/../models/ActivityLog.php';

try {
    $pdo = Database::getConnection();
    $totalCreated = 0;
    do {
        $pdo->beginTransaction();
        $created = (new AdminNotification($pdo))->generateOverdue(
            new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'))
        );
        if ($created > 0) {
            (new ActivityLog($pdo))->record(
                null,
                'overdue_notifications',
                "Created $created overdue task notification(s)"
            );
        }
        $pdo->commit();
        $totalCreated += $created;
    } while ($created === 500);

    if ($totalCreated > 0) {
        fwrite(STDOUT, "Created $totalCreated overdue notification(s)." . PHP_EOL);
    }
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Overdue notification check failed: ' . $error->getMessage());
    fwrite(STDERR, 'Overdue notification check failed. See the PHP error log.' . PHP_EOL);
    exit(1);
}
