<?php
declare(strict_types=1);
$pageTitle = 'Completed History';

$page = max(1, (int) ($_GET['p'] ?? 1));
$assignmentModel = new Assignment($pdo);
$result = $assignmentModel->completedForUser((int) current_user_id(), $page);
$paginationParams = ['page' => 'history'];
$paginationStart = 0;
$paginationEnd = 0;
if ($result['pages'] > 7) {
    $paginationStart = max(2, min($result['page'] - 1, $result['pages'] - 2));
    $paginationEnd = min($result['pages'] - 1, max($result['page'] + 1, 3));
}

require __DIR__ . '/../../layouts/header.php';
?>
<h4 class="mb-4"><i class="bi bi-check2-circle"></i> Completed History</h4>

<?php if (empty($result['rows'])): ?>
  <div class="card text-center py-5">
    <div class="card-body">
      <i class="bi bi-clock-history display-3 text-muted"></i>
      <h5 class="mt-3">No completed items yet</h5>
      <p class="text-muted mb-0">Tasks you explicitly mark as completed will show up here.</p>
    </div>
  </div>
<?php else: ?>
  <div class="card table-responsive-card">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Business Name</th>
          <th>Task Type</th>
          <th>Text Preview</th>
          <th>Reference Link</th>
          <th>Completed On</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($result['rows'] as $row): ?>
          <tr>
            <td><?= e($row['business_name']) ?></td>
            <td><span class="badge text-bg-secondary"><?= e($row['task_type']) ?></span></td>
            <td>Unavailable after completion</td>
            <td>Unavailable after completion</td>
            <td><?= e(format_datetime($row['completed_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($result['pages'] > 1): ?>
    <nav class="mt-3" aria-label="Completed history pagination">
      <ul class="pagination justify-content-center">
        <?php if ($result['page'] > 1): ?>
          <li class="page-item">
            <a class="page-link" aria-label="Previous page" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $result['page'] - 1])) ?>">&larr;</a>
          </li>
        <?php else: ?>
          <li class="page-item disabled"><span class="page-link" aria-label="Previous page">&larr;</span></li>
        <?php endif; ?>

        <?php if ($result['pages'] <= 7): ?>
          <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
            <li class="page-item <?= $i === $result['page'] ? 'active' : '' ?>">
              <a class="page-link" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $i])) ?>" <?= $i === $result['page'] ? 'aria-current="page"' : '' ?>><?= $i ?></a>
            </li>
          <?php endfor; ?>
        <?php else: ?>
          <li class="page-item <?= $result['page'] === 1 ? 'active' : '' ?>">
            <a class="page-link" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => 1])) ?>" <?= $result['page'] === 1 ? 'aria-current="page"' : '' ?>>1</a>
          </li>
          <?php if ($paginationStart > 2): ?>
            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
          <?php endif; ?>
          <?php for ($i = $paginationStart; $i <= $paginationEnd; $i++): ?>
            <li class="page-item <?= $i === $result['page'] ? 'active' : '' ?>">
              <a class="page-link" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $i])) ?>" <?= $i === $result['page'] ? 'aria-current="page"' : '' ?>><?= $i ?></a>
            </li>
          <?php endfor; ?>
          <?php if ($paginationEnd < $result['pages'] - 1): ?>
            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
          <?php endif; ?>
          <li class="page-item <?= $result['page'] === $result['pages'] ? 'active' : '' ?>">
            <a class="page-link" href="<?= e(APP_URL) ?>/index.php?<?= e(http_build_query($paginationParams + ['p' => $result['pages']])) ?>" <?= $result['page'] === $result['pages'] ? 'aria-current="page"' : '' ?>><?= $result['pages'] ?></a>
          </li>
        <?php endif; ?>

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
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
