<?php
/**
 * LoginLog Model — records login attempts and lists unified application logs.
 */

declare(strict_types=1);

final class LoginLog
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function record(?int $userId, string $username, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_logs (user_id, username, status, ip_address, user_agent)
             VALUES (:uid, :username, :status, :ip, :ua)'
        );
        $stmt->execute([
            ':uid'      => $userId,
            ':username' => mb_substr($username, 0, 60),
            ':status'   => $status,
            ':ip'       => client_ip(),
            ':ua'       => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    }

    public function list(array $filters = [], int $page = 1, int $perPage = DEFAULT_PAGE_SIZE): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "logs.event_type = 'login' AND logs.event_action = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['event_type'])) {
            $where[] = 'logs.event_type = :event_type';
            $params[':event_type'] = $filters['event_type'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(logs.username LIKE :search_username OR logs.description LIKE :search_description OR u.full_name LIKE :search_name OR CAST(logs.user_id AS CHAR) LIKE :search_user_id)';
            $search = '%' . $filters['search'] . '%';
            $params[':search_username'] = $search;
            $params[':search_description'] = $search;
            $params[':search_name'] = $search;
            $params[':search_user_id'] = $search;
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'logs.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'logs.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);
        $logsSql = "(
            SELECT ll.id, ll.user_id, ll.username, 'login' AS event_type, ll.status AS event_action,
                   CASE ll.status
                       WHEN 'success' THEN 'User logged in successfully.'
                       WHEN 'failed' THEN 'Login attempt failed.'
                       WHEN 'locked' THEN 'Login blocked because the account is locked.'
                       ELSE ll.status
                   END AS description,
                   ll.ip_address, ll.user_agent, ll.created_at
            FROM login_logs ll
            UNION ALL
            SELECT al.id, al.user_id,
                   COALESCE(u_activity.username, CONCAT('User #', al.user_id), 'System') AS username,
                   'activity' AS event_type, al.action AS event_action, al.description,
                   al.ip_address, al.user_agent, al.created_at
            FROM activity_logs al
            LEFT JOIN users u_activity ON u_activity.id = al.user_id
            WHERE NOT (
                al.action = 'login'
                AND EXISTS (
                    SELECT 1
                    FROM login_logs matching_login
                    WHERE matching_login.user_id = al.user_id
                      AND matching_login.status = 'success'
                      AND matching_login.created_at = al.created_at
                )
            )
        )";

        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM $logsSql logs
             LEFT JOIN users u ON u.id = logs.user_id
             WHERE $whereSql"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pages = (int) ceil($total / $perPage);
        $page = min(max(1, $page), max(1, $pages));

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT logs.*, u.full_name, u.role
                FROM $logsSql logs
                LEFT JOIN users u ON u.id = logs.user_id
                WHERE $whereSql
                ORDER BY logs.created_at DESC, logs.event_type ASC, logs.id DESC
                LIMIT :limit OFFSET :offset";
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
            'pages' => $pages,
        ];
    }
}
