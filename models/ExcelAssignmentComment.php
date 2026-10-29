<?php
/**
 * Excel Assignment Comment Model
 * Stores employee comments for Excel assignments.
 */

declare(strict_types=1);

final class ExcelAssignmentComment
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO excel_assignment_comments (assignment_id, user_id, comment_text, created_at, updated_at)
             VALUES (:assignment_id, :user_id, :comment_text, NOW(), NOW())'
        );

        $stmt->execute([
            ':assignment_id' => (int) $data['assignment_id'],
            ':user_id'       => (int) $data['user_id'],
            ':comment_text'  => $data['comment_text'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function listForAssignment(int $assignmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, u.full_name AS user_full_name, u.username AS user_username
             FROM excel_assignment_comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.assignment_id = :assignment_id
             ORDER BY c.created_at ASC'
        );
        $stmt->execute([':assignment_id' => $assignmentId]);
        return $stmt->fetchAll();
    }
}
