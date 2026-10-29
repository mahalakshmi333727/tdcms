<?php
/**
 * Persistent notifications for administrators.
 */

declare(strict_types=1);

final class AdminNotification
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function generateOverdue(DateTimeImmutable $now): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO admin_notifications
                (assignment_id, user_id, user_name, assignment_title, assigned_at, deadline_at, created_at)
             SELECT a.id, a.user_id, u.full_name, a.title, a.assigned_at, a.due_at, :created_at
             FROM assignments a
             JOIN users u ON u.id = a.user_id
             WHERE a.status = 'pending'
               AND a.archived_at IS NULL
               AND a.due_at IS NOT NULL
               AND a.due_at <= :deadline
               AND NOT EXISTS (
                   SELECT 1
                   FROM admin_notifications n
                   WHERE n.assignment_id = a.id
               )
             ORDER BY a.due_at ASC, a.id ASC
             LIMIT 500"
        );
        $currentTime = $now->format('Y-m-d H:i:s');
        $stmt->execute([
            ':created_at' => $currentTime,
            ':deadline' => $currentTime,
        ]);

        return $stmt->rowCount();
    }

    public function unreadCount(): int
    {
        return (int) $this->pdo->query(
            'SELECT COUNT(*) FROM admin_notifications WHERE read_at IS NULL'
        )->fetchColumn();
    }

    public function list(int $page = 1, int $perPage = 25): array
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM admin_notifications')->fetchColumn();
        $pages = (int) ceil($total / $perPage);
        $page = min(max(1, $page), max(1, $pages));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT n.*, a.status AS current_status, a.archived_at
             FROM admin_notifications n
             LEFT JOIN assignments a ON a.id = n.assignment_id
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    public function markRead(int $notificationId, DateTimeImmutable $now): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE admin_notifications
             SET read_at = COALESCE(read_at, :read_at)
             WHERE id = :id'
        );
        $stmt->execute([
            ':read_at' => $now->format('Y-m-d H:i:s'),
            ':id' => $notificationId,
        ]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        $exists = $this->pdo->prepare('SELECT 1 FROM admin_notifications WHERE id = :id');
        $exists->execute([':id' => $notificationId]);
        return $exists->fetchColumn() !== false;
    }
}
