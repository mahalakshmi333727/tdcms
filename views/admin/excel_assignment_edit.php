<?php
declare(strict_types=1);
$pageTitle = 'Edit Excel Assignment';

$id = (int) ($_GET['id'] ?? 0);
$excelModel = new ExcelAssignment($pdo);
$assignment = $id > 0 ? $excelModel->findById($id) : null;
$users = (new User($pdo))->allActiveUsers();
$inactiveCurrentUserId = 0;
if ($assignment !== null && !in_array((int) $assignment['user_id'], array_map('intval', array_column($users, 'id')), true)) {
    $currentUser = (new User($pdo))->findById((int) $assignment['user_id']);
    if ($currentUser !== null && $currentUser['role'] === 'user') {
        $users[] = $currentUser;
        if ($currentUser['status'] !== 'active') {
            $inactiveCurrentUserId = (int) $currentUser['id'];
        }
    }
}

if ($assignment === null) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require __DIR__ . '/../../layouts/header.php';
    echo '<div class="text-center py-5"><h1 class="display-1">404</h1><p class="text-muted">Excel assignment not found.</p><a href="' . e(APP_URL) . '/index.php?page=admin_excel_assignments" class="btn btn-primary">Back to Excel Assignments</a></div>';
    require __DIR__ . '/../../layouts/footer.php';
    exit;
}

require __DIR__ . '/../../layouts/header.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-lg-6">
    <div class="card p-4">
      <h5 class="mb-3"><i class="bi bi-pencil-square"></i> Edit Excel Assignment</h5>
      <form method="post" action="<?= e(APP_URL) ?>/index.php?action=update_excel_assignment" enctype="multipart/form-data" class="js-submit-loading">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $assignment['id'] ?>">

        <div class="mb-3">
          <label class="form-label" for="excel-assignment-user">Assigned User</label>
          <select id="excel-assignment-user" name="user_id" class="form-select" required>
            <?php foreach ($users as $user): ?>
              <option value="<?= (int) $user['id'] ?>" <?= (int) $assignment['user_id'] === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['full_name'] . ' (' . $user['username'] . ')') ?><?= (int) $user['id'] === $inactiveCurrentUserId ? ' (inactive)' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label" for="excel-assignment-title">Assignment Title</label>
          <input type="text" class="form-control" id="excel-assignment-title" name="title" maxlength="150" required value="<?= e($assignment['title'] ?: '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label" for="excel-review-count">Reviews Count</label>
          <input type="number" class="form-control" id="excel-review-count" name="review_count" min="0" max="4294967295" step="1" required value="<?= (int) $assignment['review_count'] ?>">
        </div>

        <div class="mb-3">
          <label class="form-label" for="excel-reference-link">Reference Link</label>
          <input type="url" class="form-control" id="excel-reference-link" name="reference_link" maxlength="500" placeholder="https://example.com" value="<?= e($assignment['reference_link'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label" for="excel-assignment-status">Status</label>
          <select id="excel-assignment-status" name="status" class="form-select" required>
            <option value="pending" <?= $assignment['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="completed" <?= $assignment['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label" for="excel-assignment-file">Replace Excel File (optional)</label>
          <input type="file" class="form-control" id="excel-assignment-file" name="excel_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
          <div class="form-text">Upload a genuine .xlsx workbook, maximum 10 MB. Leave empty to keep <?= e($assignment['original_file_name']) ?>.</div>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Save Changes</button>
          <a href="<?= e(APP_URL) ?>/index.php?page=admin_excel_assignment_view&id=<?= (int) $assignment['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
