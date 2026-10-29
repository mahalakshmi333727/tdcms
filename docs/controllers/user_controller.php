<?php
/**
 * User Controller
 * Handles the "copy" workflow for regular users. All mutating actions are
 * server-verified: the client cannot mark something completed without an
 * eligible, owned, still-pending assignment row.
 */

declare(strict_types=1);

/**
 * AJAX endpoint: mark an assignment as completed after the browser
 * successfully copies its text to the clipboard.
 */
function handle_copy_assignment(PDO $pdo): void
{
    require_method('POST');
    csrf_protect();

    if (!is_logged_in()) {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    if (!rate_limit_check($pdo, 'copy_user_' . current_user_id(), 60, 60)) {
        json_response(['success' => false, 'message' => 'Too many requests. Please slow down.'], 429);
    }

    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
    if ($assignmentId <= 0) {
        json_response(['success' => false, 'message' => 'Invalid assignment.'], 400);
    }

    $assignmentModel = new Assignment($pdo);
    $completed = $assignmentModel->markCompleted($assignmentId, (int) current_user_id());

    if ($completed === null) {
        json_response(['success' => false, 'message' => 'This item is no longer available or was already completed.'], 409);
    }

    (new ActivityLog($pdo))->record(current_user_id(), 'copy_text', 'Copied assignment #' . $assignmentId);

    json_response([
        'success' => true,
        'assignment' => [
            'id'             => (int) $completed['id'],
            'title'          => $completed['title'],
            'reference_link' => $completed['reference_link'],
        ],
    ]);
}
