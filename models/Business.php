<?php
declare(strict_types=1);

final class Business
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function list(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        $perPage = min(10, max(1, $perPage));
        $where = [];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $escapedSearch = strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']);
            $where[] = "(b.name LIKE :business_search ESCAPE '!'
                OR EXISTS (
                    SELECT 1 FROM business_users search_bu
                    JOIN users search_u ON search_u.id = search_bu.user_id
                    WHERE search_bu.business_id = b.id
                      AND search_u.full_name LIKE :user_search ESCAPE '!'
                ))";
            $params[':business_search'] = '%' . $escapedSearch . '%';
            $params[':user_search'] = '%' . $escapedSearch . '%';
        }
        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $where[] = 'EXISTS (
                SELECT 1 FROM business_users filter_bu
                WHERE filter_bu.business_id = b.id AND filter_bu.user_id = :filter_user_id
            )';
            $params[':filter_user_id'] = $userId;
        }
        $dateFrom = (string) ($filters['date_from'] ?? '');
        if ($dateFrom !== '') {
            $where[] = 'b.created_at >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }
        $dateTo = (string) ($filters['date_to'] ?? '');
        if ($dateTo !== '') {
            $where[] = 'b.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)';
            $params[':date_to'] = $dateTo;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM businesses b' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pages = (int) ceil($total / $perPage);
        $page = min(max(1, $page), max(1, $pages));
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT b.*,
                COALESCE((SELECT SUM(bi.imported_count) FROM business_imports bi
                 WHERE bi.business_id = b.id), 0) AS imported_reviews_count,
                (SELECT COUNT(*) FROM business_content bc
                 WHERE bc.business_id = b.id AND bc.archived_at IS NULL) AS total_content_count,
                (SELECT COUNT(*) FROM business_content bc
                 JOIN assignments a ON a.business_content_id = bc.id
                 WHERE bc.business_id = b.id AND bc.archived_at IS NULL
                   AND a.status = 'completed') AS used_content_count,
                (SELECT COUNT(*) FROM business_content bc
                 JOIN assignments a ON a.business_content_id = bc.id
                 WHERE bc.business_id = b.id AND bc.archived_at IS NULL
                   AND a.status = 'pending' AND a.archived_at IS NULL) AS active_content_count,
                (SELECT bu.user_id FROM business_users bu
                 WHERE bu.business_id = b.id
                 ORDER BY bu.created_at DESC, bu.user_id DESC LIMIT 1) AS assigned_user_id,
                (SELECT u.full_name FROM business_users bu
                 JOIN users u ON u.id = bu.user_id
                 WHERE bu.business_id = b.id
                 ORDER BY bu.created_at DESC, bu.user_id DESC LIMIT 1) AS assigned_users,
                (SELECT bi.original_filename FROM business_imports bi
                 WHERE bi.business_id = b.id
                 ORDER BY bi.created_at DESC, bi.id DESC LIMIT 1) AS last_import_filename,
                (SELECT bi.imported_count FROM business_imports bi
                 WHERE bi.business_id = b.id
                 ORDER BY bi.created_at DESC, bi.id DESC LIMIT 1) AS last_imported_count,
                (SELECT bi.rejected_count FROM business_imports bi
                 WHERE bi.business_id = b.id
                 ORDER BY bi.created_at DESC, bi.id DESC LIMIT 1) AS last_rejected_count
             FROM businesses b
             $whereSql
             ORDER BY b.created_at DESC, b.id DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
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

    public function filterUsers(): array
    {
        return $this->pdo->query(
            'SELECT DISTINCT u.id, u.full_name, u.username
             FROM business_users bu
             JOIN users u ON u.id = bu.user_id
             ORDER BY u.full_name, u.id'
        )->fetchAll();
    }

    public function find(int $businessId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT b.*,
                COALESCE((SELECT SUM(bi.imported_count) FROM business_imports bi
                 WHERE bi.business_id = b.id), 0) AS imported_reviews_count,
                (SELECT COUNT(*) FROM business_content bc
                 WHERE bc.business_id = b.id AND bc.archived_at IS NULL) AS total_content_count,
                (SELECT COUNT(*) FROM business_content bc
                 JOIN assignments a ON a.business_content_id = bc.id
                 WHERE bc.business_id = b.id AND bc.archived_at IS NULL
                   AND a.status = 'completed') AS used_content_count,
                (SELECT COUNT(*) FROM business_content bc
                 JOIN assignments a ON a.business_content_id = bc.id
                 WHERE bc.business_id = b.id AND bc.archived_at IS NULL
                   AND a.status = 'pending' AND a.archived_at IS NULL) AS active_content_count
             FROM businesses b
             WHERE b.id = :id"
        );
        $stmt->execute([':id' => $businessId]);
        $business = $stmt->fetch();
        if ($business === false) {
            return null;
        }

        $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata'));
        $tomorrow = $today->modify('+1 day');
        $daily = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM assignments a
             JOIN business_content bc ON bc.id = a.business_content_id
             WHERE bc.business_id = :business_id
               AND a.assigned_at >= :day_start AND a.assigned_at < :day_end'
        );
        $daily->execute([
            ':business_id' => $businessId,
            ':day_start' => $today->format('Y-m-d H:i:s'),
            ':day_end' => $tomorrow->format('Y-m-d H:i:s'),
        ]);
        $business['today_assigned_count'] = (int) $daily->fetchColumn();
        $business['remaining_daily_capacity'] = max(
            0,
            (int) $business['daily_assignment_limit'] - $business['today_assigned_count']
        );

        return $business;
    }

    public function activeUsers(): array
    {
        return $this->pdo->query(
            "SELECT id, full_name, username FROM users
             WHERE role = 'user' AND status = 'active'
             ORDER BY full_name, id"
        )->fetchAll();
    }

    public function choices(): array
    {
        return $this->pdo->query(
            'SELECT id, name FROM businesses ORDER BY name, id'
        )->fetchAll();
    }

    public function nameExists(string $name, ?int $excludeBusinessId = null): bool
    {
        $sql = 'SELECT 1 FROM businesses WHERE name = :name';
        if ($excludeBusinessId !== null) {
            $sql .= ' AND id <> :exclude_id';
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $params = [':name' => $name];
        if ($excludeBusinessId !== null) {
            $params[':exclude_id'] = $excludeBusinessId;
        }
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }

    public function deleteIfEmpty(int $businessId): void
    {
        $this->pdo->beginTransaction();
        try {
            $business = $this->pdo->prepare('SELECT id FROM businesses WHERE id = :id FOR UPDATE');
            $business->execute([':id' => $businessId]);
            if ($business->fetchColumn() === false) {
                throw new DomainException('Business not found.');
            }

            $dependencies = $this->pdo->prepare(
                'SELECT
                    (SELECT COUNT(*) FROM business_content WHERE business_id = :content_business_id) AS content_count,
                    (SELECT COUNT(*) FROM business_imports WHERE business_id = :import_business_id) AS import_count'
            );
            $dependencies->execute([
                ':content_business_id' => $businessId,
                ':import_business_id' => $businessId,
            ]);
            $counts = $dependencies->fetch();
            if ((int) $counts['content_count'] > 0 || (int) $counts['import_count'] > 0) {
                throw new DomainException(
                    'This business cannot be deleted while it has content or import history. Existing records and assignments were preserved.'
                );
            }

            $history = $this->pdo->prepare('DELETE FROM business_user_history WHERE business_id = :id');
            $history->execute([':id' => $businessId]);
            $delete = $this->pdo->prepare('DELETE FROM businesses WHERE id = :id');
            $delete->execute([':id' => $businessId]);
            if ($delete->rowCount() !== 1) {
                throw new DomainException('Business not found.');
            }
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function availableUsersForBusiness(int $businessId): array
    {
        return $this->activeUsers();
    }

    public function users(int $businessId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.full_name, u.username, u.status
             FROM business_users bu
             JOIN users u ON u.id = bu.user_id
             WHERE bu.business_id = :business_id
             ORDER BY bu.created_at DESC, bu.user_id DESC"
        );
        $stmt->execute([':business_id' => $businessId]);
        return $stmt->fetchAll();
    }

    public function categories(): array
    {
        return $this->pdo->query(
            'SELECT DISTINCT category FROM businesses ORDER BY category'
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    public function content(int $businessId, ?string $status = null): array
    {
        $statusFilter = match ($status) {
            'used' => " AND a.status = 'completed'",
            'active' => " AND a.status = 'pending' AND a.archived_at IS NULL",
            default => '',
        };
        $stmt = $this->pdo->prepare(
            "SELECT bc.*,
                    a.id AS assignment_id,
                    a.status AS assignment_status,
                    a.archived_at AS assignment_archived_at,
                    a.assigned_at,
                    a.completed_at,
                    u.full_name AS assigned_user
             FROM business_content bc
             LEFT JOIN assignments a ON a.business_content_id = bc.id
             LEFT JOIN users u ON u.id = a.user_id
             WHERE bc.business_id = :business_id AND bc.archived_at IS NULL
             $statusFilter
             ORDER BY bc.created_at DESC, bc.id DESC"
        );
        $stmt->execute([':business_id' => $businessId]);
        return $stmt->fetchAll();
    }

    public function updateContent(int $businessId, int $contentId, array $data): void
    {
        $existing = $this->pdo->prepare(
            'SELECT bc.reference_link, b.reference_link AS business_reference_link
             FROM business_content bc
             JOIN businesses b ON b.id = bc.business_id
             WHERE bc.id = :id AND bc.business_id = :business_id AND bc.archived_at IS NULL
             FOR UPDATE'
        );
        $existing->execute([':id' => $contentId, ':business_id' => $businessId]);
        $existingContent = $existing->fetch();
        if ($existingContent === false) {
            throw new DomainException('The selected content was not found for this business.');
        }
        $referenceChanged = $existingContent['reference_link'] !== ($data['reference_link'] ?? null);

        $stmt = $this->pdo->prepare(
            'UPDATE business_content
             SET title = :title, text_content = :text_content, reference_link = :reference_link,
                 content_hash = :content_hash
             WHERE id = :id AND business_id = :business_id AND archived_at IS NULL'
        );
        $contentHash = hash(
            'sha256',
            $data['title'] . "\0" . $data['text_content'] . "\0" . ($data['reference_link'] ?? '')
        );
        $stmt->execute([
            ':title' => $data['title'],
            ':text_content' => $data['text_content'],
            ':reference_link' => $data['reference_link'],
            ':content_hash' => $contentHash,
            ':id' => $contentId,
            ':business_id' => $businessId,
        ]);
        if ($stmt->rowCount() === 0) {
            $check = $this->pdo->prepare(
                'SELECT 1 FROM business_content
                 WHERE id = :id AND business_id = :business_id AND archived_at IS NULL'
            );
            $check->execute([':id' => $contentId, ':business_id' => $businessId]);
            if ($check->fetchColumn() === false) {
                throw new DomainException('The selected content was not found for this business.');
            }
        }
        if ($referenceChanged) {
            $assignment = $this->pdo->prepare(
                "UPDATE assignments
                 SET title = :title, text_content = :text_content, reference_link = :reference_link
                 WHERE business_content_id = :content_id
                   AND status = 'pending' AND archived_at IS NULL"
            );
            $assignment->execute([
                ':title' => $data['title'],
                ':text_content' => $data['text_content'],
                ':reference_link' => $data['reference_link'] ?? $existingContent['business_reference_link'],
                ':content_id' => $contentId,
            ]);
        } else {
            $assignment = $this->pdo->prepare(
                "UPDATE assignments
                 SET title = :title, text_content = :text_content
                 WHERE business_content_id = :content_id
                   AND status = 'pending' AND archived_at IS NULL"
            );
            $assignment->execute([
                ':title' => $data['title'],
                ':text_content' => $data['text_content'],
                ':content_id' => $contentId,
            ]);
        }
    }

    public function archiveContent(int $businessId, int $contentId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE business_content SET archived_at = NOW()
             WHERE id = :id AND business_id = :business_id AND archived_at IS NULL'
        );
        $stmt->execute([':id' => $contentId, ':business_id' => $businessId]);
        if ($stmt->rowCount() !== 1) {
            throw new DomainException('The selected content was not found for this business.');
        }
    }

    public function imports(int $businessId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT bi.*, u.full_name AS imported_by_name
             FROM business_imports bi
             JOIN users u ON u.id = bi.imported_by
             WHERE bi.business_id = :business_id
             ORDER BY bi.created_at DESC, bi.id DESC"
        );
        $stmt->execute([':business_id' => $businessId]);
        return $stmt->fetchAll();
    }

    public function create(array $data, array $userIds, int $adminId, ?array $import = null): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO businesses
                    (name, category, reference_link, daily_assignment_limit, created_by)
                 VALUES (:name, :category, :reference_link, :daily_limit, :created_by)'
            );
            $stmt->execute([
                ':name' => $data['name'],
                ':category' => '',
                ':reference_link' => $data['reference_link'] ?? null,
                ':daily_limit' => $data['daily_assignment_limit'],
                ':created_by' => $adminId,
            ]);
            $businessId = (int) $this->pdo->lastInsertId();
            $this->replaceUsers($businessId, $userIds, true, $adminId);
            if ($import !== null) {
                $this->storeImport($businessId, $adminId, $import);
            }
            $this->pdo->commit();
            return $businessId;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function update(int $businessId, array $data, array $userIds, int $adminId): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE businesses
                 SET name = :name, reference_link = :reference_link,
                     daily_assignment_limit = :daily_limit
                 WHERE id = :id'
            );
            $stmt->execute([
                ':name' => $data['name'],
                ':reference_link' => $data['reference_link'] ?? null,
                ':daily_limit' => $data['daily_assignment_limit'],
                ':id' => $businessId,
            ]);
            if ($stmt->rowCount() === 0 && $this->find($businessId) === null) {
                throw new DomainException('Business not found.');
            }
            $this->replaceUsers($businessId, $userIds, true, $adminId);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function replaceAssignedUser(int $businessId, int $userId, int $changedBy): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->requireBusinessForUpdate($businessId);
            $this->replaceUsers($businessId, [$userId], true, $changedBy);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function removeUser(int $businessId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM business_users WHERE business_id = :business_id AND user_id = :user_id'
        );
        $stmt->execute([':business_id' => $businessId, ':user_id' => $userId]);
    }

    public function import(int $businessId, int $adminId, array $import): array
    {
        $this->pdo->beginTransaction();
        try {
            $this->requireBusinessForUpdate($businessId);
            $summary = $this->storeImport($businessId, $adminId, $import);
            $this->pdo->commit();
            return $summary;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function assignContent(int $businessId, int $contentId, int $userId, int $adminId): int
    {
        $this->pdo->beginTransaction();
        try {
            $businessStmt = $this->pdo->prepare(
                'SELECT id, category, reference_link, daily_assignment_limit
                 FROM businesses WHERE id = :id FOR UPDATE'
            );
            $businessStmt->execute([':id' => $businessId]);
            $business = $businessStmt->fetch();
            if ($business === false) {
                throw new DomainException('Business not found.');
            }

            $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata'));
            $tomorrow = $today->modify('+1 day');
            $countStmt = $this->pdo->prepare(
                'SELECT COUNT(*)
                 FROM assignments a
                 JOIN business_content bc ON bc.id = a.business_content_id
                 WHERE bc.business_id = :business_id
                   AND a.assigned_at >= :day_start AND a.assigned_at < :day_end'
            );
            $countStmt->execute([
                ':business_id' => $businessId,
                ':day_start' => $today->format('Y-m-d H:i:s'),
                ':day_end' => $tomorrow->format('Y-m-d H:i:s'),
            ]);
            $todayCount = (int) $countStmt->fetchColumn();
            if ($todayCount >= (int) $business['daily_assignment_limit']) {
                throw new DomainException(
                    'Daily assignment limit reached. Remaining capacity: 0.'
                );
            }

            $contentStmt = $this->pdo->prepare(
                "SELECT bc.id, bc.title, bc.text_content, bc.reference_link
                 FROM business_content bc
                 JOIN business_users bu ON bu.business_id = bc.business_id
                 JOIN users u ON u.id = bu.user_id
                 WHERE bc.id = :content_id AND bc.business_id = :business_id
                   AND bu.user_id = :user_id AND u.role = 'user' AND u.status = 'active'
                   AND bc.archived_at IS NULL
                 FOR UPDATE"
            );
            $contentStmt->execute([
                ':content_id' => $contentId,
                ':business_id' => $businessId,
                ':user_id' => $userId,
            ]);
            $content = $contentStmt->fetch();
            if ($content === false) {
                throw new DomainException(
                    'Choose an available content item and an active user assigned to this business.'
                );
            }

            $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
            $deadline = $now->setTime(16, 0, 0);
            $link = $content['reference_link'] !== null && $content['reference_link'] !== ''
                ? $content['reference_link']
                : $business['reference_link'];
            $insert = $this->pdo->prepare(
                "INSERT INTO assignments
                    (user_id, title, category, text_content, reference_link, tags,
                     business_content_id, assigned_by, status, assigned_at, due_at)
                 VALUES
                    (:user_id, :title, :category, :text_content, :reference_link, '',
                     :content_id, :assigned_by, 'pending', :assigned_at, :due_at)"
            );
            $insert->execute([
                ':user_id' => $userId,
                ':title' => $content['title'],
                ':category' => $business['category'],
                ':text_content' => $content['text_content'],
                ':reference_link' => $link,
                ':content_id' => $contentId,
                ':assigned_by' => $adminId,
                ':assigned_at' => $now->format('Y-m-d H:i:s'),
                ':due_at' => $deadline->format('Y-m-d H:i:s'),
            ]);
            $assignmentId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $assignmentId;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function assignBulkTasks(int $businessId, int $userId, int $adminId, array $tasks): int
    {
        if ($tasks === []) {
            return 0;
        }

        $this->pdo->beginTransaction();
        try {
            $businessStmt = $this->pdo->prepare(
                'SELECT id, category, reference_link, daily_assignment_limit
                 FROM businesses WHERE id = :id FOR UPDATE'
            );
            $businessStmt->execute([':id' => $businessId]);
            $business = $businessStmt->fetch();
            if ($business === false) {
                throw new DomainException('Business not found.');
            }

            $memberStmt = $this->pdo->prepare(
                "SELECT u.id
                 FROM business_users bu
                 JOIN users u ON u.id = bu.user_id
                 WHERE bu.business_id = :business_id AND bu.user_id = :user_id
                   AND u.role = 'user' AND u.status = 'active'
                 FOR UPDATE"
            );
            $memberStmt->execute([':business_id' => $businessId, ':user_id' => $userId]);
            if ($memberStmt->fetchColumn() === false) {
                throw new DomainException('The selected user must be active and assigned to this business.');
            }

            $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata'));
            $tomorrow = $today->modify('+1 day');
            $countStmt = $this->pdo->prepare(
                'SELECT COUNT(*)
                 FROM assignments a
                 JOIN business_content bc ON bc.id = a.business_content_id
                 WHERE bc.business_id = :business_id
                   AND a.assigned_at >= :day_start AND a.assigned_at < :day_end'
            );
            $countStmt->execute([
                ':business_id' => $businessId,
                ':day_start' => $today->format('Y-m-d H:i:s'),
                ':day_end' => $tomorrow->format('Y-m-d H:i:s'),
            ]);
            $todayCount = (int) $countStmt->fetchColumn();
            $taskCount = count($tasks);
            if ($todayCount + $taskCount > (int) $business['daily_assignment_limit']) {
                $remaining = max(0, (int) $business['daily_assignment_limit'] - $todayCount);
                throw new DomainException(
                    'This bulk request exceeds the business daily assignment limit. Remaining capacity: ' . $remaining . '.'
                );
            }

            $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
            $assignedAt = $now->format('Y-m-d H:i:s');
            $deadlineAt = $now->setTime(16, 0, 0)->format('Y-m-d H:i:s');
            $contentInsert = $this->pdo->prepare(
                'INSERT INTO business_content
                    (business_id, import_id, content_hash, source_record, title, text_content, reference_link)
                 VALUES
                    (:business_id, NULL, :content_hash, :source_record, :title, :text_content, :reference_link)'
            );
            $assignmentInsert = $this->pdo->prepare(
                "INSERT INTO assignments
                    (user_id, title, category, text_content, reference_link, tags,
                     business_content_id, assigned_by, status, assigned_at, due_at)
                 VALUES
                    (:user_id, :title, :category, :text_content, :reference_link, :tags,
                     :business_content_id, :assigned_by, 'pending', :assigned_at, :due_at)"
            );
            $seen = [];
            foreach (array_values($tasks) as $index => $task) {
                $title = trim((string) ($task['title'] ?? ''));
                $text = trim((string) ($task['text_content'] ?? ''));
                $text = str_replace(["\r\n", "\r"], "\n", $text);
                $link = trim((string) ($task['reference_link'] ?? ''));
                $tags = trim((string) ($task['tags'] ?? ''));
                $titleLength = preg_match_all('/./us', $title);
                if ($title === '' || $titleLength === false || $titleLength > 150) {
                    throw new DomainException('Business-linked bulk tasks require a title of 150 characters or fewer.');
                }
                if ($text === '' || strlen($text) > 65535) {
                    throw new DomainException('Business-linked bulk task text is required and must be 65,535 bytes or fewer.');
                }
                if ($link !== '' && !valid_business_http_url($link, 500)) {
                    throw new DomainException('A business-linked bulk task has an invalid reference link.');
                }
                $contentHash = hash('sha256', $title . "\0" . $text . "\0" . $link);
                if (isset($seen[$contentHash])) {
                    throw new DomainException('The bulk request contains duplicate content for this business. No tasks were assigned.');
                }
                $seen[$contentHash] = true;

                $contentInsert->execute([
                    ':business_id' => $businessId,
                    ':content_hash' => $contentHash,
                    ':source_record' => $index + 1,
                    ':title' => $title,
                    ':text_content' => $text,
                    ':reference_link' => $link !== '' ? $link : null,
                ]);
                $contentId = (int) $this->pdo->lastInsertId();
                $assignmentInsert->execute([
                    ':user_id' => $userId,
                    ':title' => $title,
                    ':category' => $business['category'],
                    ':text_content' => $text,
                    ':reference_link' => $link !== '' ? $link : $business['reference_link'],
                    ':tags' => $tags,
                    ':business_content_id' => $contentId,
                    ':assigned_by' => $adminId,
                    ':assigned_at' => $assignedAt,
                    ':due_at' => $deadlineAt,
                ]);
            }

            $this->pdo->commit();
            return $taskCount;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private function replaceUsers(int $businessId, array $userIds, bool $requireOne, int $changedBy): void
    {
        $userIds = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            static fn (int $id): bool => $id > 0
        )));
        if (count($userIds) > 1) {
            throw new DomainException('A business can have only one assigned employee.');
        }
        if ($requireOne && $userIds === []) {
            throw new DomainException('Select one active employee.');
        }
        $this->requireBusinessForUpdate($businessId);
        if ($userIds !== []) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM users
                 WHERE role = 'user' AND status = 'active' AND id IN ($placeholders)"
            );
            $stmt->execute($userIds);
            if ((int) $stmt->fetchColumn() !== count($userIds)) {
                throw new DomainException('The selected user is not an active employee.');
            }
        }

        $newUserId = $userIds[0] ?? null;
        $history = $this->pdo->prepare(
            'INSERT INTO business_user_history (business_id, user_id, assigned_at, ended_at, changed_by)
             SELECT business_id, user_id, created_at, NOW(), :changed_by
             FROM business_users
             WHERE business_id = :business_id
               AND (:new_user_id_null IS NULL OR user_id <> :new_user_id)'
        );
        $history->execute([
            ':changed_by' => $changedBy,
            ':business_id' => $businessId,
            ':new_user_id_null' => $newUserId,
            ':new_user_id' => $newUserId,
        ]);

        if ($newUserId !== null) {
            $reassign = $this->pdo->prepare(
                "UPDATE assignments a
                 JOIN business_content bc ON bc.id = a.business_content_id
                 SET a.user_id = :assigned_user_id, a.text_copied = 0, a.link_clicked = 0,
                     a.completion_prompt_pending = 0
                 WHERE bc.business_id = :business_id
                   AND a.status = 'pending' AND a.archived_at IS NULL
                   AND a.user_id <> :previous_user_id"
            );
            $reassign->execute([
                ':assigned_user_id' => $newUserId,
                ':business_id' => $businessId,
                ':previous_user_id' => $newUserId,
            ]);

            $notification = $this->pdo->prepare(
                "UPDATE admin_notifications n
                 JOIN assignments a ON a.id = n.assignment_id
                 JOIN business_content bc ON bc.id = a.business_content_id
                 JOIN users u ON u.id = :user_id
                 SET n.user_id = :notification_user_id, n.user_name = u.full_name
                 WHERE bc.business_id = :business_id
                   AND a.status = 'pending' AND a.archived_at IS NULL"
            );
            $notification->execute([
                ':user_id' => $newUserId,
                ':notification_user_id' => $newUserId,
                ':business_id' => $businessId,
            ]);
        }

        $delete = $this->pdo->prepare('DELETE FROM business_users WHERE business_id = :id');
        $delete->execute([':id' => $businessId]);
        if ($newUserId !== null) {
            $insert = $this->pdo->prepare(
                'INSERT INTO business_users (business_id, user_id) VALUES (:business_id, :user_id)'
            );
            $insert->execute([':business_id' => $businessId, ':user_id' => $newUserId]);
        }
    }

    private function requireBusinessForUpdate(int $businessId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM businesses WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $businessId]);
        if ($stmt->fetchColumn() === false) {
            throw new DomainException('Business not found.');
        }
    }

    private function storeImport(int $businessId, int $adminId, array $import): array
    {
        $records = $import['records'];
        $errors = $import['errors'];
        $stmt = $this->pdo->prepare(
            'INSERT INTO business_imports
                (business_id, imported_by, original_filename, rejected_count, failure_summary)
             VALUES (:business_id, :imported_by, :filename, :rejected_count, :failure_summary)'
        );
        $stmt->execute([
            ':business_id' => $businessId,
            ':imported_by' => $adminId,
            ':filename' => substr((string) $import['filename'], 0, 255),
            ':rejected_count' => count($errors),
            ':failure_summary' => $errors === [] ? null : json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $importId = (int) $this->pdo->lastInsertId();
        $insert = $this->pdo->prepare(
            'INSERT INTO business_content
                (business_id, import_id, content_hash, source_record, title, text_content, reference_link)
             VALUES
                (:business_id, :import_id, :content_hash, :source_record, :title, :text_content, :reference_link)'
        );
        $exists = $this->pdo->prepare(
            'SELECT 1 FROM business_content WHERE business_id = :business_id AND content_hash = :hash LIMIT 1'
        );
        $imported = 0;
        foreach ($records as $record) {
            $exists->execute([':business_id' => $businessId, ':hash' => $record['content_hash']]);
            if ($exists->fetchColumn() !== false) {
                $errors[] = 'Record ' . $record['source_record'] . ': duplicate content already exists for this business.';
                continue;
            }
            $insert->execute([
                ':business_id' => $businessId,
                ':import_id' => $importId,
                ':content_hash' => $record['content_hash'],
                ':source_record' => $record['source_record'],
                ':title' => $record['title'],
                ':text_content' => $record['text_content'],
                ':reference_link' => $record['reference_link'],
            ]);
            $imported++;
        }
        $update = $this->pdo->prepare(
            'UPDATE business_imports
             SET imported_count = :imported_count, rejected_count = :rejected_count,
                 failure_summary = :failure_summary
             WHERE id = :id'
        );
        $update->execute([
            ':imported_count' => $imported,
            ':rejected_count' => count($errors),
            ':failure_summary' => $errors === [] ? null : json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':id' => $importId,
        ]);
        return ['imported' => $imported, 'errors' => $errors];
    }

    private function requireBusiness(int $businessId): void
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM businesses WHERE id = :id');
        $stmt->execute([':id' => $businessId]);
        if ($stmt->fetchColumn() === false) {
            throw new DomainException('Business not found.');
        }
    }
}
