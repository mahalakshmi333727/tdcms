<?php
declare(strict_types=1);
$pageTitle = 'Excel Assignments';

$excelModel = new ExcelAssignment($pdo);
$assignments = $excelModel->listForUser((int) current_user_id());

require __DIR__ . '/../../layouts/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="mb-0"><i class="bi bi-file-earmark-spreadsheet"></i> Excel Assignments</h4>
    <p class="text-muted mb-0">Your Excel assignments are listed below.</p>
  </div>
</div>

<?php if (empty($assignments)): ?>
  <div class="card text-center py-5">
    <div class="card-body">
      <i class="bi bi-file-earmark-spreadsheet display-3 text-muted"></i>
      <h5 class="mt-3">No Excel assignments yet</h5>
      <p class="text-muted mb-0">When your admin assigns an Excel file, it will appear here.</p>
    </div>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($assignments as $assignment): ?>
      <div class="col-12">
        <div class="card">
          <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
              <h5 class="mb-1"><?= e($assignment['title'] ?: 'Untitled Excel Assignment') ?></h5>
              <p class="text-muted mb-1">Assigned <?= e(format_datetime($assignment['assigned_at'])) ?></p>
              <p class="mb-0"><strong>Status:</strong>
                <?php if ($assignment['status'] === 'pending'): ?>
                  <span class="badge badge-pending text-white">Pending</span>
                <?php else: ?>
                  <span class="badge badge-completed text-white">Completed</span>
                <?php endif; ?>
              </p>
            </div>
            <div class="d-flex gap-2 align-items-center">
              <a href="<?= e(APP_URL) ?>/index.php?page=user_excel_assignment_view&id=<?= (int) $assignment['id'] ?>" class="btn btn-outline-primary">
                <i class="bi bi-eye"></i> View
              </a>
              <a href="<?= e(APP_URL) ?>/index.php?action=download_excel_assignment&id=<?= (int) $assignment['id'] ?>" class="btn btn-outline-secondary">
                <i class="bi bi-download"></i> Download
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
