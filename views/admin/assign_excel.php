<?php
declare(strict_types=1);
$pageTitle = 'Assign Excel';

$userModel = new User($pdo);
$allUsers = $userModel->allActiveUsers();

require __DIR__ . '/../../layouts/header.php';
?>
<div class="row justify-content-center">
  <div class="col-12 col-md-9 col-lg-7">
    <div class="card p-4">
      <h5 class="mb-3"><i class="bi bi-file-earmark-spreadsheet"></i> Assign Excel to a User</h5>
      <form method="post" action="<?= e(APP_URL) ?>/index.php?action=create_excel_assignment" enctype="multipart/form-data" class="js-submit-loading">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="excel-assignment-user">Assigned User</label>
          <select id="excel-assignment-user" name="user_id" class="form-select" required>
            <option value="">Select a user…</option>
            <?php foreach ($allUsers as $u): ?>
              <option value="<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?> (<?= e($u['username']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label" for="excel-assignment-title">Assignment Title <span class="text-muted small">(optional)</span></label>
          <input type="text" class="form-control" id="excel-assignment-title" name="title" maxlength="150" placeholder="Use the filename when blank">
        </div>

        <div class="mb-3">
          <label class="form-label" for="excel-assignment-files">Excel File Upload</label>
          <input type="file" class="form-control" id="excel-assignment-files" name="excel_files[]" accept=".xlsx,.xls" multiple required>
        </div>

        <button type="submit" class="btn btn-primary w-100 mt-3"><i class="bi bi-send"></i> Assign Excel Files</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
