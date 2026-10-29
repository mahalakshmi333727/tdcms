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

    public function list(array $filters = [], int $page = 1, int $perPage = DEFAULT_PAGE_SIZE): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'l.action = :action';
            $params[':action'] = $filters['action'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(l.description LIKE :search OR u.full_name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id WHERE $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT l.*, u.full_name, u.username FROM activity_logs l
                LEFT JOIN users u ON u.id = l.user_id
                WHERE $whereSql ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows'  => $stmt->fetchAll(),
            'total' => $total,
            'page'  => $page,
            'pages' => (int) ceil($total / $perPage),
        ];
    }
}
