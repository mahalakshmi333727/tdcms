<?php
/**
 * Admin Controller
 * Handles all admin-only state-changing actions: user CRUD, assignment
 * creation (single + bulk), and CSV export. Every function assumes
 * middleware/require_login.php + middleware/require_admin.php already ran.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// USER MANAGEMENT
// ---------------------------------------------------------------------

function handle_create_user(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    $userModel = new User($pdo);

    $fullName = sanitize_input($_POST['full_name'] ?? '');
    $username = strtolower(sanitize_input($_POST['username'] ?? ''));
    $email    = sanitize_input($_POST['email'] ?? '');
    $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
    $password = (string) ($_POST['password'] ?? '');

    $errors = [];
    if ($fullName === '' || mb_strlen($fullName) > 120) $errors[] = 'Full name is required.';
    if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) $errors[] = 'Username must be 3-60 chars: letters, numbers, dot, underscore, hyphen.';
    if (!is_valid_email($email)) $errors[] = 'A valid email is required.';
    if (($strengthErr = validate_password_strength($password)) !== null) $errors[] = $strengthErr;
    if ($userModel->usernameExists($username)) $errors[] = 'That username is already taken.';
    if ($userModel->emailExists($email)) $errors[] = 'That email is already registered.';

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        redirect(APP_URL . '/index.php?page=admin_user_form');
    }

    $newId = $userModel->create([
        'full_name'     => $fullName,
        'username'      => $username,
        'email'         => $email,
        'password_hash' => hash_password($password),
        'role'          => $role,
        'status'        => 'active',
    ]);

    (new ActivityLog($pdo))->record(current_user_id(), 'create_user', "Created user #$newId ($username)");
    set_flash('success', 'User created successfully.');
    redirect(APP_URL . '/index.php?page=admin_users');
}

function handle_update_user(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    $userModel = new User($pdo);
    $id = (int) ($_POST['id'] ?? 0);
    $existing = $userModel->findById($id);

    if (!$existing) {
        set_flash('error', 'User not found.');
        redirect(APP_URL . '/index.php?page=admin_users');
    }

    $fullName = sanitize_input($_POST['full_name'] ?? '');
    $username = strtolower(sanitize_input($_POST['username'] ?? ''));
    $email    = sanitize_input($_POST['email'] ?? '');
    $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

    $errors = [];
    if ($fullName === '' || mb_strlen($fullName) > 120) $errors[] = 'Full name is required.';
    if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) $errors[] = 'Invalid username format.';
    if (!is_valid_email($email)) $errors[] = 'A valid email is required.';
    if ($userModel->usernameExists($username, $id)) $errors[] = 'That username is already taken.';
    if ($userModel->emailExists($email, $id)) $errors[] = 'That email is already registered.';

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        redirect(APP_URL . '/index.php?page=admin_user_form&id=' . $id);
    }

    $userModel->update($id, compact('full_name', 'username', 'email', 'role'));

    // Keep the current session in sync if the admin just edited their own account
    if ($id === current_user_id()) {
        $_SESSION['user_name'] = $full_name;
        $_SESSION['username']  = $username;
    }

    // Optional password reset by admin
    if (!empty($_POST['new_password'])) {
        $strengthErr = validate_password_strength((string) $_POST['new_password']);
        if ($strengthErr === null) {
            $userModel->updatePassword($id, hash_password((string) $_POST['new_password']));
        }
    }

    (new ActivityLog($pdo))->record(current_user_id(), 'update_user', "Updated user #$id ($username)");
    set_flash('success', 'User updated successfully.');
    redirect(APP_URL . '/index.php?page=admin_users');
}

function handle_toggle_user_status(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    $id = (int) ($_POST['id'] ?? 0);
    $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'disabled';

    $userModel = new User($pdo);
    $target = $userModel->findById($id);
    if (!$target) {
        json_response(['success' => false, 'message' => 'User not found.'], 404);
    }
    if ($target['role'] === 'admin') {
        json_response(['success' => false, 'message' => 'Administrator accounts cannot be disabled.'], 403);
    }

    $userModel->setStatus($id, $status);
    (new ActivityLog($pdo))->record(current_user_id(), 'toggle_status', "Set user #$id status to $status");
    json_response(['success' => true, 'status' => $status]);
}

function handle_delete_user(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    $id = (int) ($_POST['id'] ?? 0);
    $userModel = new User($pdo);
    $target = $userModel->findById($id);

    if (!$target) {
        json_response(['success' => false, 'message' => 'User not found.'], 404);
    }
    if ($target['role'] === 'admin') {
        json_response(['success' => false, 'message' => 'Administrator accounts cannot be deleted.'], 403);
    }

    $userModel->delete($id);
    (new ActivityLog($pdo))->record(current_user_id(), 'delete_user', "Deleted user #$id ({$target['username']})");
    json_response(['success' => true]);
}

// ---------------------------------------------------------------------
// ASSIGNMENT MANAGEMENT
// ---------------------------------------------------------------------

function handle_get_assignment(PDO $pdo): void
{
    require_method('GET');

    $assignmentIdInput = is_string($_GET['assignment_id'] ?? null) ? $_GET['assignment_id'] : null;
    $assignmentId = filter_var($assignmentIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($assignmentId === false || $assignmentId === null) {
        json_response(['success' => false, 'message' => 'Invalid assignment ID.'], 400);
    }

    $assignment = (new Assignment($pdo))->findAdminDetailsById((int) $assignmentId);
    if ($assignment === null) {
        json_response(['success' => false, 'message' => 'Assignment not found.'], 404);
    }

    json_response(['success' => true, 'assignment' => $assignment]);
}

function handle_update_assignment(PDO $pdo): void
{
    require_method('POST');
    csrf_protect();

    $assignmentIdInput = is_string($_POST['assignment_id'] ?? null) ? $_POST['assignment_id'] : null;
    $assignmentId = filter_var($assignmentIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($assignmentId === false || $assignmentId === null) {
        json_response(['success' => false, 'message' => 'Invalid assignment ID.'], 400);
    }

    $assignmentModel = new Assignment($pdo);
    $existing = $assignmentModel->findActiveById((int) $assignmentId);
    if ($existing === null) {
        json_response(['success' => false, 'message' => 'Assignment not found.'], 404);
    }

    foreach (['title', 'category', 'text_content', 'reference_link', 'tags'] as $field) {
        if (isset($_POST[$field]) && !is_string($_POST[$field])) {
            json_response(['success' => false, 'message' => 'Invalid assignment data.'], 422);
        }
    }

    $userIdInput = is_string($_POST['user_id'] ?? null) ? $_POST['user_id'] : null;
    $data = [
        'user_id' => filter_var($userIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0,
        'title' => sanitize_input($_POST['title'] ?? ''),
        'category' => sanitize_input($_POST['category'] ?? ''),
        'text_content' => sanitize_input($_POST['text_content'] ?? ''),
        'reference_link' => sanitize_input($_POST['reference_link'] ?? ''),
        'tags' => sanitize_input($_POST['tags'] ?? ''),
    ];

    $errors = [];
    if ($data['user_id'] <= 0) $errors[] = 'Please select a valid employee.';
    if (mb_strlen($data['title']) > 150) $errors[] = 'Assignment title must be 150 characters or fewer.';
    if (mb_strlen($data['category']) > 100) $errors[] = 'Category must be 100 characters or fewer.';
    if ($data['text_content'] === '' || mb_strlen($data['text_content']) > 20000) $errors[] = 'Description is required and must be 20,000 characters or fewer.';
    if (mb_strlen($data['reference_link']) > 500 || ($data['reference_link'] !== '' && !is_valid_url($data['reference_link']))) {
        $errors[] = 'Reference link must be a valid http(s) URL of 500 characters or fewer.';
    }
    if (mb_strlen($data['tags']) > 500) $errors[] = 'Tags must be 500 characters or fewer.';
    if ($data['user_id'] !== (int) $existing['user_id'] && $existing['status'] === 'completed') {
        $errors[] = 'A completed assignment cannot be reassigned because that would change its completion history.';
    }

    $user = $data['user_id'] > 0 ? (new User($pdo))->findById($data['user_id']) : null;
    $isCurrentDisabledUser = $user
        && $data['user_id'] === (int) $existing['user_id']
        && $user['role'] === 'user';
    if (!$user || $user['role'] !== 'user' || ($user['status'] !== 'active' && !$isCurrentDisabledUser)) {
        $errors[] = 'Selected employee is not available.';
    }
    if ($errors !== []) {
        json_response(['success' => false, 'message' => implode(' ', $errors)], 422);
    }

    try {
        $updated = $assignmentModel->updateAdmin((int) $assignmentId, $data);
    } catch (DomainException $error) {
        json_response(['success' => false, 'message' => $error->getMessage()], 409);
    }
    if ($updated === null) {
        json_response(['success' => false, 'message' => 'Assignment was changed or removed. Refresh the list and try again.'], 409);
    }

    (new ActivityLog($pdo))->record(current_user_id(), 'update_assignment', 'Updated assignment #' . $assignmentId);
    json_response(['success' => true, 'message' => 'Assignment updated successfully.']);
}

function handle_delete_assignment(PDO $pdo): void
{
    require_method('POST');
    csrf_protect();

    $assignmentIdInput = is_string($_POST['assignment_id'] ?? null) ? $_POST['assignment_id'] : null;
    $assignmentId = filter_var($assignmentIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($assignmentId === false || $assignmentId === null) {
        json_response(['success' => false, 'message' => 'Invalid assignment ID.'], 400);
    }

    if (!(new Assignment($pdo))->archiveAdmin((int) $assignmentId)) {
        json_response(['success' => false, 'message' => 'Assignment not found or already deleted. Refresh the list and try again.'], 404);
    }

    (new ActivityLog($pdo))->record(current_user_id(), 'archive_assignment', 'Archived assignment #' . $assignmentId);
    json_response(['success' => true, 'message' => 'Assignment deleted successfully.']);
}

function handle_create_assignment(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    $requestId = $_POST['assignment_request_id'] ?? null;
    if (!is_string($requestId) || !preg_match('/\A[a-f0-9]{64}\z/', $requestId)) {
        set_flash('error', 'Invalid assignment request. Refresh the page and try again.');
        redirect(APP_URL . '/index.php?page=admin_assign_form');
    }
    if (assignment_request_was_processed($requestId)) {
        set_flash('info', 'This assignment request has already been processed.');
        redirect(APP_URL . '/index.php?page=admin_assignments');
    }

    $userId   = (int) ($_POST['user_id'] ?? 0);
    $title    = sanitize_input($_POST['title'] ?? '');
    $category = sanitize_input($_POST['category'] ?? '');
    $text     = trim((string) ($_POST['text_content'] ?? ''));
    $link     = sanitize_input($_POST['reference_link'] ?? '');
    $tags     = sanitize_input($_POST['tags'] ?? '');

    $errors = [];
    if ($userId <= 0) $errors[] = 'Please select a valid user.';
    if ($text === '' || mb_strlen($text) > 20000) $errors[] = 'Text content is required (max 20,000 characters).';
    if ($link !== '' && !is_valid_url($link)) $errors[] = 'Reference link must be a valid http(s) URL.';

    $userModel = new User($pdo);
    if ($userId > 0 && !$userModel->findById($userId)) {
        $errors[] = 'Selected user does not exist.';
    }

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        redirect(APP_URL . '/index.php?page=admin_assign_form');
    }

    $assignmentModel = new Assignment($pdo);
    $result = $assignmentModel->create([
        'user_id'        => $userId,
        'title'          => $title,
        'category'       => $category,
        'text_content'   => $text,
        'reference_link' => $link,
        'tags'           => $tags,
        'assigned_by'    => current_user_id(),
    ]);

    mark_assignment_request_processed($requestId);
    (new ActivityLog($pdo))->record(current_user_id(), 'assign_text', "Assigned item #{$result['id']} to user #$userId");
    set_flash('success', 'Text assigned successfully.');
    redirect(APP_URL . '/index.php?page=admin_assignments');
}

function assignment_request_was_processed(string $requestId): bool
{
    return !empty($_SESSION['processed_assignment_requests'][$requestId]);
}

function mark_assignment_request_processed(string $requestId): void
{
    // Request tokens prevent reposts without blocking intentional identical assignments.
    $processed = $_SESSION['processed_assignment_requests'] ?? [];
    $processed[$requestId] = true;
    $_SESSION['processed_assignment_requests'] = array_slice($processed, -32, null, true);
}

function handle_bulk_assign(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    $requestId = $_POST['assignment_request_id'] ?? null;
    if (!is_string($requestId) || !preg_match('/\A[a-f0-9]{64}\z/', $requestId)) {
        set_flash('error', 'Invalid assignment request. Refresh the page and try again.');
        redirect(APP_URL . '/index.php?page=admin_bulk_assign');
    }
    if (assignment_request_was_processed($requestId)) {
        set_flash('info', 'This assignment request has already been processed.');
        redirect(APP_URL . '/index.php?page=admin_assignments');
    }

    $rawUserIds = $_POST['user_ids'] ?? [];
    $userIds = is_array($rawUserIds) ? array_map('intval', $rawUserIds) : [];
    $userIds = array_values(array_unique(array_filter($userIds, fn($v) => $v > 0)));

    if (empty($userIds)) {
        set_flash('error', 'Please select at least one user.');
        redirect(APP_URL . '/index.php?page=admin_bulk_assign');
    }

    // Validate the selected users actually exist and are real "user" accounts,
    // rather than trusting whatever IDs came back in the POST body.
    $userModel = new User($pdo);
    $validUserIds = [];
    foreach ($userIds as $uid) {
        $u = $userModel->findById($uid);
        if ($u && $u['role'] === 'user') {
            $validUserIds[] = $uid;
        }
    }
    if (empty($validUserIds)) {
        set_flash('error', 'None of the selected users are valid.');
        redirect(APP_URL . '/index.php?page=admin_bulk_assign');
    }

    $hasFile = !empty($_FILES['import_file']['name']);

    if ($hasFile) {
        handle_bulk_assign_from_file($pdo, $validUserIds, $requestId);
        return;
    }

    handle_bulk_assign_manual($pdo, $validUserIds, $requestId);
}

/**
 * Manual path: one title/text/link/category/tags form assigned to every
 * selected user (the original bulk-assign behavior).
 */
function handle_bulk_assign_manual(PDO $pdo, array $userIds, string $requestId): void
{
    $title    = sanitize_input($_POST['title'] ?? '');
    $category = sanitize_input($_POST['category'] ?? '');
    $text     = trim((string) ($_POST['text_content'] ?? ''));
    $link     = sanitize_input($_POST['reference_link'] ?? '');
    $tags     = sanitize_input($_POST['tags'] ?? '');

    $errors = [];
    if ($text === '' || mb_strlen($text) > 20000) $errors[] = 'Text content is required (max 20,000 characters).';
    if ($link !== '' && !is_valid_url($link)) $errors[] = 'Reference link must be a valid http(s) URL.';

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        redirect(APP_URL . '/index.php?page=admin_bulk_assign');
    }

    $assignmentModel = new Assignment($pdo);
    $result = $assignmentModel->bulkCreate($userIds, $title, $text, $link, (int) current_user_id(), $category, $tags);
    $assigned = $result['assigned'];

    mark_assignment_request_processed($requestId);
    (new ActivityLog($pdo))->record(
        current_user_id(),
        'bulk_assign',
        "Bulk-assigned text to $assigned user(s)"
    );

    set_flash('success', "Text assigned to $assigned user(s) successfully.");
    redirect(APP_URL . '/index.php?page=admin_assignments');
}

/**
 * File-import path: a JSON or .xlsx file describing MULTIPLE tasks, each
 * one assigned to every selected user. Every task/user pair creates a
 * separate assignment, including repeated task content.
 */
function handle_bulk_assign_from_file(PDO $pdo, array $userIds, string $requestId): void
{
    try {
        $parsed = parse_uploaded_task_file($_FILES['import_file']);
    } catch (RuntimeException $e) {
        set_flash('error', $e->getMessage());
        redirect(APP_URL . '/index.php?page=admin_bulk_assign');
    }

    $validTasks = $parsed['valid'];
    $rowErrors  = $parsed['errors'];

    if (empty($validTasks)) {
        set_flash('error', 'No valid task rows were found in the file. ' . implode(' ', array_slice($rowErrors, 0, 5)));
        redirect(APP_URL . '/index.php?page=admin_bulk_assign');
    }

    $assignmentModel = new Assignment($pdo);
    $result = $assignmentModel->bulkCreateFromTasks($userIds, $validTasks, (int) current_user_id());

    if ($result['assigned'] > 0) {
        mark_assignment_request_processed($requestId);
    }
    (new ActivityLog($pdo))->record(
        current_user_id(),
        'bulk_assign',
        "Imported {$result['tasks']} task(s) from file, assigned to {$result['users']} user(s): "
            . "{$result['assigned']} assignment(s) created"
            . (!empty($rowErrors) ? ', ' . count($rowErrors) . ' row(s) rejected' : '')
    );

    $message = "Imported {$result['tasks']} task(s) for {$result['users']} user(s): "
        . "{$result['assigned']} assignment(s) created";
    $message .= '.';

    if (!empty($rowErrors)) {
        $message .= ' ' . count($rowErrors) . ' row(s) in the file were skipped due to errors: '
            . implode(' | ', array_slice($rowErrors, 0, 5))
            . (count($rowErrors) > 5 ? ' …and more.' : '');
        set_flash('info', $message);
    } else {
        set_flash('success', $message);
    }

    redirect(APP_URL . '/index.php?page=admin_assignments');
}

// ---------------------------------------------------------------------
// EXPORT
// ---------------------------------------------------------------------

function handle_export_csv(PDO $pdo): void
{
    require_method('GET');
    // CSRF is not applicable to safe GET exports, but we still require an
    // authenticated admin session (already enforced by middleware).

    $filters = [
        'status'  => $_GET['status'] ?? '',
        'user_id' => $_GET['user_id'] ?? '',
    ];

    $assignmentModel = new Assignment($pdo);
    $rows = $assignmentModel->exportAll($filters);

    (new ActivityLog($pdo))->record(current_user_id(), 'export_csv', 'Exported assignments CSV');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="assignments_export_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'User Name', 'Username', 'Title', 'Category', 'Text Content', 'Reference Link', 'Tags', 'Status', 'Assigned At', 'Completed At'] /* PHP 8.4 COMPAT START */, ',', '"', '\\'/* PHP 8.4 COMPAT END */);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            csv_safe_cell($r['full_name']),
            csv_safe_cell($r['username']),
            csv_safe_cell($r['title']),
            csv_safe_cell($r['category']),
            csv_safe_cell($r['text_content']),
            csv_safe_cell($r['reference_link']),
            csv_safe_cell($r['tags']),
            $r['status'], $r['assigned_at'], $r['completed_at'],
        ] /* PHP 8.4 COMPAT START */, ',', '"', '\\'/* PHP 8.4 COMPAT END */);
    }
    fclose($out);
    exit;
}

/**
 * Prevent CSV/formula injection: if a cell starts with a character that
 * spreadsheet software (Excel, Google Sheets, LibreOffice) treats as the
 * start of a formula, prefix it with a leading apostrophe so it is opened
 * as plain text instead of being evaluated.
 */
function csv_safe_cell(?string $value): string
{
    $value = $value ?? '';
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
        return "'" . $value;
    }
    return $value;
}
