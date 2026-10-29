<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

function test_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$userStmt = $pdo->query("SELECT id FROM users WHERE role = 'user' AND status = 'active' ORDER BY id LIMIT 1");
$userId = (int) $userStmt->fetchColumn();
$adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id LIMIT 1");
$adminId = (int) $adminStmt->fetchColumn();
test_expect($userId > 0 && $adminId > 0, 'Create an active employee and administrator before running this test.');

$assignmentModel = new Assignment($pdo);
$assignmentIds = [];

try {
    $token = bin2hex(random_bytes(8));
    $created = $assignmentModel->create([
        'user_id' => $userId,
        'title' => 'Completion flow test ' . $token,
        'category' => 'Testing',
        'text_content' => 'Private test content ' . $token,
        'reference_link' => 'https://example.com/',
        'tags' => 'test',
        'assigned_by' => $adminId,
    ]);
    test_expect($created['created'], 'Could not create the reference-link test assignment.');
    $assignmentId = (int) $created['id'];
    $assignmentIds[] = $assignmentId;

    $dashboardRow = null;
    foreach ($assignmentModel->pendingForUser($userId) as $row) {
        if ((int) $row['id'] === $assignmentId) {
            $dashboardRow = $row;
            break;
        }
    }
    test_expect($dashboardRow !== null, 'Pending task is missing from the dashboard query.');
    test_expect($dashboardRow['text_content'] === 'Private test content ' . $token, "Dashboard did not load this task's original content.");
    test_expect(!array_key_exists('reference_link', $dashboardRow), 'Reference URL leaked into the dashboard query.');
    test_expect($assignmentModel->stateForUser($assignmentId, -1) === null, 'Another employee can read this task state.');

    $copied = $assignmentModel->recordTaskProgress($assignmentId, $userId, ['text_copied' => true]);
    test_expect($copied !== null && $copied['status'] === 'pending', 'Copying must not complete a task.');
    test_expect($copied['text_content'] === 'Private test content ' . $token, 'Copy action returned content from a different task.');
    test_expect(!(bool) $copied['completion_prompt_pending'], 'Reference task prompted before the link step.');
    test_expect($assignmentModel->markCompleted($assignmentId, $userId) === null, 'Task completed before its reference link was opened.');

    $linked = $assignmentModel->recordTaskProgress($assignmentId, $userId, ['link_clicked' => true]);
    test_expect($linked !== null && (bool) $linked['completion_prompt_pending'], 'Ready task did not persist the pending popup state.');
    test_expect($linked['reference_link'] === 'https://example.com/', "Open Reference Link did not resolve this task's URL.");
    test_expect((bool) $assignmentModel->stateForUser($assignmentId, $userId)['completion_prompt_pending'], 'Popup state was not restored from the database.');

    $keptPending = $assignmentModel->keepCompletionPromptPending($assignmentId, $userId);
    test_expect($keptPending !== null && (bool) $keptPending['completion_prompt_pending'], 'No cleared the unresolved popup.');
    $afterNo = $assignmentModel->stateForUser($assignmentId, $userId);
    test_expect($afterNo['status'] === 'pending', 'No changed the task status.');
    test_expect((bool) $afterNo['completion_prompt_pending'], 'No did not preserve the server-side modal state.');

    $assignmentModel->recordTaskProgress($assignmentId, $userId, ['link_clicked' => true]);
    $completed = $assignmentModel->markCompleted($assignmentId, $userId);
    test_expect($completed !== null && $completed['status'] === 'completed', 'Yes did not complete the ready task.');
    test_expect($assignmentModel->markCompleted($assignmentId, $userId) === null, 'A duplicate completion was accepted.');
    test_expect($assignmentModel->recordTaskProgress($assignmentId, $userId, ['text_copied' => true]) === null, 'A completed task allowed another content copy.');
    $history = $assignmentModel->completedForUser($userId);
    foreach ($history['rows'] as $row) {
        if ((int) $row['id'] === $assignmentId) {
            test_expect(!array_key_exists('text_content', $row), 'Completed text leaked through task history.');
            test_expect(!array_key_exists('reference_link', $row), 'Completed reference URL leaked through task history.');
        }
    }
    if ($history['pages'] > 0) {
        $lastHistoryPage = $assignmentModel->completedForUser($userId, 999999, 1);
        test_expect($lastHistoryPage['page'] === $lastHistoryPage['pages'], 'Completed history did not clamp an out-of-range page to the last page.');
    }

    $noLink = $assignmentModel->create([
        'user_id' => $userId,
        'title' => 'No-link completion test ' . $token,
        'text_content' => 'No-link test content ' . $token,
        'reference_link' => null,
        'assigned_by' => $adminId,
    ]);
    test_expect($noLink['created'], 'Could not create the no-link test assignment.');
    $noLinkId = (int) $noLink['id'];
    $assignmentIds[] = $noLinkId;
    $noLinkCopy = $assignmentModel->recordTaskProgress($noLinkId, $userId, ['text_copied' => true]);
    test_expect($noLinkCopy !== null && (bool) $noLinkCopy['completion_prompt_pending'], 'Copying a no-link task did not set its popup state.');
    test_expect($noLinkCopy['text_content'] === 'No-link test content ' . $token, 'Copy action mixed content between assigned tasks.');
    test_expect($assignmentModel->markCompleted($noLinkId, $userId) !== null, 'No-link task could not be explicitly completed.');

    $missingContent = $assignmentModel->create([
        'user_id' => $userId,
        'title' => 'Missing content test ' . $token,
        'text_content' => '',
        'reference_link' => 'https://example.com/missing',
        'assigned_by' => $adminId,
    ]);
    test_expect($missingContent['created'], 'Could not create the missing-content test assignment.');
    $missingContentId = (int) $missingContent['id'];
    $assignmentIds[] = $missingContentId;
    $missingContentRow = null;
    foreach ($assignmentModel->pendingForUser($userId) as $row) {
        if ((int) $row['id'] === $missingContentId) {
            $missingContentRow = $row;
            break;
        }
    }
    test_expect($missingContentRow !== null && $missingContentRow['text_content'] === '', 'Missing review content was replaced with fabricated text.');
    test_expect(!(bool) $missingContentRow['completion_prompt_pending'], 'Missing review content exposed a completion prompt.');
    $missingContentRejected = false;
    try {
        $assignmentModel->recordTaskProgress($missingContentId, $userId, ['text_copied' => true]);
    } catch (DomainException $error) {
        $missingContentRejected = str_contains($error->getMessage(), 'no original review content');
    }
    test_expect($missingContentRejected, 'Missing review content was allowed to be copied.');
    test_expect($assignmentModel->markCompleted($missingContentId, $userId) === null, 'Missing review content was allowed to be completed.');
    $pendingPageOne = $assignmentModel->pendingPageForUser($userId, 1, 1);
    $pendingPageTwo = $assignmentModel->pendingPageForUser($userId, 2, 1);
    test_expect($pendingPageOne['total'] >= 2 && $pendingPageOne['pages'] === (int) ceil($pendingPageOne['total']), 'Pending task pagination returned an incorrect total or page count.');
    test_expect(count($pendingPageOne['rows']) === 1 && count($pendingPageTwo['rows']) === 1, 'Pending task pagination did not return one row per requested page.');
    test_expect((int) $pendingPageOne['rows'][0]['id'] !== (int) $pendingPageTwo['rows'][0]['id'], 'Pending task pagination repeated an assignment across pages.');
    $lastPendingPage = $assignmentModel->pendingPageForUser($userId, 999999, 1);
    test_expect($lastPendingPage['page'] === $lastPendingPage['pages'], 'Pending task pagination did not clamp an out-of-range page to the last page.');

    $editable = $assignmentModel->create([
        'user_id' => $userId,
        'title' => 'Assignment admin edit test ' . $token,
        'category' => 'Testing',
        'text_content' => 'Original editable content ' . $token,
        'reference_link' => 'https://example.com/original',
        'tags' => 'before',
        'assigned_by' => $adminId,
    ]);
    test_expect($editable['created'], 'Could not create assignment edit test row.');
    $editableId = (int) $editable['id'];
    $assignmentIds[] = $editableId;
    test_expect($assignmentModel->recordTaskProgress($editableId, $userId, ['text_copied' => true]) !== null, 'Could not seed assignment progress before editing.');
    $updated = $assignmentModel->updateAdmin($editableId, [
        'user_id' => $userId,
        'title' => 'Updated assignment ' . $token,
        'category' => 'Updated category',
        'text_content' => 'Updated assignment description ' . $token,
        'reference_link' => 'https://example.com/updated',
        'tags' => 'after',
    ]);
    test_expect($updated !== null && $updated['title'] === 'Updated assignment ' . $token, 'Assignment edit did not persist.');
    test_expect($updated['text_content'] === 'Updated assignment description ' . $token, 'Edited description did not persist.');
    test_expect($updated['reference_link'] === 'https://example.com/updated' && $updated['tags'] === 'after', 'Edited reference link or tags did not persist.');
    test_expect(!(bool) $updated['text_copied'] && !(bool) $updated['link_clicked'], 'Changing task content did not reset stale employee progress.');
    test_expect($assignmentModel->archiveAdmin($editableId), 'Assignment archive did not succeed.');
    test_expect(!$assignmentModel->archiveAdmin($editableId), 'Repeated assignment archive was accepted.');
    test_expect($assignmentModel->findActiveById($editableId) === null, 'Archived assignment remained active.');
    test_expect($assignmentModel->stateForUser($editableId, $userId) === null, 'Employee state API exposed an archived assignment.');
    test_expect(($assignmentModel->findById($editableId)['archived_at'] ?? null) !== null, 'Archived assignment history was not preserved.');
    test_expect($assignmentModel->findById($editableId)['status'] === 'pending', 'Archiving changed the historical task status.');
    $activeAssignments = $assignmentModel->listAll([], 1, 1000);
    test_expect(!in_array($editableId, array_map('intval', array_column($activeAssignments['rows'], 'id')), true), 'Archived assignment remained in the admin list.');

    $repeatTitle = 'Repeated assignment test ' . $token;
    $repeatText = 'Same review content ' . $token;
    $repeatData = [
        'user_id' => $userId,
        'title' => $repeatTitle,
        'category' => 'Testing',
        'text_content' => $repeatText,
        'reference_link' => null,
        'assigned_by' => $adminId,
    ];
    $repeatOne = $assignmentModel->create($repeatData);
    $repeatTwo = $assignmentModel->create($repeatData);
    test_expect($repeatOne['created'] && $repeatTwo['created'], 'Identical reviews were not both assigned.');
    test_expect($repeatOne['id'] !== $repeatTwo['id'], 'Repeated reviews did not receive distinct assignment IDs.');
    $assignmentIds[] = (int) $repeatOne['id'];
    $assignmentIds[] = (int) $repeatTwo['id'];

    $bulkRepeat = $assignmentModel->bulkCreate(
        [$userId],
        $repeatTitle,
        $repeatText,
        null,
        $adminId,
        'Testing'
    );
    test_expect($bulkRepeat['assigned'] === 1, 'Manual bulk assignment skipped a repeated pending review.');

    $importRepeat = $assignmentModel->bulkCreateFromTasks(
        [$userId],
        [
            ['title' => $repeatTitle, 'category' => 'Testing', 'text_content' => $repeatText],
            ['title' => $repeatTitle, 'category' => 'Testing', 'text_content' => $repeatText],
        ],
        $adminId
    );
    test_expect($importRepeat['assigned'] === 2, 'File import did not create a separate assignment for each repeated task row.');

    $repeatRows = $pdo->prepare(
        'SELECT id, status FROM assignments WHERE user_id = :user_id AND title = :title ORDER BY id'
    );
    $repeatRows->execute([':user_id' => $userId, ':title' => $repeatTitle]);
    $repeatAssignments = $repeatRows->fetchAll();
    test_expect(count($repeatAssignments) === 5, 'Repeated assignments were not persisted as five separate rows.');
    $repeatIds = array_map(static fn(array $row): int => (int) $row['id'], $repeatAssignments);
    test_expect(count($repeatIds) === count(array_unique($repeatIds)), 'Repeated assignment rows do not have unique IDs.');
    $assignmentIds = array_merge($assignmentIds, $repeatIds);

    $exportRows = $assignmentModel->exportAll(['user_id' => $userId]);
    $exportedRepeatIds = array_map(
        static fn(array $row): int => (int) $row['id'],
        array_values(array_filter($exportRows, static fn(array $row): bool => $row['title'] === $repeatTitle))
    );
    test_expect(count($exportedRepeatIds) === 5, 'CSV export query did not include every active repeated assignment.');

    $repeatId = $repeatIds[0];
    test_expect($assignmentModel->recordTaskProgress($repeatId, $userId, ['text_copied' => true]) !== null, 'Could not prepare one repeated assignment for completion.');
    test_expect($assignmentModel->markCompleted($repeatId, $userId) !== null, 'Could not complete one repeated assignment.');
    foreach (array_slice($repeatIds, 1) as $otherId) {
        test_expect($assignmentModel->findById($otherId)['status'] === 'pending', 'Completing one assignment changed another repeated assignment.');
    }

    $updatedRepeat = $assignmentModel->updateAdmin($repeatIds[1], [
        'user_id' => $userId,
        'title' => 'Edited repeated assignment ' . $token,
        'category' => 'Testing',
        'text_content' => 'Edited review content ' . $token,
        'reference_link' => '',
        'tags' => '',
    ]);
    test_expect($updatedRepeat !== null, 'Could not edit one repeated assignment.');
    test_expect($assignmentModel->findById($repeatIds[2])['title'] === $repeatTitle, 'Editing one assignment changed another repeated assignment.');
    test_expect($assignmentModel->archiveAdmin($repeatIds[3]), 'Could not archive one repeated assignment.');
    test_expect($assignmentModel->findActiveById($repeatIds[4]) !== null, 'Archiving one assignment affected another repeated assignment.');

    echo "PASS: task completion flow, repeated identical assignments, bulk/import records, assignment isolation, editing, archiving, duplicate completion, and archived-task access rejection.\n";
} finally {
    if ($assignmentIds !== []) {
        $delete = $pdo->prepare('DELETE FROM assignments WHERE id = :id AND user_id = :user_id');
        foreach ($assignmentIds as $id) {
            $delete->execute([':id' => $id, ':user_id' => $userId]);
        }
    }
    if (isset($repeatTitle)) {
        $deleteRepeats = $pdo->prepare('DELETE FROM assignments WHERE user_id = :user_id AND title = :title');
        $deleteRepeats->execute([':user_id' => $userId, ':title' => $repeatTitle]);
    }
}
