<?php
/**
 * Excel Assignment Model
 * Stores metadata for permanent uploaded Excel assignments.
 */

declare(strict_types=1);

final class ExcelAssignment
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO excel_assignments
                (user_id, title, original_file_name, stored_file_path, file_type,
                 status, assigned_at, created_by, created_at, updated_at)
             VALUES
                (:user_id, :title, :original_file_name, :stored_file_path, :file_type,
                 :status, :assigned_at, :created_by, NOW(), NOW())'
        );

        $stmt->execute([
            ':user_id'            => (int) $data['user_id'],
            ':title'              => $data['title'] !== '' ? $data['title'] : null,
            ':original_file_name' => $data['original_file_name'],
            ':stored_file_path'   => $data['stored_file_path'],
            ':file_type'          => $data['file_type'],
            ':status'             => $data['status'] ?? 'pending',
            ':assigned_at'        => $data['assigned_at'],
            ':created_by'         => (int) $data['created_by'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM excel_assignments WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByIdForUser(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM excel_assignments WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByIdWithUser(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.full_name AS user_full_name, u.username AS user_username
             FROM excel_assignments a
             JOIN users u ON u.id = a.user_id
             WHERE a.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM excel_assignments WHERE user_id = :user_id ORDER BY assigned_at DESC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function listAll(array $filters = [], int $page = 1, int $perPage = DEFAULT_PAGE_SIZE): array
    {
        $where = ['1=1'];
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
            $where[] = '(a.title LIKE :search_title OR u.full_name LIKE :search_name OR u.username LIKE :search_username)';
            $search = '%' . $filters['search'] . '%';
            $params[':search_title'] = $search;
            $params[':search_name'] = $search;
            $params[':search_username'] = $search;
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM excel_assignments a JOIN users u ON u.id = a.user_id WHERE $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT a.*, u.full_name AS user_full_name, u.username AS user_username
                FROM excel_assignments a JOIN users u ON u.id = a.user_id
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

    public function recent(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.full_name AS user_full_name FROM excel_assignments a JOIN users u ON u.id = a.user_id ORDER BY a.assigned_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function counts(): array
    {
        $stmt = $this->pdo->query(
            'SELECT
                COUNT(*) AS total,
                SUM(status = "pending") AS pending,
                SUM(status = "completed") AS completed
             FROM excel_assignments'
        );
        return $stmt->fetch() ?: ['total' => 0, 'pending' => 0, 'completed' => 0];
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE excel_assignments
             SET user_id = :user_id,
                 title = :title,
                 updated_at = NOW()
             WHERE id = :id'
        );
        return $stmt->execute([
            ':user_id' => (int) $data['user_id'],
            ':title'   => $data['title'] !== '' ? $data['title'] : null,
            ':id'      => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM excel_assignments WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
