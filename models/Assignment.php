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
     * Create a distinct assignment row, including when the same user already
     * has a pending assignment with identical content.
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

    /**
     * Shared insert path used by create(), bulkCreate(), and
     * bulkCreateFromTasks(). Each call creates a separate assignment row.
     */
    private function insertOne(int $userId, string $title, string $category, string $text, ?string $link, string $tags, int $assignedBy): array
    {
        $assignedAt = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
        $stmt = $this->pdo->prepare(
            'INSERT INTO assignments
                (user_id, title, category, text_content, reference_link, tags, assigned_by, status, assigned_at, due_at)
             VALUES
                (:user_id, :title, :category, :text_content, :reference_link, :tags, :assigned_by, "pending", :assigned_at, :due_at)'
        );
        $stmt->execute([
            ':user_id'        => $userId,
            ':title'          => $title !== '' ? $title : null,
            ':category'       => $category !== '' ? $category : null,
            ':text_content'   => $text,
            ':reference_link' => $link !== null && $link !== '' ? $link : null,
            ':tags'           => $tags !== '' ? $tags : null,
            ':assigned_by'    => $assignedBy,
            ':assigned_at'    => $assignedAt->format('Y-m-d H:i:s'),
            ':due_at'         => $assignedAt->setTime(16, 0)->format('Y-m-d H:i:s'),
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
     * multiple users in one transaction — every task/user pair is assigned,
     * including identical task rows in the same import.
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
                'SELECT user_id, status, business_content_id FROM assignments
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
            if ($userChanged && $existing['business_content_id'] !== null) {
                $member = $this->pdo->prepare(
                    "SELECT 1
                     FROM business_content bc
                     JOIN business_users bu ON bu.business_id = bc.business_id
                     JOIN users u ON u.id = bu.user_id
                     WHERE bc.id = :content_id AND bu.user_id = :user_id
                       AND u.role = 'user' AND u.status = 'active'"
                );
                $member->execute([
                    ':content_id' => (int) $existing['business_content_id'],
                    ':user_id' => (int) $data['user_id'],
                ]);
                if ($member->fetchColumn() === false) {
                    $this->pdo->rollBack();
                    throw new DomainException('Business content can only be reassigned to an active user assigned to that business.');
                }
            }

            $sql = 'UPDATE assignments
                    SET user_id = :user_id, title = :title, category = :category,
                        text_content = :text_content, reference_link = :reference_link, tags = :tags';
            if ($taskContentChanged && $existing['status'] === 'pending') {
                // Progress belongs to the specific employee and task content version.
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

            // Preserve the assignment row as the completion and reporting record.
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
        return $this->pendingRowsForUser($userId);
    }

    /**
     * Paginated pending items for a specific user's dashboard.
     *
     * @return array{rows: array, total: int, page: int, pages: int}
     */
    public function pendingPageForUser(int $userId, int $page = 1, int $perPage = DEFAULT_PAGE_SIZE): array
    {
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM assignments
             WHERE user_id = :uid AND status = 'pending' AND archived_at IS NULL"
        );
        $countStmt->execute([':uid' => $userId]);
        $total = (int) $countStmt->fetchColumn();
        $pages = (int) ceil($total / $perPage);
        $page = min(max(1, $page), max(1, $pages));
        $offset = ($page - 1) * $perPage;

        return [
            'rows' => $this->pendingRowsForUser($userId, $perPage, $offset),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    private function pendingRowsForUser(int $userId, ?int $limit = null, int $offset = 0): array
    {
        $sql =
            "SELECT a.id, a.user_id, a.title, a.category, a.tags, a.text_content,
                    COALESCE(NULLIF(b.name, ''), NULLIF(a.title, ''), 'Review task') AS business_name,
                    CASE
                        WHEN a.business_content_id IS NOT NULL
                          OR LOWER(TRIM(COALESCE(a.category, ''))) = 'review' THEN 'Review'
                        ELSE COALESCE(NULLIF(TRIM(a.category), ''), 'Review')
                    END AS task_type,
                    CASE WHEN a.reference_link IS NULL OR a.reference_link = '' THEN 0 ELSE 1 END AS has_reference_link,
                    a.text_copied, a.link_clicked,
                    CASE WHEN TRIM(a.text_content) = '' THEN 0 ELSE a.completion_prompt_pending END AS completion_prompt_pending,
                    a.status,
                    a.assigned_by, a.assigned_at
             FROM assignments a
             LEFT JOIN business_content bc ON bc.id = a.business_content_id
             LEFT JOIN businesses b ON b.id = bc.business_id
             WHERE a.user_id = :uid AND a.status = 'pending' AND a.archived_at IS NULL
             ORDER BY a.assigned_at ASC, a.id ASC";
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }
        $stmt = $this->pdo->prepare($sql);
        if ($limit !== null) {
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt->execute([':uid' => $userId]);
        }
        return $stmt->fetchAll();
    }

    public function recordTaskProgress(int $assignmentId, int $userId, array $updates): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM assignments
                 WHERE id = :id AND user_id = :uid AND status = 'pending'
                   AND archived_at IS NULL FOR UPDATE"
            );
            $stmt->execute([':id' => $assignmentId, ':uid' => $userId]);
            $row = $stmt->fetch();

            if (!$row) {
                $this->pdo->rollBack();
                return null;
            }

            if (trim((string) ($row['text_content'] ?? '')) === '') {
                throw new DomainException('This task has no original review content and cannot be processed.');
            }

            $textCopied = isset($updates['text_copied']) ? (int) ((bool) $updates['text_copied']) : (int) (bool) $row['text_copied'];
            $linkClicked = isset($updates['link_clicked']) ? (int) ((bool) $updates['link_clicked']) : (int) (bool) $row['link_clicked'];
            if (!empty($updates['link_clicked']) && !$textCopied) {
                $this->pdo->rollBack();
                return null;
            }

            $promptPending = (int) (bool) $row['completion_prompt_pending'];
            if ($textCopied && (empty($row['reference_link']) || $linkClicked)) {
                $promptPending = 1;
            }

            $update = $this->pdo->prepare(
                "UPDATE assignments
                 SET text_copied = :text_copied,
                     link_clicked = :link_clicked,
                     completion_prompt_pending = :completion_prompt_pending
                 WHERE id = :id"
            );
            $update->execute([
                ':text_copied' => $textCopied,
                ':link_clicked' => $linkClicked,
                ':completion_prompt_pending' => $promptPending,
                ':id' => $assignmentId,
            ]);

            $this->pdo->commit();
            $row['text_copied'] = $textCopied;
            $row['link_clicked'] = $linkClicked;
            $row['status'] = 'pending';
            $row['completion_prompt_pending'] = $promptPending;
            return $row;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function keepCompletionPromptPending(int $assignmentId, int $userId): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM assignments
                 WHERE id = :id AND user_id = :uid AND status = 'pending'
                   AND completion_prompt_pending = 1 AND text_copied = 1
                   AND TRIM(text_content) <> ''
                   AND (reference_link IS NULL OR reference_link = '' OR link_clicked = 1)
                 FOR UPDATE"
            );
            $stmt->execute([':id' => $assignmentId, ':uid' => $userId]);
            $assignment = $stmt->fetch();
            if (!$assignment) {
                $this->pdo->rollBack();
                return null;
            }

            // "No" acknowledges the prompt but leaves its server-side state unresolved.
            $this->pdo->commit();

            $assignment['status'] = 'pending';
            $assignment['completion_prompt_pending'] = 1;
            return $assignment;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Completed history for a specific user.
     */
    public function completedForUser(int $userId, int $page = 1, int $perPage = DEFAULT_PAGE_SIZE): array
    {
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM assignments WHERE user_id = :uid AND status = 'completed' AND archived_at IS NULL");
        $countStmt->execute([':uid' => $userId]);
        $total = (int) $countStmt->fetchColumn();
        $pages = (int) ceil($total / $perPage);
        $page = min(max(1, $page), max(1, $pages));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT a.id, a.title, a.category, a.completed_at,
                    COALESCE(NULLIF(b.name, ''), NULLIF(a.title, ''), 'Review task') AS business_name,
                    CASE
                        WHEN a.business_content_id IS NOT NULL
                          OR LOWER(TRIM(COALESCE(a.category, ''))) = 'review' THEN 'Review'
                        ELSE COALESCE(NULLIF(TRIM(a.category), ''), 'Review')
                    END AS task_type
             FROM assignments a
             LEFT JOIN business_content bc ON bc.id = a.business_content_id
             LEFT JOIN businesses b ON b.id = bc.business_id
             WHERE a.user_id = :uid AND a.status = 'completed' AND a.archived_at IS NULL
             ORDER BY a.completed_at DESC, a.id DESC LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
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

    /**
     * Atomically mark an assignment completed ONLY if it currently belongs
     * to the given user AND is still pending. Prevents replay / tampering.
     * The assignment row itself is the unique completion record.
     * Returns null when the task is not owned, not ready, or already done.
     */
    public function markCompleted(int $assignmentId, int $userId): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, user_id, status FROM assignments
                 WHERE id = :id AND user_id = :uid AND status = 'pending'
                   AND archived_at IS NULL
                   AND completion_prompt_pending = 1 AND text_copied = 1
                   AND TRIM(text_content) <> ''
                   AND (reference_link IS NULL OR reference_link = '' OR link_clicked = 1)
                 FOR UPDATE"
            );
            $stmt->execute([':id' => $assignmentId, ':uid' => $userId]);
            $row = $stmt->fetch();

            if (!$row) {
                $this->pdo->rollBack();
                return null;
            }

            $update = $this->pdo->prepare(
                "UPDATE assignments
                 SET status = 'completed', completed_at = NOW(), ip_on_completion = :ip,
                     completion_prompt_pending = 0
                 WHERE id = :id AND user_id = :uid AND status = 'pending'
                   AND archived_at IS NULL
                   AND completion_prompt_pending = 1
                   AND TRIM(text_content) <> ''"
            );
            $update->execute([
                ':ip' => client_ip(),
                ':id' => $assignmentId,
                ':uid' => $userId,
            ]);
            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return null;
            }

            $this->pdo->commit();
            $row['status'] = 'completed';
            $row['completion_prompt_pending'] = 0;
            return $row;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function stateForUser(int $assignmentId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, status, text_copied, link_clicked,
                    CASE WHEN TRIM(text_content) = '' THEN 0 ELSE completion_prompt_pending END AS completion_prompt_pending
             FROM assignments WHERE id = :id AND user_id = :uid AND archived_at IS NULL LIMIT 1"
        );
        $stmt->execute([':id' => $assignmentId, ':uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
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
            $where[] = '(a.text_content LIKE :search_text OR a.title LIKE :search_title OR u.full_name LIKE :search_user)';
            $search = '%' . $filters['search'] . '%';
            $params[':search_text'] = $search;
            $params[':search_title'] = $search;
            $params[':search_user'] = $search;
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
        $pages = (int) ceil($total / $perPage);
        $page = min(max(1, $page), max(1, $pages));

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT a.*, u.full_name AS user_full_name, u.username AS user_username,
                       u.status AS user_status
                FROM assignments a JOIN users u ON u.id = a.user_id
                WHERE $whereSql ORDER BY a.assigned_at DESC, a.id DESC LIMIT :limit OFFSET :offset";
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

    public function contentForAdminUser(int $userId, string $status, int $page = 1, int $perPage = 10): array
    {
        $where = ['user_id = :user_id', 'archived_at IS NULL'];
        $params = [':user_id' => $userId];

        if ($status !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM assignments WHERE $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pages = (int) ceil($total / $perPage);
        $page = min(max(1, $page), max(1, $pages));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT id, title, text_content, status
             FROM assignments
             WHERE $whereSql
             ORDER BY assigned_at DESC, id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
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
