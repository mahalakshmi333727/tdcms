<?php
declare(strict_types=1);
$pageTitle = 'Login Logs';

$pageParam = $_GET['p'] ?? '';
$page = is_string($pageParam) && ctype_digit($pageParam) ? max(1, (int) $pageParam) : 1;
$readFilter = static fn (string $key): string => is_string($_GET[$key] ?? null)
    ? sanitize_input($_GET[$key])
    : '';
$filters = [
    'search' => $readFilter('search'),
    'status' => $readFilter('status'),
    'event_type' => $readFilter('event_type'),
    'date_from' => $readFilter('date_from'),
    'date_to' => $readFilter('date_to'),
];
if (!in_array($filters['status'], ['', 'success', 'failed', 'locked'], true)) {
    $filters['status'] = '';
}
if (!in_array($filters['event_type'], ['', 'login', 'activity'], true)) {
    $filters['event_type'] = '';
}
foreach (['date_from', 'date_to'] as $dateFilter) {
    if ($filters[$dateFilter] !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$dateFilter]);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $date->format('Y-m-d') !== $filters[$dateFilter]
        ) {
            $filters[$dateFilter] = '';
        }
    }
}
$logModel = new LoginLog($pdo);
$result = $logModel->list($filters, $page);
$pageItems = pagination_page_items($result['page'], $result['pages']);
$paginationParams = [
    'page' => 'admin_login_logs',
    'search' => $filters['search'],
    'status' => $filters['status'],
    'event_type' => $filters['event_type'],
    'date_from' => $filters['date_from'],
    'date_to' => $filters['date_to'],
];

require __DIR__ . '/../../layouts/header.php';
?>
<h4 class="mb-4"><i class="bi bi-box-arrow-in-right"></i> Login Logs</h4>

<form method="get" action="<?= e(APP_URL) ?>/index.php" class="card p-3 mb-4">
  <input type="hidden" name="page" value="admin_login_logs">
  <div class="row g-2">
    <div class="col-12 col-md-4">
      <input type="text" name="search" class="form-control" placeholder="Search user, ID, or description" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-6 col-md-2">
      <select name="event_type" class="form-select">
        <option value="">All Events</option>
        <option value="login" <?= $filters['event_type'] === 'login' ? 'selected' : '' ?>>Login</option>
        <option value="activity" <?= $filters['event_type'] === 'activity' ? 'selected' : '' ?>>Other Activity</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select name="status" class="form-select">
        <option value="">All Outcomes</option>
        <option value="success" <?= $filters['status'] === 'success' ? 'selected' : '' ?>>Success</option>
        <option value="failed" <?= $filters['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
        <option value="locked" <?= $filters['status'] === 'locked' ? 'selected' : '' ?>>Locked</option>
      </select>
    </div>
    <div class="col-6 col-md-1">
      <input type="date" name="date_from" class="form-control" aria-label="Start date" value="<?= e($filters['date_from']) ?>">
    </div>
    <div class="col-6 col-md-1">
      <input type="date" name="date_to" class="form-control" aria-label="End date" value="<?= e($filters['date_to']) ?>">
    </div>
    <div class="col-12 col-md-2">
      <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i></button>
    </div>
  </div>
</form>

<p class="text-muted small">Showing <?= (int) $result['total'] ?> log event(s).</p>
<div class="card table-responsive-card">
  <table class="table table-hover align-middle mb-0">
    <thead><tr><th>User</th><th>Role</th><th>Event</th><th>Action / Outcome</th><th>Description</th><th>IP Address</th><th>Device / Browser</th><th>Timestamp</th></tr></thead>
    <tbody>
      <?php foreach ($result['rows'] as $log): ?>
        <tr>
          <td>
            <?= e($log['full_name'] ?: $log['username']) ?>
            <div class="small text-muted"><?= e($log['username']) ?><?= $log['user_id'] ? ' · #' . (int) $log['user_id'] : '' ?></div>
          </td>
          <td><?= e($log['role'] ? ucfirst($log['role']) : '—') ?></td>
          <td><span class="badge text-bg-<?= $log['event_type'] === 'login' ? 'primary' : 'secondary' ?>"><?= $log['event_type'] === 'login' ? 'Login' : 'Activity' ?></span></td>
          <td>
            <?php $statusClass = ['success' => 'success', 'failed' => 'danger', 'locked' => 'warning'][$log['event_action']] ?? 'secondary'; ?>
            <span class="badge text-bg-<?= e($statusClass) ?>"><?= e(ucwords(str_replace('_', ' ', $log['event_action']))) ?></span>
          </td>
          <td><?= e($log['description']) ?></td>
          <td class="ip-cell"><?= e($log['ip_address']) ?></td>
          <td><span class="text-truncate d-inline-block" style="max-width: 240px;"><?= e($log['user_agent']) ?></span></td>
          <td><?= e(format_datetime($log['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($result['rows'])): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No log events match these filters.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- BEGIN requested Login Logs pagination -->
<?php if ($result['pages'] > 1): ?>
  <nav class="mt-3" aria-label="Login Logs pagination">
    <ul class="pagination justify-content-center">
      <?php if ($result['page'] > 1): ?>
        <li class="page-item">
          <a class="page-link" aria-label="Previous page" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $result['page'] - 1])) ?>">&larr;</a>
        </li>
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
        <li class="page-item">
          <a class="page-link" aria-label="Next page" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $result['page'] + 1])) ?>">&rarr;</a>
        </li>
      <?php else: ?>
        <li class="page-item disabled"><span class="page-link" aria-label="Next page">&rarr;</span></li>
      <?php endif; ?>
    </ul>
  </nav>
<?php endif; ?>
<!-- END requested Login Logs pagination -->

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
