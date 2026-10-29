<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Business.php';
require_once __DIR__ . '/../helpers/business_xml_import.php';
require_once __DIR__ . '/../helpers/business_excel_import.php';

function business_post(): void
{
    csrf_protect();
    require_method('POST');
}

function business_admin_id(): int
{
    return (int) current_user_id();
}

function business_form_data(): array
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $referenceLink = trim((string) ($_POST['reference_link'] ?? ''));
    $dailyLimit = filter_var(
        $_POST['daily_assignment_limit'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
    );

    $nameLength = preg_match_all('/./us', $name);
    if ($name === '' || $nameLength === false || $nameLength > 150) {
        throw new DomainException('Business name is required and must be no more than 150 characters.');
    }
    if ($dailyLimit === false) {
        throw new DomainException('Daily content assignment limit must be a positive whole number.');
    }
    if ($referenceLink !== '' && !valid_business_http_url($referenceLink, 500)) {
        throw new DomainException('Reference link must be a valid HTTP or HTTPS URL no longer than 500 characters.');
    }
    return [
        'name' => $name,
        'reference_link' => $referenceLink === '' ? null : $referenceLink,
        'daily_assignment_limit' => (int) $dailyLimit,
    ];
}

function business_selected_user_ids(bool $isUpdate = false): array
{
    $rawUserIds = $_POST['user_ids'] ?? null;
    if ($isUpdate && is_array($rawUserIds)) {
        $raw = $rawUserIds;
    } else {
        $rawUserId = $_POST['user_id'] ?? null;
        if (!is_string($rawUserId) && !is_int($rawUserId)) {
            throw new DomainException('Select one active user.');
        }
        $raw = [$rawUserId];
    }
    if (count($raw) > 1) {
        throw new DomainException('A business can have only one assigned employee.');
    }
    $ids = [];
    foreach ($raw as $value) {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new DomainException('Select one valid active user.');
        }
        $ids[] = (int) $id;
    }
    return array_values(array_unique($ids));
}

function business_optional_excel_import(): ?array
{
    if (!isset($_FILES['excel_file']) || (int) ($_FILES['excel_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    return parse_business_excel_upload($_FILES['excel_file']);
}

function business_record_activity(PDO $pdo, string $action, string $description): void
{
    (new ActivityLog($pdo))->record(business_admin_id(), $action, $description);
}

function business_name_conflict(PDOException $error): bool
{
    $details = $error->errorInfo;
    return is_array($details)
        && ($details[0] ?? null) === '23000'
        && (int) ($details[1] ?? 0) === 1062
        && str_contains((string) ($details[2] ?? ''), 'uq_businesses_name');
}

function handle_save_business(PDO $pdo): void
{
    business_post();
    $rawBusinessId = $_POST['business_id'] ?? null;
    $businessId = $rawBusinessId === null
        ? null
        : filter_var($rawBusinessId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($businessId === false) {
        set_flash('error', 'Business not found.');
        redirect(APP_URL . '/index.php?page=admin_assignments');
    }
    $isUpdate = $businessId !== null;
    $back = $isUpdate
        ? APP_URL . '/index.php?page=admin_business_form&id=' . (int) $businessId
        : APP_URL . '/index.php?page=admin_business_form';

    try {
        $data = business_form_data();
        $businessModel = new Business($pdo);
        if ($businessModel->nameExists($data['name'], $isUpdate ? (int) $businessId : null)) {
            throw new DomainException('A business with this name already exists. Please choose a different name.');
        }
        $userIds = business_selected_user_ids($isUpdate);
        $import = business_optional_excel_import();
        $adminId = business_admin_id();
        if ($isUpdate) {
            $businessModel->update($businessId, $data, $userIds, business_admin_id());
            if ($import !== null) {
                $summary = $businessModel->import($businessId, $adminId, $import);
            } else {
                $summary = null;
            }
            business_record_activity(
                $pdo,
                'business_updated',
                'Updated business #' . $businessId
            );
            $destination = APP_URL . '/index.php?page=admin_business_details&id=' . $businessId;
            set_flash('success', business_success_message('Business updated.', $summary));
        } else {
            if ($userIds === []) {
                throw new DomainException('Select at least one active employee.');
            }
            $businessId = $businessModel->create($data, $userIds, $adminId, $import);
            business_record_activity(
                $pdo,
                'business_created',
                'Created business #' . $businessId
            );
            $afterSave = $_POST['after_save'] ?? '';
            $destination = match ($afterSave) {
                'bulk_assign' => APP_URL . '/index.php?page=admin_bulk_assign&business_id=' . (int) $businessId,
                'import_file' => APP_URL . '/index.php?page=admin_bulk_assign&business_id=' . (int) $businessId . '&tab=import',
                default => APP_URL . '/index.php?page=admin_assignments',
            };
            $summary = $import === null ? null : ['imported' => count($import['records']), 'errors' => $import['errors']];
            set_flash('success', business_success_message('Business created.', $summary));
        }
        redirect($destination);
    } catch (DomainException $error) {
        set_flash('error', $error->getMessage());
        redirect($back);
    } catch (PDOException $error) {
        error_log(
            'Business save failed [SQLSTATE ' . (string) $error->getCode()
            . ', driver code ' . (string) ($error->errorInfo[1] ?? 'unknown')
            . ']: ' . $error->getMessage()
        );
        set_flash(
            'error',
            business_name_conflict($error)
                ? 'A business with this name already exists. Please choose a different name.'
                : 'The business could not be saved because of a database error. Please try again later.'
        );
        redirect($back);
    }
}

function handle_delete_business(PDO $pdo): void
{
    business_post();
    $businessId = filter_var($_POST['business_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($businessId === false) {
        set_flash('error', 'Select a valid business to delete.');
        redirect(APP_URL . '/index.php?page=admin_assignments');
    }

    $destinationParams = ['page' => 'admin_assignments'];
    foreach (['search', 'date_from', 'date_to'] as $key) {
        if (is_string($_POST[$key] ?? null) && $_POST[$key] !== '') {
            $destinationParams[$key] = mb_substr($_POST[$key], 0, 100);
        }
    }
    foreach (['user_id', 'p'] as $key) {
        $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value !== false) {
            $destinationParams[$key] = $value;
        }
    }

    try {
        (new Business($pdo))->deleteIfEmpty((int) $businessId);
        set_flash('success', 'Business deleted successfully.');
    } catch (DomainException $error) {
        set_flash('error', $error->getMessage());
        redirect(APP_URL . '/index.php?' . http_build_query($destinationParams));
    } catch (Throwable $error) {
        error_log('Business deletion failed: ' . $error->getMessage());
        set_flash('error', 'The business could not be deleted. Existing records were preserved.');
        redirect(APP_URL . '/index.php?' . http_build_query($destinationParams));
    }

    try {
        business_record_activity($pdo, 'business_deleted', 'Deleted empty business #' . (int) $businessId);
    } catch (Throwable $error) {
        error_log('Business deletion audit logging failed: ' . $error->getMessage());
    }
    redirect(APP_URL . '/index.php?' . http_build_query($destinationParams));
}

function business_success_message(string $message, ?array $summary): string
{
    if ($summary === null) {
        return $message;
    }
    return $message . ' Excel import: ' . (int) $summary['imported'] . ' content item(s) imported, '
        . count($summary['errors']) . ' record(s) reported.';
}

function handle_import_business_excel(PDO $pdo): void
{
    business_post();
    $businessId = filter_var($_POST['business_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($businessId === false) {
        set_flash('error', 'Business not found.');
        redirect(APP_URL . '/index.php?page=admin_assignments');
    }
    $returnToAssignments = ($_POST['return_to'] ?? '') === 'admin_assignments';
    $destination = $returnToAssignments
        ? APP_URL . '/index.php?page=admin_assignments'
        : APP_URL . '/index.php?page=admin_business_details&id=' . (int) $businessId;
    try {
        $import = business_optional_excel_import();
        if ($import === null) {
            throw new DomainException('Select an .xlsx file to import.');
        }
        $summary = (new Business($pdo))->import((int) $businessId, business_admin_id(), $import);
        business_record_activity(
            $pdo,
            'business_content_imported',
            'Imported ' . $summary['imported'] . ' Excel content item(s) for business #' . (int) $businessId
        );
        set_flash('success', business_success_message('Excel import completed.', $summary));
    } catch (DomainException $error) {
        set_flash('error', $error->getMessage());
    } catch (PDOException $error) {
        error_log('Business Excel import failed: ' . $error->getMessage());
        set_flash('error', 'The Excel import could not be saved. Review the server log.');
    }
    redirect($destination);
}

function handle_business_user(PDO $pdo, bool $add): void
{
    business_post();
    $businessId = filter_var($_POST['business_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($businessId === false || $userId === false) {
        set_flash('error', 'Select a valid business and employee.');
        redirect(APP_URL . '/index.php?page=admin_assignments');
    }
    $returnToAssignments = ($_POST['return_to'] ?? '') === 'admin_assignments';
    $destination = $returnToAssignments
        ? APP_URL . '/index.php?page=admin_assignments'
        : APP_URL . '/index.php?page=admin_business_details&id=' . (int) $businessId;
    try {
        $model = new Business($pdo);
        if ($model->find((int) $businessId) === null) {
            throw new DomainException('Business not found.');
        }
        if ($add) {
            $model->replaceAssignedUser((int) $businessId, (int) $userId, business_admin_id());
            business_record_activity($pdo, 'business_user_replaced', 'Updated the assigned employee for business #' . (int) $businessId);
            set_flash('success', 'Assigned user updated. Pending business tasks were moved to the selected employee; completed history remains unchanged.');
        } else {
            $model->removeUser((int) $businessId, (int) $userId);
            business_record_activity($pdo, 'business_user_removed', 'Removed employee from business #' . (int) $businessId);
            set_flash('success', 'Employee removed from this business. Existing assignments are unchanged.');
        }
    } catch (DomainException $error) {
        set_flash('error', $error->getMessage());
    } catch (PDOException $error) {
        error_log('Business user update failed: ' . $error->getMessage());
        set_flash('error', 'The business user relationship could not be updated. Check that the employee is not already assigned.');
    }
    redirect($destination);
}

function business_card_content_redirect(array $input, int $businessId): void
{
    $returnToContentPage = ($input['return_to'] ?? '') === 'admin_business_content';
    $params = $returnToContentPage
        ? [
            'page' => 'admin_business_content',
            'business_id' => $businessId,
            'content_status' => in_array($input['content_status'] ?? '', ['all', 'active', 'used'], true)
                ? $input['content_status']
                : 'all',
        ]
        : ['page' => 'admin_assignments'];
    foreach (['search', 'date_from', 'date_to'] as $key) {
        if (isset($input[$key]) && is_string($input[$key]) && $input[$key] !== '') {
            $params[$key] = mb_substr($input[$key], 0, 100);
        }
    }
    foreach (['user_id', 'p'] as $key) {
        $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value !== false) {
            $params[$key] = $value;
        }
    }
    redirect(APP_URL . '/index.php?' . http_build_query($params));
}

function business_content_form_data(array $input, int $contentId): array
{
    $titles = $input['title'] ?? [];
    $texts = $input['text_content'] ?? [];
    $links = $input['reference_link'] ?? [];
    if (!is_array($titles) || !is_array($texts) || !is_array($links)) {
        throw new DomainException('The content changes are invalid.');
    }
    $title = $titles[$contentId] ?? null;
    $text = $texts[$contentId] ?? null;
    $referenceLink = $links[$contentId] ?? '';
    if (!is_string($title) || !is_string($text) || !is_string($referenceLink)) {
        throw new DomainException('The content changes are incomplete.');
    }
    $title = trim($title);
    $text = trim($text);
    $referenceLink = trim($referenceLink);
    $titleLength = preg_match_all('/./us', $title);
    if ($title === '' || $titleLength === false || $titleLength > 150) {
        throw new DomainException('Content title is required and must be 150 characters or fewer.');
    }
    if ($text === '' || strlen($text) > 65535) {
        throw new DomainException('Content is required and must be 65,535 bytes or fewer.');
    }
    if ($referenceLink !== '' && !valid_business_http_url($referenceLink, 500)) {
        throw new DomainException('Content reference link must be a valid HTTP or HTTPS URL no longer than 500 characters.');
    }
    return [
        'title' => $title,
        'text_content' => $text,
        'reference_link' => $referenceLink === '' ? null : $referenceLink,
    ];
}

function business_content_request_was_processed(string $requestId): bool
{
    return !empty($_SESSION['processed_business_content_requests'][$requestId]);
}

function mark_business_content_request_processed(string $requestId): void
{
    $processed = $_SESSION['processed_business_content_requests'] ?? [];
    $processed[$requestId] = true;
    $_SESSION['processed_business_content_requests'] = array_slice($processed, -32, null, true);
}

function handle_manage_business_content(PDO $pdo): void
{
    business_post();
    $businessId = filter_var($_POST['business_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $operation = $_POST['operation'] ?? '';
    $requestId = $_POST['content_request_id'] ?? null;
    if (
        $businessId === false
        || !is_string($operation)
        || !in_array($operation, ['save_one', 'bulk_save', 'delete_one', 'bulk_delete'], true)
        || !is_string($requestId)
        || !preg_match('/\A[a-f0-9]{64}\z/', $requestId)
    ) {
        set_flash('error', 'Invalid business content request.');
        if ($businessId !== false) {
            business_card_content_redirect($_POST, (int) $businessId);
        }
        redirect(APP_URL . '/index.php?page=admin_assignments');
    }
    if (business_content_request_was_processed($requestId)) {
        set_flash('info', 'This content request has already been processed.');
        business_card_content_redirect($_POST, (int) $businessId);
    }

    $model = new Business($pdo);
    $business = $model->find((int) $businessId);
    if ($business === null) {
        set_flash('error', 'Business not found.');
        redirect(APP_URL . '/index.php?page=admin_assignments');
    }

    try {
        $pdo->beginTransaction();
        if ($operation === 'save_one') {
            $contentId = filter_var($_POST['content_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($contentId === false) {
                throw new DomainException('Select valid content to save.');
            }
            $model->updateContent((int) $businessId, (int) $contentId, business_content_form_data($_POST, (int) $contentId));
        } elseif ($operation === 'bulk_save') {
            $rawIds = $_POST['content_ids'] ?? null;
            if (!is_array($rawIds) || $rawIds === []) {
                throw new DomainException('Select at least one content item to edit.');
            }
            foreach (array_unique($rawIds) as $rawId) {
                $contentId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($contentId === false) {
                    throw new DomainException('One or more selected content items are invalid.');
                }
                $model->updateContent((int) $businessId, (int) $contentId, business_content_form_data($_POST, (int) $contentId));
            }
        } elseif ($operation === 'delete_one') {
            $contentId = filter_var($_POST['content_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($contentId === false) {
                throw new DomainException('Select valid content to delete.');
            }
            $model->archiveContent((int) $businessId, (int) $contentId);
        } else {
            $rawIds = $_POST['content_ids'] ?? null;
            if (!is_array($rawIds) || $rawIds === []) {
                throw new DomainException('Select at least one content item to delete.');
            }
            foreach (array_unique($rawIds) as $rawId) {
                $contentId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($contentId === false) {
                    throw new DomainException('One or more selected content items are invalid.');
                }
                $model->archiveContent((int) $businessId, (int) $contentId);
            }
        }
        $pdo->commit();
        mark_business_content_request_processed($requestId);
        set_flash('success', match ($operation) {
            'save_one' => 'Content updated successfully.',
            'bulk_save' => 'Selected content updated successfully.',
            'delete_one' => 'Content removed from this business.',
            default => 'Selected content removed from this business.',
        });
    } catch (DomainException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('error', $error->getMessage());
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Business content management failed: ' . $error->getMessage());
        set_flash('error', 'The selected content could not be updated. Review the server log.');
    }

    business_card_content_redirect($_POST, (int) $businessId);
}

function handle_assign_business_content(PDO $pdo): void
{
    business_post();
    $businessId = filter_var($_POST['business_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $contentId = filter_var($_POST['content_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($businessId === false || $contentId === false || $userId === false) {
        set_flash('error', 'Select a valid content item and employee.');
        redirect(APP_URL . '/index.php?page=admin_assignments');
    }
    $destination = APP_URL . '/index.php?page=admin_business_details&id=' . (int) $businessId;
    try {
        $assignmentId = (new Business($pdo))->assignContent(
            (int) $businessId,
            (int) $contentId,
            (int) $userId,
            business_admin_id()
        );
        business_record_activity(
            $pdo,
            'business_content_assigned',
            'Created assignment #' . $assignmentId . ' from business #' . (int) $businessId
        );
        set_flash('success', 'Content assigned. Daily capacity was checked and reserved.');
    } catch (DomainException $error) {
        set_flash('error', $error->getMessage());
    } catch (PDOException $error) {
        error_log('Business content assignment failed: ' . $error->getMessage());
        set_flash('error', 'The content could not be assigned. It may already have been assigned; review the server log.');
    }
    redirect($destination);
}
