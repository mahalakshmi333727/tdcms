<?php
/**
 * ActivityLog Model — records administrative and system actions for audit.
 */

declare(strict_types=1);

final class ActivityLog
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function record(?int $userId, string $action, string $description = ''): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent)
             VALUES (:uid, :action, :description, :ip, :ua)'
        );
        $stmt->execute([
            ':uid'         => $userId,
            ':action'      => $action,
            ':description' => mb_substr($description, 0, 500),
            ':ip'          => client_ip(),
            ':ua'          => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    }

}
