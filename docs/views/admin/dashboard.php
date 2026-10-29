<?php
declare(strict_types=1);
$pageTitle = 'Admin Dashboard';

$assignmentModel = new Assignment($pdo);
$userModel = new User($pdo);
$counts = $assignmentModel->counts();
$recent = $assignmentModel->listAll([], 1, 8);

require __DIR__ . '/../../layouts/header.php';
?>
<h4 class="mb-4"><i class="bi bi-speedometer2"></i> Admin Dashboard</h4>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card stat-card p-3">
      <div class="text-muted small">Total Assignments</div>
      <div class="stat-value"><?= (int) $counts['total'] ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card p-3">
      <div class="text-muted small">Pending</div>
      <div class="stat-value text-warning"><?= (int) $counts['pending'] ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card p-3">
      <div class="text-muted small">Completed</div>
      <div class="stat-value text-success"><?= (int) $counts['completed'] ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card p-3">
      <div class="text-muted small">Active Users</div>
      <div class="stat-value text-primary"><?= count($userModel->allActiveUsers()) ?></div>
    </div>
  </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
  <a href="<?= e(APP_URL) ?>/index.php?page=admin_assign_form" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Assign Text</a>
  <a href="<?= e(APP_URL) ?>/index.php?page=admin_bulk_assign" class="btn btn-outline-primary"><i class="bi bi-layers"></i> Bulk Assign</a>
  <a href="<?= e(APP_URL) ?>/index.php?page=admin_user_form" class="btn btn-outline-secondary"><i class="bi bi-person-plus"></i> Create User</a>
</div>

<div class="card table-responsive-card">
  <div class="card-header bg-transparent fw-semibold">Recent Assignments</div>
  <table class="table table-hover align-middle mb-0">
    <thead>
      <tr><th>User</th><th>Text Preview</th><th>Status</th><th>Assigned</th></tr>
    </thead>
    <tbody>
      <?php foreach ($recent['rows'] as $row): ?>
        <tr>
          <td><?= e($row['user_full_name']) ?></td>
          <td><?= e(text_preview($row['text_content'], 50)) ?></td>
          <td>
            <?php if ($row['status'] === 'pending'): ?>
              <span class="badge badge-pending text-white">Pending</span>
            <?php else: ?>
              <span class="badge badge-completed text-white">Completed</span>
            <?php endif; ?>
          </td>
          <td><?= e(format_datetime($row['assigned_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($recent['rows'])): ?>
        <tr><td colspan="4" class="text-center text-muted py-4">No assignments yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
