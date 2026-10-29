<?php
/**
 * Assignment Model
 * Represents a single piece of text (+ optional reference link) distributed
 * from an admin to a user.
 */

declare(strict_types=1);

final class Assignment
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a distinct assignment row, including when the user already has
     * a pending assignment with identical content.
     */
    public function create(array $data): array
    {
        return $this->insertOne(
            (int) $data['user_id'],
            (string) ($data['title'] ?? ''),
            (string) ($data['category'] ?? ''),
            (string) $data['text_content'],
            $data['reference_link'] ?? null,
            (string) ($data['tags'] ?? ''),
            (int) $data['assigned_by']
        );
    }

    /** Shared insert path used by create(), bulkCreate(), and bulkCreateFromTasks(). */
    private function insertOne(int $userId, string $title, string $category, string $text, ?string $link, string $tags, int $assignedBy): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO assignments (user_id, title, category, text_content, reference_link, tags, assigned_by, status)
             VALUES (:user_id, :title, :category, :text_content, :reference_link, :tags, :assigned_by, "pending")'
        );
        $stmt->execute([
            ':user_id'        => $userId,
            ':title'          => $title !== '' ? $title : null,
            ':category'       => $category !== '' ? $category : null,
            ':text_content'   => $text,
            ':reference_link' => $link !== null && $link !== '' ? $link : null,
            ':tags'           => $tags !== '' ? $tags : null,
            ':assigned_by'    => $assignedBy,
        ]);
        return ['created' => true, 'id' => (int) $this->pdo->lastInsertId()];
    }

    /**
     * Bulk-assign the same text/link to multiple users in a single transaction.
     * @param int[] $userIds
     * @return array{assigned:int}
     */
    public function bulkCreate(array $userIds, string $title, string $text, ?string $link, int $assignedBy, string $category = '', string $tags = ''): array
    {
        $this->pdo->beginTransaction();
        try {
            $assigned = 0;
            foreach ($userIds as $uid) {
                $this->insertOne((int) $uid, $title, $category, $text, $link, $tags, $assignedBy);
                $assigned++;
            }
            $this->pdo->commit();
            return ['assigned' => $assigned];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Bulk-assign a LIST of parsed tasks (from a JSON or XLSX import) to
     * multiple users in one transaction. Every task/user pair gets its own
     * assignment row, even when task content is identical.
     *
     * @param int[] $userIds
     * @param array<int, array{title?:string, category?:string, text_content:string, reference_link?:?string, tags?:string}> $tasks
     * @return array{assigned:int, tasks:int, users:int}
     */
    public function bulkCreateFromTasks(array $userIds, array $tasks, int $assignedBy): array
    {
        $this->pdo->beginTransaction();
        try {
            $assigned = 0;
            foreach ($tasks as $task) {
                foreach ($userIds as $uid) {
                    $this->insertOne(
                        (int) $uid,
                        (string) ($task['title'] ?? ''),
                        (string) ($task['category'] ?? ''),
                        (string) $task['text_content'],
                        $task['reference_link'] ?? null,
                        (string) ($task['tags'] ?? ''),
                        $assignedBy
                    );
                    $assigned++;
                }
            }
            $this->pdo->commit();
            return ['assigned' => $assigned, 'tasks' => count($tasks), 'users' => count($userIds)];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM assignments WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findActiveById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM assignments WHERE id = :id AND archived_at IS NULL LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findAdminDetailsById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.user_id, a.title, a.category, a.text_content, a.reference_link,
                    a.tags, a.text_copied, a.link_clicked, a.completion_prompt_pending,
                    a.status, a.assigned_by, a.assigned_at, a.completed_at, a.ip_on_completion,
                    u.full_name AS user_full_name, u.username AS user_username,
                    u.status AS user_status, assigned_by_user.full_name AS assigned_by_name
             FROM assignments a
             JOIN users u ON u.id = a.user_id
             JOIN users assigned_by_user ON assigned_by_user.id = a.assigned_by
             WHERE a.id = :id AND a.archived_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateAdmin(int $assignmentId, array $data): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT user_id, status FROM assignments
                 WHERE id = :id AND archived_at IS NULL FOR UPDATE'
            );
            $stmt->execute([':id' => $assignmentId]);
            $existing = $stmt->fetch();
            if (!$existing) {
                $this->pdo->rollBack();
                return null;
            }

            $userChanged = (int) $existing['user_id'] !== (int) $data['user_id'];
            $taskContentChanged = $userChanged
                || (string) ($existing['text_content'] ?? '') !== $data['text_content']
                || (string) ($existing['reference_link'] ?? '') !== $data['reference_link'];
            if ($userChanged && $existing['status'] === 'completed') {
                $this->pdo->rollBack();
                throw new DomainException('A completed assignment cannot be reassigned because that would change its completion history.');
            }

            $sql = 'UPDATE assignments
                    SET user_id = :user_id, title = :title, category = :category,
                        text_content = :text_content, reference_link = :reference_link, tags = :tags';
            if ($taskContentChanged && $existing['status'] === 'pending') {
                $sql .= ', text_copied = 0, link_clicked = 0, completion_prompt_pending = 0';
            }
            $sql .= ' WHERE id = :id AND archived_at IS NULL';
            $update = $this->pdo->prepare($sql);
            $update->execute([
                ':user_id' => (int) $data['user_id'],
                ':title' => $data['title'] !== '' ? $data['title'] : null,
                ':category' => $data['category'] !== '' ? $data['category'] : null,
                ':text_content' => $data['text_content'],
                ':reference_link' => $data['reference_link'] !== '' ? $data['reference_link'] : null,
                ':tags' => $data['tags'] !== '' ? $data['tags'] : null,
                ':id' => $assignmentId,
            ]);

            $this->pdo->commit();
            return $this->findActiveById($assignmentId);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function archiveAdmin(int $assignmentId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id FROM assignments WHERE id = :id AND archived_at IS NULL FOR UPDATE'
            );
            $stmt->execute([':id' => $assignmentId]);
            if (!$stmt->fetch()) {
                $this->pdo->rollBack();
                return false;
            }

            $archive = $this->pdo->prepare(
                'UPDATE assignments SET archived_at = NOW()
                 WHERE id = :id AND archived_at IS NULL'
            );
            $archive->execute([':id' => $assignmentId]);
            if ($archive->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Pending (not-yet-copied) items for a specific user's dashboard.
     */
    public function pendingForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, user_id, title, category, text_content, reference_link, tags, status,
                    assigned_by, assigned_at, completed_at, ip_on_completion
             FROM assignments
             WHERE user_id = :uid AND status = 'pending' AND archived_at IS NULL
             ORDER BY assigned_at ASC, id ASC"
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Completed history for a specific user.
     */
    public function completedForUser(int $userId, int $page = 1, int $perPage = DEFAULT_PAGE_SIZE): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM assignments WHERE user_id = :uid AND status = 'completed' AND archived_at IS NULL");
        $countStmt->execute([':uid' => $userId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT * FROM assignments WHERE user_id = :uid AND status = 'completed' AND archived_at IS NULL
             ORDER BY completed_at DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
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

    /**
     * Atomically mark an assignment completed ONLY if it currently belongs
     * to the given user AND is still pending. Prevents replay / tampering.
     * Returns the completed row (including reference_link for the popup) on
     * success, or null if the assignment was not eligible.
     */
    public function markCompleted(int $assignmentId, int $userId): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM assignments WHERE id = :id AND user_id = :uid AND status = 'pending'
                   AND archived_at IS NULL FOR UPDATE"
            );
            $stmt->execute([':id' => $assignmentId, ':uid' => $userId]);
            $row = $stmt->fetch();

            if (!$row) {
                $this->pdo->rollBack();
                return null;
            }

            $update = $this->pdo->prepare(
                "UPDATE assignments SET status = 'completed', completed_at = NOW(), ip_on_completion = :ip
                 WHERE id = :id"
            );
            $update->execute([':ip' => client_ip(), ':id' => $assignmentId]);

            $this->pdo->commit();
            $row['status'] = 'completed';
            return $row;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Admin: filtered + paginated list across all users.
     */
    public function listAll(array $filters = [], int $page = 1, int $perPage = DEFAULT_PAGE_SIZE): array
    {
        $where = ['a.archived_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'a.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(a.text_content LIKE :search OR a.title LIKE :search OR u.full_name LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(a.assigned_at) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(a.assigned_at) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "SELECT COUNT(*) FROM assignments a JOIN users u ON u.id = a.user_id WHERE $whereSql";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT a.*, u.full_name AS user_full_name, u.username AS user_username
                FROM assignments a JOIN users u ON u.id = a.user_id
                WHERE $whereSql ORDER BY a.assigned_at DESC LIMIT :limit OFFSET :offset";
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

    /**
     * All rows matching filters (unpaginated) — used for CSV export.
     */
    public function exportAll(array $filters = []): array
    {
        $where = ['a.archived_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'a.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT a.id, u.full_name, u.username, a.title, a.category, a.text_content, a.reference_link,
                       a.tags, a.status, a.assigned_at, a.completed_at
                FROM assignments a JOIN users u ON u.id = a.user_id
                WHERE $whereSql ORDER BY a.assigned_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function counts(): array
    {
        $stmt = $this->pdo->query(
            "SELECT
                SUM(status = 'pending') AS pending,
                SUM(status = 'completed') AS completed,
                COUNT(*) AS total
             FROM assignments
             WHERE archived_at IS NULL"
        );
        $row = $stmt->fetch();
        return [
            'pending'   => (int) ($row['pending'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'total'     => (int) ($row['total'] ?? 0),
        ];
    }
}
