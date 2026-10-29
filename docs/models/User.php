<?php
/**
 * User Model
 * All queries use prepared statements via PDO.
 */

declare(strict_types=1);

final class User
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :e';
        $params = [':e' => $email];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :u';
        $params = [':u' => $username];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (full_name, username, email, password_hash, role, status, password_changed_at)
             VALUES (:full_name, :username, :email, :password_hash, :role, :status, NOW())'
        );
        $stmt->execute([
            ':full_name'      => $data['full_name'],
            ':username'       => $data['username'],
            ':email'          => $data['email'],
            ':password_hash'  => $data['password_hash'],
            ':role'           => $data['role'],
            ':status'         => $data['status'] ?? 'active',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET full_name = :full_name, username = :username, email = :email, role = :role
             WHERE id = :id'
        );
        return $stmt->execute([
            ':full_name' => $data['full_name'],
            ':username'  => $data['username'],
            ':email'     => $data['email'],
            ':role'      => $data['role'],
            ':id'        => $id,
        ]);
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :p, password_changed_at = NOW() WHERE id = :id');
        return $stmt->execute([':p' => $passwordHash, ':id' => $id]);
    }

    public function setStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE users SET status = :s WHERE id = :id');
        return $stmt->execute([':s' => $status, ':id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id AND role != "admin"');
        return $stmt->execute([':id' => $id]);
    }

    public function recordFailedAttempt(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $check = $this->pdo->prepare('SELECT failed_attempts FROM users WHERE id = :id');
        $check->execute([':id' => $id]);
        $attempts = (int) $check->fetchColumn();

        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockUntil = (new DateTime())->modify('+' . LOGIN_LOCKOUT_MINUTES . ' minutes')->format('Y-m-d H:i:s');
            $lock = $this->pdo->prepare('UPDATE users SET locked_until = :l WHERE id = :id');
            $lock->execute([':l' => $lockUntil, ':id' => $id]);
        }
    }

    public function resetFailedAttempts(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function isLocked(array $user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }
        return new DateTime($user['locked_until']) > new DateTime();
    }

    /**
     * Paginated + filtered list of users for the admin panel.
     */
    public function list(array $filters = [], int $page = 1, int $perPage = DEFAULT_PAGE_SIZE): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(full_name LIKE :search OR username LIKE :search OR email LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['role'])) {
            $where[] = 'role = :role';
            $params[':role'] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT id, full_name, username, email, role, status, last_login_at, created_at
                FROM users WHERE $whereSql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
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

    public function allActiveUsers(): array
    {
        $stmt = $this->pdo->query("SELECT id, full_name, username FROM users WHERE role = 'user' AND status = 'active' ORDER BY full_name");
        return $stmt->fetchAll();
    }
}
