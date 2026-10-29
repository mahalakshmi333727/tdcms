<?php
declare(strict_types=1);
$pageTitle = 'Login Logs';

$page = max(1, (int) ($_GET['p'] ?? 1));
$filters = [
    'search' => sanitize_input($_GET['search'] ?? ''),
    'status' => sanitize_input($_GET['status'] ?? ''),
];
$logModel = new LoginLog($pdo);
$result = $logModel->list($filters, $page);

require __DIR__ . '/../../layouts/header.php';
?>
<h4 class="mb-4"><i class="bi bi-box-arrow-in-right"></i> User Login Logs</h4>

<form method="get" action="<?= e(APP_URL) ?>/index.php" class="card p-3 mb-4">
  <input type="hidden" name="page" value="admin_login_logs">
  <div class="row g-2">
    <div class="col-12 col-md-6">
      <input type="text" name="search" class="form-control" placeholder="Search username" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-8 col-md-4">
      <select name="status" class="form-select">
        <option value="">All</option>
        <option value="success" <?= $filters['status'] === 'success' ? 'selected' : '' ?>>Success</option>
        <option value="failed" <?= $filters['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
        <option value="locked" <?= $filters['status'] === 'locked' ? 'selected' : '' ?>>Locked</option>
      </select>
    </div>
    <div class="col-4 col-md-2">
      <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button>
    </div>
  </div>
</form>

<div class="card table-responsive-card">
  <table class="table table-hover align-middle mb-0">
    <thead><tr><th>Username</th><th>Status</th><th>IP Address</th><th>User Agent</th><th>Timestamp</th></tr></thead>
    <tbody>
      <?php foreach ($result['rows'] as $log): ?>
        <tr>
          <td><?= e($log['username']) ?></td>
          <td>
            <?php
              $statusClass = ['success' => 'success', 'failed' => 'danger', 'locked' => 'warning'][$log['status']] ?? 'secondary';
            ?>
            <span class="badge text-bg-<?= e($statusClass) ?>"><?= e(ucfirst($log['status'])) ?></span>
          </td>
          <td><?= e($log['ip_address']) ?></td>
          <td class="text-truncate d-inline-block" style="max-width: 240px;"><?= e($log['user_agent']) ?></td>
          <td><?= e(format_datetime($log['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($result['rows'])): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">No login activity recorded.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($result['pages'] > 1): ?>
  <nav class="mt-3">
    <ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
        <li class="page-item <?= $i === $result['page'] ? 'active' : '' ?>">
          <a class="page-link" href="?page=admin_login_logs&p=<?= $i ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
