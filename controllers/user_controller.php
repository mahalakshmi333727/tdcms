<?php
/**
 * User Controller
 * Handles the "copy" workflow for regular users. All mutating actions are
 * server-verified: the client cannot mark something completed without an
 * eligible, owned, still-pending assignment row.
 */

declare(strict_types=1);

function handle_mark_assignment_completed(PDO $pdo): void
{
    require_method('POST');
    csrf_protect();

    if (!is_logged_in()) {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    if (!rate_limit_check($pdo, 'complete_user_' . current_user_id(), 10, 60)) {
        json_response(['success' => false, 'message' => 'Too many completion attempts. Please slow down.'], 429);
    }

    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
    if ($assignmentId <= 0) {
        json_response(['success' => false, 'message' => 'Invalid assignment.'], 400);
    }

    $assignmentModel = new Assignment($pdo);
    $completed = $assignmentModel->markCompleted($assignmentId, (int) current_user_id());
    if ($completed === null) {
        json_response(['success' => false, 'message' => 'Verification failed or the task is not ready to be completed.'], 409);
    }

    (new ActivityLog($pdo))->record(current_user_id(), 'complete_assignment', 'Completed assignment #' . $assignmentId);
    json_response(['success' => true, 'status' => 'completed']);
}

function handle_mark_assignment_pending(PDO $pdo): void
{
    require_method('POST');
    csrf_protect();

    if (!is_logged_in()) {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
    if ($assignmentId <= 0) {
        json_response(['success' => false, 'message' => 'Invalid assignment.'], 400);
    }

    $assignment = (new Assignment($pdo))->keepCompletionPromptPending($assignmentId, (int) current_user_id());
    if ($assignment === null) {
        json_response(['success' => false, 'message' => 'This task is no longer available.'], 409);
    }

    (new ActivityLog($pdo))->record(current_user_id(), 'pending_assignment', 'Kept assignment #' . $assignmentId . ' pending');
    json_response([
        'success' => true,
        'status' => 'pending',
        'completion_prompt_pending' => (bool) $assignment['completion_prompt_pending'],
        'assignment_id' => $assignmentId,
    ]);
}

/**
 * Secure download endpoint for Excel assignment files.
 * Available to both regular users (own assignments only) and admins
 * (any assignment). Streams the file instead of exposing the raw
 * /uploads/ URL, so it works regardless of how the live host serves
 * static files.
 */
function handle_download_excel_assignment(PDO $pdo): void
{
    require_method('GET');

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(404);
        exit('Assignment not found.');
    }

    $excelModel = new ExcelAssignment($pdo);
    if (is_admin()) {
        $assignment = $excelModel->findById($id);
    } else {
        $assignment = $excelModel->findByIdForUser($id, (int) current_user_id());
    }

    if ($assignment === null) {
        http_response_code(404);
        exit('Assignment not found.');
    }

    $storedPath = (string) $assignment['stored_file_path'];

    if ($storedPath === '' || str_contains($storedPath, "\0") || str_contains($storedPath, '..')) {
        http_response_code(404);
        exit('File not found.');
    }

    $uploadsRealPath = realpath(UPLOADS_DIR);
    $fullPath = $uploadsRealPath . DIRECTORY_SEPARATOR . ltrim($storedPath, '/\\');
    $resolvedPath = realpath($fullPath);

    if (
        $uploadsRealPath === false
        || $resolvedPath === false
        || !str_starts_with($resolvedPath, $uploadsRealPath . DIRECTORY_SEPARATOR)
        || !is_file($resolvedPath)
        || !is_readable($resolvedPath)
    ) {
        http_response_code(404);
        exit('File not found.');
    }

    $ext = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));
    $mimeMap = [
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls'  => 'application/vnd.ms-excel',
    ];
    $mimeType = $mimeMap[$ext] ?? 'application/octet-stream';

    $downloadName = $assignment['original_file_name'] !== null && $assignment['original_file_name'] !== ''
        ? $assignment['original_file_name']
        : basename($resolvedPath);
    $downloadName = str_replace(['"', "\r", "\n"], '', $downloadName);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . (string) filesize($resolvedPath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    readfile($resolvedPath);
    exit;
}

/**
 * Task source and reference link are released only after checking the
 * logged-in user's current assignment state in the database.
 */
function handle_copy_assignment(PDO $pdo): void
{
    require_method('POST');
    csrf_protect();

    if (!is_logged_in()) {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }

    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
    $event = (string) ($_POST['event'] ?? 'copy_text');
    $rateLimit = $event === 'status'
        ? rate_limit_check($pdo, 'task_status_user_' . current_user_id(), 240, 60)
        : rate_limit_check($pdo, 'copy_user_' . current_user_id(), 60, 60);
    if (!$rateLimit) {
        json_response(['success' => false, 'message' => 'Too many requests. Please slow down.'], 429);
    }

    if ($assignmentId <= 0) {
        json_response(['success' => false, 'message' => 'Invalid assignment.'], 400);
    }

    $assignmentModel = new Assignment($pdo);
    if ($event === 'status') {
        $state = $assignmentModel->stateForUser($assignmentId, (int) current_user_id());
        if ($state === null) {
            json_response(['success' => false, 'message' => 'This task is no longer available.'], 404);
        }

        json_response([
            'success' => true,
            'status' => $state['status'],
            'text_copied' => (bool) $state['text_copied'],
            'link_clicked' => (bool) $state['link_clicked'],
            'completion_prompt_pending' => (bool) $state['completion_prompt_pending'],
        ]);
    }

    if (!in_array($event, ['copy_text', 'link_click'], true)) {
        json_response(['success' => false, 'message' => 'Invalid task action.'], 400);
    }

    $updates = $event === 'link_click'
        ? ['link_clicked' => true]
        : ['text_copied' => true];
    try {
        $updated = $assignmentModel->recordTaskProgress($assignmentId, (int) current_user_id(), $updates);
    } catch (DomainException $error) {
        json_response(['success' => false, 'message' => $error->getMessage()], 409);
    }

    if ($updated === null) {
        $state = $assignmentModel->stateForUser($assignmentId, (int) current_user_id());
        if ($event === 'copy_text' && ($state['status'] ?? null) === 'completed') {
            json_response([
                'success' => false,
                'message' => 'This task has already been completed. Copying this content is not allowed.',
            ], 409);
        }
        json_response(['success' => false, 'message' => 'This task is no longer available or ready for that action.'], 409);
    }

    (new ActivityLog($pdo))->record(current_user_id(), $event, 'Task progress update for assignment #' . $assignmentId);

    $response = [
        'success' => true,
        'status' => $updated['status'],
        'text_copied' => (bool) $updated['text_copied'],
        'link_clicked' => (bool) $updated['link_clicked'],
        'completion_prompt_pending' => (bool) $updated['completion_prompt_pending'],
        'assignment' => [
            'id' => (int) $updated['id'],
            'title' => $updated['title'],
            'reference_link' => $event === 'link_click' ? $updated['reference_link'] : null,
        ],
    ];
    if ($event === 'copy_text') {
        // Assignment::recordTaskProgress locks and validates this pending row.
        $response['content'] = $updated['text_content'];
    }

    json_response($response);
}



function handle_create_excel_assignment_comment(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
    $commentText = trim((string) ($_POST['comment_text'] ?? ''));

    if ($assignmentId <= 0) {
        set_flash('error', 'Invalid Excel assignment.');
        redirect(APP_URL . '/index.php?page=user_excel_assignments');
    }

    if ($commentText === '' || mb_strlen($commentText) > 2000) {
        set_flash('error', 'Comment text is required and must be 2000 characters or fewer.');
        redirect(APP_URL . '/index.php?page=user_excel_assignment_view&id=' . $assignmentId);
    }

    $excelAssignmentModel = new ExcelAssignment($pdo);
    $assignment = is_admin()
        ? $excelAssignmentModel->findById($assignmentId)
        : $excelAssignmentModel->findByIdForUser($assignmentId, (int) current_user_id());

    if ($assignment === null) {
        set_flash('error', 'Excel assignment not found.');
        redirect(APP_URL . '/index.php?page=' . (is_admin() ? 'admin_excel_assignments' : 'user_excel_assignments'));
    }

    $commentModel = new ExcelAssignmentComment($pdo);
    $commentModel->create([
        'assignment_id' => $assignmentId,
        'user_id'       => current_user_id(),
        'comment_text'  => $commentText,
    ]);

    (new ActivityLog($pdo))->record(current_user_id(), 'excel_assignment_comment', "Added comment to Excel assignment #$assignmentId");
    set_flash('success', 'Your update was saved.');
    redirect(APP_URL . '/index.php?page=' . (is_admin() ? 'admin_excel_assignment_view' : 'user_excel_assignment_view') . '&id=' . $assignmentId);
}
