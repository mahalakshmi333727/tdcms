<?php
declare(strict_types=1);
$pageTitle = 'Excel Assignments';

$page = max(1, (int) ($_GET['p'] ?? 1));
$filters = [
    'status'  => sanitize_input($_GET['status'] ?? ''),
    'user_id' => (int) ($_GET['user_id'] ?? 0) ?: '',
    'search'  => sanitize_input($_GET['search'] ?? ''),
];

$excelModel = new ExcelAssignment($pdo);
$userModel = new User($pdo);
$result = $excelModel->listAll($filters, $page);
$allUsers = $userModel->allActiveUsers();
$pageItems = pagination_page_items($result['page'], $result['pages']);
$paginationParams = [
    'page' => 'admin_excel_assignments',
    'status' => $filters['status'],
    'user_id' => $filters['user_id'],
    'search' => $filters['search'],
];

require __DIR__ . '/../../layouts/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h4 class="mb-0"><i class="bi bi-file-earmark-spreadsheet"></i> Excel Assignments</h4>
  <a href="<?= e(APP_URL) ?>/index.php?page=admin_assign_excel" class="btn btn-outline-success"><i class="bi bi-plus-circle"></i> Assign Excel</a>
</div>

<form method="get" action="<?= e(APP_URL) ?>/index.php" class="card p-3 mb-4">
  <input type="hidden" name="page" value="admin_excel_assignments">
  <div class="row g-2">
    <div class="col-12 col-md-4">
      <input type="text" name="search" class="form-control" placeholder="Search title or user" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-6 col-md-3">
      <select name="status" class="form-select">
        <option value="">All Status</option>
        <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <select name="user_id" class="form-select">
        <option value="">All Users</option>
        <?php foreach ($allUsers as $u): ?>
          <option value="<?= (int) $u['id'] ?>" <?= ((string) $filters['user_id'] === (string) $u['id']) ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-2">
      <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button>
    </div>
  </div>
</form>

<div class="card table-responsive-card">
  <table class="table table-hover align-middle mb-0">
    <thead>
      <tr>
        <th>User</th>
        <th>Title</th>
        <th>File</th>
        <th>Status</th>
        <th>Assigned</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($result['rows'] as $row): ?>
        <tr>
          <td><?= e($row['user_full_name']) ?></td>
          <td><?= e($row['title'] ?: 'Untitled Excel Assignment') ?></td>
          <td><?= e($row['original_file_name']) ?></td>
          <td>
            <?php if ($row['status'] === 'pending'): ?>
              <span class="badge badge-pending text-white">Pending</span>
            <?php else: ?>
              <span class="badge badge-completed text-white">Completed</span>
            <?php endif; ?>
          </td>
          <td><?= e(format_datetime($row['assigned_at'])) ?></td>
          <td>
            <a href="<?= e(APP_URL) ?>/index.php?page=admin_excel_assignment_view&id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
            <a href="<?= e(APP_URL) ?>/index.php?action=download_excel_assignment&id=<?= (int) $row['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i> Download</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($result['rows'])): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No Excel assignments found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($result['pages'] > 1): ?>
  <nav class="mt-3" aria-label="Excel assignments pagination">
    <ul class="pagination justify-content-center">
      <?php if ($result['page'] > 1): ?>
        <li class="page-item"><a class="page-link" aria-label="Previous page" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $result['page'] - 1])) ?>">&larr;</a></li>
      <?php else: ?>
        <li class="page-item disabled"><span class="page-link" aria-label="Previous page">&larr;</span></li>
      <?php endif; ?>
      <?php foreach ($pageItems as $pageItem): ?>
        <?php if ($pageItem === '…'): ?>
          <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php else: ?>
          <li class="page-item <?= $pageItem === $result['page'] ? 'active' : '' ?>">
            <a class="page-link" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $pageItem])) ?>" <?= $pageItem === $result['page'] ? 'aria-current="page"' : '' ?>><?= $pageItem ?></a>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($result['page'] < $result['pages']): ?>
        <li class="page-item"><a class="page-link" aria-label="Next page" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $result['page'] + 1])) ?>">&rarr;</a></li>
      <?php else: ?>
        <li class="page-item disabled"><span class="page-link" aria-label="Next page">&rarr;</span></li>
      <?php endif; ?>
    </ul>
  </nav>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
