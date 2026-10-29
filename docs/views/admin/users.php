<?php
declare(strict_types=1);
$pageTitle = 'Manage Users';

$page = max(1, (int) ($_GET['p'] ?? 1));
$filters = [
    'search' => sanitize_input($_GET['search'] ?? ''),
    'role'   => sanitize_input($_GET['role'] ?? ''),
    'status' => sanitize_input($_GET['status'] ?? ''),
];

$userModel = new User($pdo);
$result = $userModel->list($filters, $page);

require __DIR__ . '/../../layouts/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h4 class="mb-0"><i class="bi bi-people"></i> Manage Users</h4>
  <a href="<?= e(APP_URL) ?>/index.php?page=admin_user_form" class="btn btn-primary"><i class="bi bi-person-plus"></i> New User</a>
</div>

<form method="get" action="<?= e(APP_URL) ?>/index.php" class="card p-3 mb-4">
  <input type="hidden" name="page" value="admin_users">
  <div class="row g-2">
    <div class="col-12 col-md-4">
      <input type="text" name="search" class="form-control" placeholder="Search name, username, email" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-6 col-md-3">
      <select name="role" class="form-select">
        <option value="">All Roles</option>
        <option value="admin" <?= $filters['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
        <option value="user" <?= $filters['role'] === 'user' ? 'selected' : '' ?>>User</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <select name="status" class="form-select">
        <option value="">All Status</option>
        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="disabled" <?= $filters['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
      </select>
    </div>
    <div class="col-12 col-md-2">
      <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button>
    </div>
  </div>
</form>

<div class="card table-responsive-card">
  <table class="table table-hover align-middle mb-0">
    <thead>
      <tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th class="text-end">Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($result['rows'] as $u): ?>
        <tr>
          <td><?= e($u['full_name']) ?></td>
          <td><?= e($u['username']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><span class="badge text-bg-secondary"><?= e(ucfirst($u['role'])) ?></span></td>
          <td>
            <?php if ($u['status'] === 'active'): ?>
              <span class="badge badge-completed text-white">Active</span>
            <?php else: ?>
              <span class="badge text-bg-danger">Disabled</span>
            <?php endif; ?>
          </td>
          <td><?= e(format_datetime($u['last_login_at'])) ?></td>
          <td class="text-end">
            <a href="<?= e(APP_URL) ?>/index.php?page=admin_user_form&id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
            <?php if ($u['role'] !== 'admin'): ?>
              <?php if ($u['status'] === 'active'): ?>
                <button class="btn btn-sm btn-outline-warning btn-toggle-status" data-id="<?= (int) $u['id'] ?>" data-new-status="disabled" title="Disable"><i class="bi bi-slash-circle"></i></button>
              <?php else: ?>
                <button class="btn btn-sm btn-outline-success btn-toggle-status" data-id="<?= (int) $u['id'] ?>" data-new-status="active" title="Enable"><i class="bi bi-check-circle"></i></button>
              <?php endif; ?>
              <button class="btn btn-sm btn-outline-danger btn-delete-user" data-id="<?= (int) $u['id'] ?>" title="Delete"><i class="bi bi-trash"></i></button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($result['rows'])): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($result['pages'] > 1): ?>
  <nav class="mt-3">
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
        <li class="page-item <?= $i === $result['page'] ? 'active' : '' ?>">
          <a class="page-link" href="?page=admin_users&p=<?= $i ?>&search=<?= urlencode($filters['search']) ?>&role=<?= urlencode($filters['role']) ?>&status=<?= urlencode($filters['status']) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
