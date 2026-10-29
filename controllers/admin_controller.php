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

    if (array_key_exists('new_password', $_POST)) {
        set_flash('error', 'Use the administrator password field to set a password.');
        redirect(APP_URL . '/index.php?page=admin_user_form');
    }

    $userModel = new User($pdo);

    foreach (['full_name', 'username', 'email', 'role', 'admin_password'] as $field) {
        if (isset($_POST[$field]) && !is_string($_POST[$field])) {
            set_flash('error', 'Enter valid user details.');
            redirect(APP_URL . '/index.php?page=admin_user_form');
        }
    }

    $fullName = sanitize_input($_POST['full_name'] ?? '');
    $username = strtolower(sanitize_input($_POST['username'] ?? ''));
    $email    = sanitize_input($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? 'user';
    $adminPassword = $_POST['admin_password'] ?? '';

    $errors = [];
    if ($fullName === '' || mb_strlen($fullName) > 120) $errors[] = 'Full name is required.';
    if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) $errors[] = 'Username must be 3-60 chars: letters, numbers, dot, underscore, hyphen.';
    if (!is_valid_email($email)) $errors[] = 'A valid email is required.';
    if (!in_array($role, ['admin', 'user'], true)) $errors[] = 'Select a valid role.';
    if ($role === 'admin' && validate_password_strength($adminPassword) !== null) {
        $errors[] = 'A valid password of at least ' . PASSWORD_MIN_LENGTH . ' characters is required for an administrator.';
    }
    if ($role === 'user' && $adminPassword !== '') {
        $errors[] = 'A password can only be set for an administrator account.';
    }
    if ($userModel->usernameExists($username)) $errors[] = 'That username is already taken.';
    if ($userModel->emailExists($email)) $errors[] = 'That email is already registered.';

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        redirect(APP_URL . '/index.php?page=admin_user_form');
    }

    try {
        $newId = $userModel->create([
            'full_name'     => $fullName,
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $role === 'admin' ? hash_password($adminPassword) : null,
            'role'          => $role,
            'status'        => 'active',
        ]);
    } catch (PDOException $error) {
        error_log('User creation failed: ' . $error->getMessage());
        set_flash('error', 'User could not be created. Check for an existing username or email and try again.');
        redirect(APP_URL . '/index.php?page=admin_user_form');
    }

    (new ActivityLog($pdo))->record(current_user_id(), 'create_user', "Created user #$newId ($username)");
    set_flash('success', 'User created successfully.');
    redirect(APP_URL . '/index.php?page=admin_users');
}

function handle_update_user(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    $userModel = new User($pdo);
    $idInput = $_POST['id'] ?? null;
    $id = is_string($idInput)
        ? filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    if ($id === false) {
        set_flash('error', 'Invalid user.');
        redirect(APP_URL . '/index.php?page=admin_users');
    }
    $existing = $userModel->findById($id);

    if (!$existing) {
        set_flash('error', 'User not found.');
        redirect(APP_URL . '/index.php?page=admin_users');
    }

    foreach (['full_name', 'username', 'email', 'role', 'admin_password'] as $field) {
        if (isset($_POST[$field]) && !is_string($_POST[$field])) {
            set_flash('error', 'Enter valid user details.');
            redirect(APP_URL . '/index.php?page=admin_user_form&id=' . $id);
        }
    }
    if (array_key_exists('new_password', $_POST)) {
        set_flash('error', 'Use the administrator password field to update a password.');
        redirect(APP_URL . '/index.php?page=admin_user_form&id=' . $id);
    }

    $fullName = sanitize_input($_POST['full_name'] ?? '');
    $username = strtolower(sanitize_input($_POST['username'] ?? ''));
    $email    = sanitize_input($_POST['email'] ?? '');
    $role     = $_POST['role'] ?? '';
    $adminPassword = $_POST['admin_password'] ?? '';

    $errors = [];
    if ($fullName === '' || mb_strlen($fullName) > 120) $errors[] = 'Full name is required.';
    if (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) $errors[] = 'Invalid username format.';
    if (!is_valid_email($email)) $errors[] = 'A valid email is required.';
    if (!in_array($role, ['admin', 'user'], true)) $errors[] = 'Select a valid role.';
    if ($role === 'admin') {
        if ($adminPassword !== '') {
            if (validate_password_strength($adminPassword) !== null) {
                $errors[] = 'Administrator passwords must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
            }
        } elseif ($existing['role'] !== 'admin' || empty($existing['password_hash'])) {
            $errors[] = 'A password is required when creating an administrator account.';
        }
    } elseif ($adminPassword !== '') {
        $errors[] = 'A password can only be set for an administrator account.';
    }
    if ($userModel->usernameExists($username, $id)) $errors[] = 'That username is already taken.';
    if ($userModel->emailExists($email, $id)) $errors[] = 'That email is already registered.';

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        redirect(APP_URL . '/index.php?page=admin_user_form&id=' . $id);
    }

    try {
        $pdo->beginTransaction();
        $updated = $userModel->update($id, [
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'role' => $role,
        ]);
        if (!$updated || ($role === 'admin' && $adminPassword !== ''
            && !$userModel->updatePassword($id, hash_password($adminPassword)))) {
            $pdo->rollBack();
            set_flash('error', 'User changes could not be saved.');
            redirect(APP_URL . '/index.php?page=admin_user_form&id=' . $id);
        }
        $pdo->commit();
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('User update transaction failed: ' . $error->getMessage());
        set_flash('error', 'User changes could not be saved.');
        redirect(APP_URL . '/index.php?page=admin_user_form&id=' . $id);
    }
    // Keep the current session in sync if the admin just edited their own account
    if ($id === current_user_id()) {
        $_SESSION['user_name'] = $fullName;
        $_SESSION['username']  = $username;
        $_SESSION['user_role'] = $role;
    }

    (new ActivityLog($pdo))->record(current_user_id(), 'update_user', "Updated user #$id ($username)");
    set_flash('success', 'User updated successfully.');
    redirect(APP_URL . '/index.php?page=admin_users');
}

function handle_toggle_user_status(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    $idInput = $_POST['id'] ?? null;
    $id = is_string($idInput)
        ? filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    $status = $_POST['status'] ?? null;
    if ($id === false || !is_string($status) || !in_array($status, ['active', 'disabled'], true)) {
        json_response(['success' => false, 'message' => 'Invalid user status request.'], 422);
    }

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

    $idInput = $_POST['id'] ?? null;
    $id = is_string($idInput)
        ? filter_var($idInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    if ($id === false) {
        json_response(['success' => false, 'message' => 'Invalid user.'], 422);
    }
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

function handle_get_user_assignment_content(PDO $pdo): void
{
    require_method('GET');

    $userIdInput = is_string($_GET['user_id'] ?? null) ? $_GET['user_id'] : null;
    $userId = filter_var($userIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
    $pageInput = is_string($_GET['p'] ?? null) ? $_GET['p'] : '1';
    $page = filter_var($pageInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($userId === false || $userId === null || $page === false || !in_array($status, ['', 'pending', 'completed'], true)) {
        json_response(['success' => false, 'message' => 'Invalid assignment content filter.'], 400);
    }

    $user = (new User($pdo))->findById((int) $userId);
    if (!$user || $user['role'] !== 'user') {
        json_response(['success' => false, 'message' => 'User not found.'], 404);
    }

    $result = (new Assignment($pdo))->contentForAdminUser((int) $userId, $status, (int) $page);
    json_response([
        'success' => true,
        'rows' => $result['rows'],
        'total' => $result['total'],
        'page' => $result['page'],
        'pages' => $result['pages'],
    ]);
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

function handle_mark_notification_read(PDO $pdo): void
{
    require_method('POST');
    csrf_protect();

    $notificationIdInput = is_string($_POST['notification_id'] ?? null)
        ? $_POST['notification_id']
        : null;
    $notificationId = filter_var(
        $notificationIdInput,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($notificationId === false || $notificationId === null) {
        set_flash('error', 'Invalid notification.');
        redirect(APP_URL . '/index.php?page=admin_notifications');
    }

    $notificationModel = new AdminNotification($pdo);
    if (!$notificationModel->markRead(
        (int) $notificationId,
        new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'))
    )) {
        set_flash('error', 'Notification not found.');
    }

    redirect(APP_URL . '/index.php?page=admin_notifications');
}

function excel_assignment_form_data(PDO $pdo, bool $requireTitle, ?int $allowedExistingUserId = null): array
{
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $rawTitle = $_POST['title'] ?? null;
    if ($rawTitle !== null && !is_string($rawTitle)) {
        throw new DomainException('Assignment title is invalid.');
    }
    $title = sanitize_input(is_string($rawTitle) ? $rawTitle : null);

    if ($userId === false) {
        throw new DomainException('Please select a valid user.');
    }
    if (($requireTitle && $title === '') || mb_strlen($title) > 150) {
        throw new DomainException('Assignment title is required and must be 150 characters or fewer.');
    }
    $user = (new User($pdo))->findById((int) $userId);
    if (
        $user === null
        || $user['role'] !== 'user'
        || ($user['status'] !== 'active' && (int) $userId !== $allowedExistingUserId)
    ) {
        throw new DomainException('Selected user is not available.');
    }

    return [
        'user_id' => (int) $userId,
        'title' => $title,
    ];
}

function excel_assignment_upload_from_request(array $uploads, int $index): array
{
    $file = [];
    foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
        $value = $uploads[$key] ?? null;
        $file[$key] = is_array($value) ? ($value[$index] ?? null) : $value;
    }
    return $file;
}

function handle_create_excel_assignment(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    try {
        $data = excel_assignment_form_data($pdo, false);
    } catch (DomainException $error) {
        set_flash('error', $error->getMessage());
        redirect(APP_URL . '/index.php?page=admin_assign_excel');
    }

    $uploadedFiles = $_FILES['excel_files'] ?? $_FILES['excel_file'] ?? null;
    if (
        !is_array($uploadedFiles)
        || !isset($uploadedFiles['name'])
        || (!is_string($uploadedFiles['name']) && !is_array($uploadedFiles['name']))
    ) {
        set_flash('error', 'Please select an Excel file.');
        redirect(APP_URL . '/index.php?page=admin_assign_excel');
    }

    $fileNames = is_array($uploadedFiles['name']) ? $uploadedFiles['name'] : [$uploadedFiles['name']];
    $fileCount = count($fileNames);
    if (
        $fileCount < 1
        || $fileCount > 100
        || count(array_filter($fileNames, 'is_string')) !== $fileCount
    ) {
        set_flash('error', 'Please select between 1 and 100 Excel files.');
        redirect(APP_URL . '/index.php?page=admin_assign_excel');
    }

    $excelAssignmentModel = new ExcelAssignment($pdo);
    $storedPaths = [];

    try {
        $pdo->beginTransaction();
        for ($index = 0; $index < $fileCount; $index++) {
            $file = excel_assignment_upload_from_request($uploadedFiles, $index);
            $uploadResult = save_uploaded_file($file, 'excel_assignments', ['xlsx', 'xls']);
            $storedPaths[] = APP_ROOT . '/uploads/' . $uploadResult['stored_path'];

            $fileTitle = $data['title'] !== ''
                ? $data['title']
                : sanitize_input(pathinfo($uploadResult['original_name'], PATHINFO_FILENAME));
            $excelAssignmentModel->create(array_merge($data, [
                'title' => mb_substr($fileTitle, 0, 150),
                'original_file_name' => $uploadResult['original_name'],
                'stored_file_path' => $uploadResult['stored_path'],
                'file_type' => $uploadResult['file_type'],
                'assigned_at' => date('Y-m-d H:i:s'),
                'created_by' => current_user_id(),
            ]));
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($storedPaths as $storedPath) {
            if (is_file($storedPath) && !unlink($storedPath)) {
                error_log('Failed to remove Excel assignment upload after save failure: ' . $storedPath);
            }
        }
        if (!$error instanceof RuntimeException) {
            error_log('Excel assignment save failed: ' . $error->getMessage());
        }
        set_flash('error', $error instanceof RuntimeException
            ? $error->getMessage()
            : 'Excel assignments could not be saved. Please try again.');
        redirect(APP_URL . '/index.php?page=admin_assign_excel');
    }

    (new ActivityLog($pdo))->record(current_user_id(), 'assign_excel', "Assigned $fileCount Excel file(s) to user #" . $data['user_id']);
    set_flash('success', "$fileCount Excel file(s) uploaded and assigned successfully.");
    redirect(APP_URL . '/index.php?page=admin_excel_assignments');
}

function handle_update_excel_assignment(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($id === false) {
        set_flash('error', 'Invalid Excel assignment.');
        redirect(APP_URL . '/index.php?page=admin_excel_assignments');
    }
    $id = (int) $id;

    $excelAssignmentModel = new ExcelAssignment($pdo);
    $assignment = $excelAssignmentModel->findById($id);
    if ($assignment === null) {
        set_flash('error', 'Excel assignment not found.');
        redirect(APP_URL . '/index.php?page=admin_excel_assignments');
    }

    try {
        $data = excel_assignment_form_data($pdo, true, (int) $assignment['user_id']);
    } catch (DomainException $error) {
        set_flash('error', $error->getMessage());
        redirect(APP_URL . '/index.php?page=admin_excel_assignment_edit&id=' . $id);
    }

    try {
        $excelAssignmentModel->update($id, $data);
    } catch (Throwable $error) {
        if (!$error instanceof RuntimeException && !$error instanceof DomainException) {
            error_log('Excel assignment update failed: ' . $error->getMessage());
        }
        set_flash('error', $error instanceof RuntimeException || $error instanceof DomainException
            ? $error->getMessage()
            : 'Excel assignment could not be updated. Please try again.');
        redirect(APP_URL . '/index.php?page=admin_excel_assignment_edit&id=' . $id);
    }

    (new ActivityLog($pdo))->record(current_user_id(), 'update_excel_assignment', "Updated Excel assignment #$id");
    set_flash('success', 'Excel assignment updated successfully.');
    redirect(APP_URL . '/index.php?page=admin_excel_assignment_view&id=' . $id);
}

function handle_delete_excel_assignment(PDO $pdo): void
{
    csrf_protect();
    require_method('POST');

    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        set_flash('error', 'Invalid Excel assignment.');
        redirect(APP_URL . '/index.php?page=admin_excel_assignments');
    }

    $excelAssignmentModel = new ExcelAssignment($pdo);
    $assignment = $excelAssignmentModel->findById($id);
    if ($assignment === null) {
        set_flash('error', 'Excel assignment not found.');
        redirect(APP_URL . '/index.php?page=admin_excel_assignments');
    }

    $filePath = APP_ROOT . '/uploads/' . ltrim((string) $assignment['stored_file_path'], '/\\');
    if (is_file($filePath)) {
        @unlink($filePath);
    }

    $excelAssignmentModel->delete($id);

    (new ActivityLog($pdo))->record(current_user_id(), 'delete_excel_assignment', "Deleted Excel assignment #$id");
    set_flash('success', 'Excel assignment deleted successfully.');
    redirect(APP_URL . '/index.php?page=admin_excel_assignments');
}

function bulk_assignment_request_was_processed(string $requestId): bool
{
    return !empty($_SESSION['processed_bulk_assignment_requests'][$requestId]);
}

function mark_bulk_assignment_request_processed(string $requestId): void
{
    // Guard request retries by token, never by comparing intentional task content.
    $processed = $_SESSION['processed_bulk_assignment_requests'] ?? [];
    $processed[$requestId] = true;
    $_SESSION['processed_bulk_assignment_requests'] = array_slice($processed, -32, null, true);
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
    if (bulk_assignment_request_was_processed($requestId)) {
        set_flash('info', 'This assignment request has already been processed.');
        redirect(APP_URL . '/index.php?page=admin_assignments');
    }

    $businessId = null;
    $rawBusinessId = $_POST['business_id'] ?? '';
    if ($rawBusinessId !== '' && !is_string($rawBusinessId) && !is_int($rawBusinessId)) {
        set_flash('error', 'Select a valid business.');
        redirect(APP_URL . '/index.php?page=admin_bulk_assign');
    }
    if ($rawBusinessId !== '') {
        $businessId = filter_var(
            $rawBusinessId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($businessId === false) {
            set_flash('error', 'Select a valid business.');
            redirect(APP_URL . '/index.php?page=admin_bulk_assign');
        }
    }

    $rawUserIds = $_POST['user_ids'] ?? null;
    if (!is_array($rawUserIds)) {
        $rawUserIds = isset($_POST['user_id']) ? [$_POST['user_id']] : [];
    }
    if (count($rawUserIds) > 1) {
        set_flash('error', 'Bulk import supports one selected user at a time.');
        redirect(APP_URL . '/index.php?page=admin_bulk_assign');
    }
    $userIds = array_values(array_unique(array_filter(array_map('intval', $rawUserIds), static fn($id) => $id > 0)));

    if (empty($userIds)) {
        set_flash('error', 'Please select at least one user.');
        redirect(APP_URL . '/index.php?page=admin_bulk_assign');
    }

    // Validate the selected user actually exists and is a real "user"
    // account, rather than trusting whatever ID came back in the POST body.
    $userModel = new User($pdo);
    $validUserIds = [];
    foreach ($userIds as $userId) {
        $user = $userModel->findById($userId);
        if ($user && $user['role'] === 'user' && $user['status'] === 'active') {
            $validUserIds[] = $userId;
        }
    }
    if (empty($validUserIds)) {
        set_flash('error', 'None of the selected users are valid.');
        redirect(APP_URL . '/index.php?page=admin_bulk_assign');
    }

    if (!empty($_FILES['import_files']['name'])) {
        $commonReferenceLink = sanitize_input($_POST['common_reference_link'] ?? '');
        if ($commonReferenceLink !== '' && !is_valid_url($commonReferenceLink)) {
            set_flash('error', 'Common Reference Link must be a valid http(s) URL.');
            redirect(APP_URL . '/index.php?page=admin_bulk_assign');
        }
        handle_bulk_assign_from_files(
            $pdo,
            $validUserIds,
            $requestId,
            $commonReferenceLink !== '' ? $commonReferenceLink : null,
            $businessId === null ? null : (int) $businessId
        );
        return;
    }

    handle_bulk_assign_manual($pdo, $validUserIds, $requestId, $businessId === null ? null : (int) $businessId);
}
function handle_bulk_assign_manual(PDO $pdo, array $userIds, string $requestId, ?int $businessId = null): void
{
    $title    = sanitize_input($_POST['title'] ?? '');
    $category = sanitize_input($_POST['category'] ?? '');
    $text     = trim((string) ($_POST['text_content'] ?? ''));
    $link     = sanitize_input($_POST['reference_link'] ?? '');
    $tags     = sanitize_input($_POST['tags'] ?? '');

    $errors = [];
    if ($text === '' || mb_strlen($text) > 20000) $errors[] = 'Text content is required (max 20,000 characters).';
    if ($link !== '' && !is_valid_url($link)) $errors[] = 'Reference link must be a valid http(s) URL.';

    if ($businessId !== null) {
        $titleLength = preg_match_all('/./us', $title);
        if ($title === '' || $titleLength === false || $titleLength > 150) {
            $errors[] = 'Business-linked tasks require a title between 1 and 150 characters.';
        }
    }

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        $returnTo = APP_URL . '/index.php?page=admin_bulk_assign'
            . ($businessId !== null ? '&business_id=' . $businessId : '');
        redirect($returnTo);
    }

    if ($businessId !== null) {
        try {
            $assigned = (new Business($pdo))->assignBulkTasks(
                $businessId,
                $userIds[0],
                (int) current_user_id(),
                [[
                    'title' => $title,
                    'text_content' => $text,
                    'reference_link' => $link,
                    'tags' => $tags,
                ]]
            );
            mark_bulk_assignment_request_processed($requestId);
            (new ActivityLog($pdo))->record(
                current_user_id(),
                'bulk_assign',
                "Bulk-assigned $assigned business content item(s) to user #{$userIds[0]} for business #$businessId"
            );
            set_flash('success', "Business content assigned to {$assigned} user(s).");
        } catch (DomainException $error) {
            set_flash('error', $error->getMessage());
            redirect(APP_URL . '/index.php?page=admin_bulk_assign');
        } catch (PDOException $error) {
            error_log('Business-linked manual assignment failed: ' . $error->getMessage());
            set_flash('error', 'The business-linked assignment could not be saved. Review the server log.');
            redirect(APP_URL . '/index.php?page=admin_bulk_assign');
        }
        redirect(APP_URL . '/index.php?page=admin_assignments');
    }

    $assignmentModel = new Assignment($pdo);
    $result = $assignmentModel->bulkCreate($userIds, $title, $text, $link, (int) current_user_id(), $category, $tags);
    $assigned = $result['assigned'];
    mark_bulk_assignment_request_processed($requestId);

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
 * one assigned to every selected user. Each imported row creates a new
 * assignment, even when its content matches another assignment.
 */
// ---------------------------------------------------------------------
// EXPORT
// ---------------------------------------------------------------------

function handle_bulk_assign_from_files(
    PDO $pdo,
    array $userIds,
    string $requestId,
    ?string $commonReferenceLink = null,
    ?int $businessId = null
): void
{
    $uploaded = $_FILES['import_files'] ?? null;
    $names = is_array($uploaded['name'] ?? null) ? $uploaded['name'] : [];
    $fileCount = count($names);

    if ($fileCount < 1 || $fileCount > 100) {
        set_flash('error', 'Please select between 1 and 100 import files.');
        redirect(APP_URL . '/index.php?page=admin_bulk_assign');
    }

    $assignmentModel = new Assignment($pdo);
    $totalTasks = 0;
    $totalAssigned = 0;
    $successfulFiles = 0;
    $failedFiles = 0;
    $reports = [];

    for ($index = 0; $index < $fileCount; $index++) {
        $file = [
            'name' => $uploaded['name'][$index],
            'type' => $uploaded['type'][$index],
            'tmp_name' => $uploaded['tmp_name'][$index],
            'error' => $uploaded['error'][$index],
            'size' => $uploaded['size'][$index],
        ];
        $fileName = (string) $file['name'];

        try {
            $parsed = parse_uploaded_task_file($file, $businessId !== null);
            if ($commonReferenceLink !== null) {
                foreach ($parsed['valid'] as &$task) {
                    $task['reference_link'] = $commonReferenceLink;
                }
                unset($task);
            }
            if ($businessId === null) {
                $result = $assignmentModel->bulkCreateFromTasks($userIds, $parsed['valid'], (int) current_user_id());
                $assigned = $result['assigned'];
            } else {
                $assigned = (new Business($pdo))->assignBulkTasks(
                    $businessId,
                    $userIds[0],
                    (int) current_user_id(),
                    $parsed['valid']
                );
            }
            $totalTasks += count($parsed['valid']);
            $totalAssigned += $assigned;
            if ($assigned > 0) {
                mark_bulk_assignment_request_processed($requestId);
            }
            $successfulFiles++;
            $report = "$fileName: " . count($parsed['valid']) . ' task(s) imported';
            if (!empty($parsed['errors'])) {
                $report .= '; ' . count($parsed['errors']) . ' invalid row(s): ' . implode(' | ', array_slice($parsed['errors'], 0, 5));
            }
            $reports[] = $report;
        } catch (DomainException $e) {
            $failedFiles++;
            $reports[] = "$fileName: " . $e->getMessage();
        } catch (PDOException $e) {
            error_log('Bulk assignment save failed for ' . $fileName . ': ' . $e->getMessage());
            $failedFiles++;
            $reports[] = "$fileName: database save failed.";
        } catch (Throwable $e) {
            $failedFiles++;
            $reports[] = "$fileName: file error: " . $e->getMessage();
        }
    }

    (new ActivityLog($pdo))->record(current_user_id(), 'bulk_assign', "Imported $totalTasks task(s) from $fileCount file(s) for one user");
    $message = "Total Files Selected: $fileCount. Successfully Processed: $successfulFiles. Failed Files: $failedFiles. Total Tasks Created: $totalAssigned."
        . ' ' . implode(' ', $reports);
    $flashType = $totalAssigned > 0 ? 'success' : ($totalTasks > 0 ? 'info' : 'error');
    set_flash($flashType, $message);
    redirect(APP_URL . '/index.php?page=admin_assignments');
}

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
