<?php
declare(strict_types=1);
$pageTitle = 'Excel Assignment';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require __DIR__ . '/../../layouts/header.php';
    echo '<div class="text-center py-5"><h1 class="display-1">404</h1><p class="text-muted">Excel assignment not found.</p><a href="' . e(APP_URL) . '/index.php?page=admin_excel_assignments" class="btn btn-primary">Back to Excel Assignments</a></div>';
    require __DIR__ . '/../../layouts/footer.php';
    exit;
}

$excelModel = new ExcelAssignment($pdo);
$assignment = $excelModel->findByIdWithUser($id);
if ($assignment === null) {
    http_response_code(404);
    $pageTitle = 'Not Found';
    require __DIR__ . '/../../layouts/header.php';
    echo '<div class="text-center py-5"><h1 class="display-1">404</h1><p class="text-muted">Excel assignment not found.</p><a href="' . e(APP_URL) . '/index.php?page=admin_excel_assignments" class="btn btn-primary">Back to Excel Assignments</a></div>';
    require __DIR__ . '/../../layouts/footer.php';
    exit;
}

$commentModel = new ExcelAssignmentComment($pdo);
$comments = $commentModel->listForAssignment($id);

$previewRows = [];
$previewError = null;
$filePath = __DIR__ . '/../../uploads/' . $assignment['stored_file_path'];
if (is_file($filePath) && is_readable($filePath)) {
    try {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            $assocRows = read_xlsx_first_sheet($filePath);
            if (!empty($assocRows)) {
                $headers = array_keys($assocRows[0]);
                $previewRows[] = $headers;
                foreach ($assocRows as $ar) {
                    $row = [];
                    foreach ($headers as $h) {
                        $row[] = $ar[$h] ?? '';
                    }
                    $previewRows[] = $row;
                }
            }
        } elseif ($ext === 'xls') {
            $previewError = 'This server cannot preview .xls files. Please download the file to view it.';
        }
    } catch (Throwable $e) {
        $previewRows = [];
        $previewError = $e->getMessage();
    }
}

require __DIR__ . '/../../layouts/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <h4 class="mb-1"><?= e($assignment['title'] ?: 'Untitled Excel Assignment') ?></h4>
    <p class="text-muted mb-0">Assigned to <?= e($assignment['user_full_name']) ?> (<?= e($assignment['user_username']) ?>)</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
  <a href="<?= e(APP_URL) ?>/index.php?action=download_excel_assignment&id=<?= (int) $assignment['id'] ?>" class="btn btn-outline-secondary"><i class="bi bi-download"></i> Download</a>
    <a href="<?= e(APP_URL) ?>/index.php?page=admin_excel_assignment_edit&id=<?= (int) $assignment['id'] ?>" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Edit</a>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <div class="text-muted small">Status</div>
        <div><?= $assignment['status'] === 'pending' ? '<span class="badge badge-pending text-white">Pending</span>' : '<span class="badge badge-completed text-white">Completed</span>' ?></div>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Assigned At</div>
        <div><?= e(format_datetime($assignment['assigned_at'])) ?></div>
      </div>
      <div class="col-md-12">
        <div class="text-muted small">Original File</div>
        <div><?= e($assignment['original_file_name']) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <h5 class="mb-3">Excel Preview</h5>
    <?php if (!empty($previewRows)): ?>
      <div class="table-responsive">
        <table class="table table-bordered table-sm mb-0">
          <thead class="table-light">
            <tr>
              <?php foreach ($previewRows[0] as $header): ?>
                <th><?= e($header) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($previewRows, 1, 20) as $row): ?>
              <tr>
                <?php foreach ($row as $cell): ?>
                  <td><?= e((string) $cell) ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php elseif ($previewError !== null): ?>
      <div class="text-muted small mb-0">Preview is not available for this file type. Please download the file to view it.</div>
    <?php else: ?>
      <div class="text-muted small mb-0">Preview is not available for this file type. Please download the file to view it.</div>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <h5 class="mb-3">Add Update</h5>
    <form method="post" action="<?= e(APP_URL) ?>/index.php?action=create_excel_assignment_comment" class="js-submit-loading">
      <?= csrf_field() ?>
      <input type="hidden" name="assignment_id" value="<?= (int) $assignment['id'] ?>">
      <div class="mb-3">
        <label class="form-label">Your Comment</label>
        <textarea class="form-control" name="comment_text" rows="4" maxlength="2000" required></textarea>
      </div>
      <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Save Update</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <h5 class="mb-3">Comment History</h5>
    <?php if (empty($comments)): ?>
      <div class="text-muted">No comments yet.</div>
    <?php else: ?>
      <div class="list-group">
        <?php foreach ($comments as $comment): ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <strong><?= e($comment['user_full_name']) ?></strong>
                <div class="text-muted small"><?= e(format_datetime($comment['created_at'])) ?></div>
              </div>
            </div>
            <p class="mb-0 mt-2"><?= nl2br(e($comment['comment_text'])) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
