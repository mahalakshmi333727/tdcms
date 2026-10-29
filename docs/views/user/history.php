<?php
declare(strict_types=1);
$pageTitle = 'Completed History';

$page = max(1, (int) ($_GET['p'] ?? 1));
$assignmentModel = new Assignment($pdo);
$result = $assignmentModel->completedForUser((int) current_user_id(), $page);

require __DIR__ . '/../../layouts/header.php';
?>
<h4 class="mb-4"><i class="bi bi-check2-circle"></i> Completed History</h4>

<?php if (empty($result['rows'])): ?>
  <div class="card text-center py-5">
    <div class="card-body">
      <i class="bi bi-clock-history display-3 text-muted"></i>
      <h5 class="mt-3">No completed items yet</h5>
      <p class="text-muted mb-0">Items you copy will show up here.</p>
    </div>
  </div>
<?php else: ?>
  <div class="card table-responsive-card">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Title</th>
          <th>Category</th>
          <th>Text Preview</th>
          <th>Reference Link</th>
          <th>Completed On</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($result['rows'] as $row): ?>
          <tr>
            <td><?= e($row['title'] ?: '—') ?></td>
            <td><?= !empty($row['category']) ? '<span class="badge text-bg-secondary">' . e($row['category']) . '</span>' : '—' ?></td>
            <td><?= e(text_preview($row['text_content'], 60)) ?></td>
            <td>
              <?php if (!empty($row['reference_link'])): ?>
                <a href="<?= e($row['reference_link']) ?>" target="_blank" rel="noopener noreferrer">
                  <i class="bi bi-box-arrow-up-right"></i> Open
                </a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= e(format_datetime($row['completed_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($result['pages'] > 1): ?>
    <nav class="mt-3">
      <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
          <li class="page-item <?= $i === $result['page'] ? 'active' : '' ?>">
            <a class="page-link" href="?page=history&p=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
