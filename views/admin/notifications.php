<?php
declare(strict_types=1);
$pageTitle = 'Admin Notifications';

$page = max(1, (int) ($_GET['p'] ?? 1));
$notificationModel = new AdminNotification($pdo);
$result = $notificationModel->list($page);
$pageItems = pagination_page_items($result['page'], $result['pages']);

require __DIR__ . '/../../layouts/header.php';
?>
<h4 class="mb-4"><i class="bi bi-bell"></i> Notifications</h4>

<div class="card table-responsive-card">
  <table class="table table-hover align-middle mb-0">
    <thead>
      <tr><th>Status</th><th>User</th><th>Task</th><th>Assigned</th><th>Deadline</th><th>Task Status</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php foreach ($result['rows'] as $notification): ?>
        <tr>
          <td>
            <?php if ($notification['read_at'] === null): ?>
              <span class="badge text-bg-primary">Unread</span>
            <?php else: ?>
              <span class="badge text-bg-secondary">Read</span>
            <?php endif; ?>
            <div class="small text-muted">Task Overdue</div>
          </td>
          <td><?= e($notification['user_name']) ?></td>
          <td>
            <?= e($notification['assignment_title'] ?: 'Assignment') ?>
            <?php if ($notification['assignment_id'] !== null): ?>
              <span class="text-muted small">#<?= (int) $notification['assignment_id'] ?></span>
            <?php endif; ?>
          </td>
          <td><?= e(format_datetime($notification['assigned_at'])) ?></td>
          <td><?= e(format_datetime($notification['deadline_at'])) ?></td>
          <td><?= e(ucfirst($notification['current_status'] ?? 'removed')) ?></td>
          <td>
            <div class="d-flex flex-wrap gap-2">
              <?php if ($notification['assignment_id'] !== null && $notification['archived_at'] === null): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL) ?>/index.php?page=admin_assignments&amp;assignment_id=<?= (int) $notification['assignment_id'] ?>">View task</a>
              <?php endif; ?>
              <?php if ($notification['read_at'] === null): ?>
                <form method="post" action="<?= e(APP_URL) ?>/index.php?action=mark_notification_read">
                  <?= csrf_field() ?>
                  <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary">Mark read</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($result['rows'])): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No overdue notifications.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($result['pages'] > 1): ?>
  <nav class="mt-3" aria-label="Notification pagination">
    <ul class="pagination justify-content-center">
      <?php if ($result['page'] > 1): ?>
        <li class="page-item"><a class="page-link" aria-label="Previous page" href="<?= e(APP_URL) ?>/index.php?page=admin_notifications&amp;p=<?= $result['page'] - 1 ?>">&larr;</a></li>
      <?php else: ?>
        <li class="page-item disabled"><span class="page-link" aria-label="Previous page">&larr;</span></li>
      <?php endif; ?>
      <?php foreach ($pageItems as $pageItem): ?>
        <?php if ($pageItem === '…'): ?>
          <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php else: ?>
          <li class="page-item <?= $pageItem === $result['page'] ? 'active' : '' ?>">
            <a class="page-link" href="<?= e(APP_URL) ?>/index.php?page=admin_notifications&amp;p=<?= $pageItem ?>" <?= $pageItem === $result['page'] ? 'aria-current="page"' : '' ?>><?= $pageItem ?></a>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($result['page'] < $result['pages']): ?>
        <li class="page-item"><a class="page-link" aria-label="Next page" href="<?= e(APP_URL) ?>/index.php?page=admin_notifications&amp;p=<?= $result['page'] + 1 ?>">&rarr;</a></li>
      <?php else: ?>
        <li class="page-item disabled"><span class="page-link" aria-label="Next page">&rarr;</span></li>
      <?php endif; ?>
    </ul>
  </nav>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
