<?php
declare(strict_types=1);
$pageTitle = 'Admin Dashboard';

$assignmentModel = new Assignment($pdo);
$userModel = new User($pdo);
$counts = $assignmentModel->counts();
$page = max(1, (int) ($_GET['p'] ?? 1));
$recent = $assignmentModel->listAll(['status' => 'completed'], $page, 8);
$paginationParams = ['page' => 'admin_dashboard'];
$pageItems = pagination_page_items($recent['page'], $recent['pages']);

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
  <a href="<?= e(APP_URL) ?>/index.php?page=admin_user_form" class="btn btn-outline-secondary"><i class="bi bi-person-plus"></i> Create User</a>
</div>

<div class="card table-responsive-card">
  <div class="card-header bg-transparent fw-semibold">Recent Assignments</div>
  <table class="table table-hover align-middle mb-0">
    <thead>
      <tr><th>User</th><th>Text Preview</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php foreach ($recent['rows'] as $row): ?>
        <tr>
          <td><?= e($row['user_full_name'] ?: $row['user_username']) ?></td>
          <td><?= e(text_preview($row['text_content'], 50)) ?></td>
          <td>
            <?php if ($row['status'] === 'pending'): ?>
              <span class="badge badge-pending text-white">Pending</span>
            <?php else: ?>
              <span class="badge badge-completed text-white">Completed</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($recent['rows'])): ?>
        <tr><td colspan="3" class="text-center text-muted py-4">No assignments yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($recent['pages'] > 1): ?>
  <nav class="mt-3" aria-label="Dashboard assignments pagination">
    <ul class="pagination justify-content-center">
      <?php if ($recent['page'] > 1): ?>
        <li class="page-item">
          <a class="page-link" aria-label="Previous page" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $recent['page'] - 1])) ?>">&larr;</a>
        </li>
      <?php else: ?>
        <li class="page-item disabled"><span class="page-link" aria-label="Previous page">&larr;</span></li>
      <?php endif; ?>

      <?php foreach ($pageItems as $pageItem): ?>
        <?php if ($pageItem === '…'): ?>
          <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php else: ?>
          <li class="page-item <?= $pageItem === $recent['page'] ? 'active' : '' ?>">
            <a class="page-link" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $pageItem])) ?>" <?= $pageItem === $recent['page'] ? 'aria-current="page"' : '' ?>><?= $pageItem ?></a>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>

      <?php if ($recent['page'] < $recent['pages']): ?>
        <li class="page-item">
          <a class="page-link" aria-label="Next page" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $recent['page'] + 1])) ?>">&rarr;</a>
        </li>
      <?php else: ?>
        <li class="page-item disabled"><span class="page-link" aria-label="Next page">&rarr;</span></li>
      <?php endif; ?>
    </ul>
  </nav>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
