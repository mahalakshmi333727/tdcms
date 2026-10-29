<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/models/Business.php';

function assert_business_duplicate_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$adminId = (int) $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id LIMIT 1")->fetchColumn();
$userId = (int) $pdo->query("SELECT id FROM users WHERE role = 'user' AND status = 'active' ORDER BY id LIMIT 1")->fetchColumn();
assert_business_duplicate_test($adminId > 0 && $userId > 0, 'An active admin and user are required.');

$model = new Business($pdo);
$token = bin2hex(random_bytes(8));
$businessId = 0;
try {
    $businessId = $model->create([
        'name' => 'Repeated content test ' . $token,
        'reviews_count' => 999,
        'reference_link' => null,
        'daily_assignment_limit' => 10,
    ], [$userId], $adminId);
    assert_business_duplicate_test(
        (int) $model->find($businessId)['imported_reviews_count'] === 0,
        'A submitted manual review count was used instead of the imported review total.'
    );

    $task = [
        'title' => 'Repeated review ' . $token,
        'text_content' => 'Identical review content ' . $token,
        'reference_link' => null,
        'tags' => '',
    ];
    $first = $model->assignBulkTasks($businessId, $userId, $adminId, [$task]);
    $second = $model->assignBulkTasks($businessId, $userId, $adminId, [$task]);
    assert_business_duplicate_test($first === 1 && $second === 1, 'Matching business content was not assigned twice.');

    $content = $pdo->prepare(
        'SELECT id, content_hash FROM business_content WHERE business_id = :business_id ORDER BY id'
    );
    $content->execute([':business_id' => $businessId]);
    $contentRows = $content->fetchAll();
    assert_business_duplicate_test(count($contentRows) === 2, 'Repeated content did not create distinct business content records.');
    assert_business_duplicate_test(
        (int) $contentRows[0]['id'] !== (int) $contentRows[1]['id']
        && $contentRows[0]['content_hash'] === $contentRows[1]['content_hash'],
        'Repeated content identities or hashes were not preserved.'
    );

    $importResult = $model->import($businessId, $adminId, [
        'filename' => 'duplicate-test.xlsx',
        'records' => [[
            'source_record' => 2,
            'title' => $task['title'],
            'text_content' => $task['text_content'],
            'reference_link' => null,
            'content_hash' => $contentRows[0]['content_hash'],
        ]],
        'errors' => [],
    ]);
    assert_business_duplicate_test(
        $importResult['imported'] === 0 && count($importResult['errors']) === 1,
        'File imports no longer reject matching content already stored for the business.'
    );
    assert_business_duplicate_test(
        (int) $model->find($businessId)['imported_reviews_count'] === 0,
        'A rejected duplicate import changed the review total.'
    );

    $newImportedText = 'New imported review ' . $token;
    $successfulImport = $model->import($businessId, $adminId, [
        'filename' => 'successful-import-test.xlsx',
        'records' => [[
            'source_record' => 3,
            'title' => 'New imported title ' . $token,
            'text_content' => $newImportedText,
            'reference_link' => null,
            'content_hash' => hash('sha256', 'New imported title ' . $token . "\0" . $newImportedText . "\0"),
        ]],
        'errors' => [],
    ]);
    assert_business_duplicate_test($successfulImport['imported'] === 1, 'A valid import did not create its review record.');
    assert_business_duplicate_test(
        (int) $model->find($businessId)['imported_reviews_count'] === 1,
        'Reviews Count does not equal the number of successfully imported reviews.'
    );

    $listedBusiness = array_values(array_filter(
        $model->list(1, 10, ['search' => 'Repeated content test ' . $token])['rows'],
        static fn (array $row): bool => (int) $row['id'] === $businessId
    ));
    assert_business_duplicate_test(
        count($listedBusiness) === 1 && (int) $listedBusiness[0]['imported_reviews_count'] === 1,
        'Assignments list does not show the actual successfully imported review count.'
    );

    echo "PASS: review count reflects successful imports and duplicate imports remain rejected.\n";
} finally {
    if ($businessId > 0) {
        $pdo->beginTransaction();
        try {
            $assignments = $pdo->prepare(
                'DELETE FROM assignments WHERE business_content_id IN (SELECT id FROM business_content WHERE business_id = :business_id)'
            );
            $assignments->execute([':business_id' => $businessId]);
            $deleteContent = $pdo->prepare('DELETE FROM business_content WHERE business_id = :business_id');
            $deleteContent->execute([':business_id' => $businessId]);
            $deleteImports = $pdo->prepare('DELETE FROM business_imports WHERE business_id = :business_id');
            $deleteImports->execute([':business_id' => $businessId]);
            $deleteHistory = $pdo->prepare('DELETE FROM business_user_history WHERE business_id = :business_id');
            $deleteHistory->execute([':business_id' => $businessId]);
            $deleteBusiness = $pdo->prepare('DELETE FROM businesses WHERE id = :business_id');
            $deleteBusiness->execute([':business_id' => $businessId]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
}
